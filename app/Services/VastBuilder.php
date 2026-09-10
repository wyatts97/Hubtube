<?php

namespace App\Services;

use App\Models\VideoAd;
use DOMDocument;
use DOMElement;

/**
 * Renders a VideoAd row as a VAST 3.0 document.
 *
 * This exists so the player only ever speaks one ad language. The site stores
 * four creative types — `mp4`, `html`, `vast` and `vpaid` — and the old Vue ad
 * overlay carried a bespoke rendering path for each, plus its own impression
 * and skip-countdown logic layered on top. Expressing all four as VAST moves
 * that work into the player, where the spec already defines it:
 *
 *   mp4        -> InLine with a <Linear> and one <MediaFile> per variant
 *   html       -> InLine with <NonLinearAds> (suits on-pause placements)
 *   vast/vpaid -> Wrapper around the third-party tag
 *
 * Wrapping third-party tags rather than handing them to the player directly is
 * deliberate: it lets our own <Impression> and <ClickTracking> ride along, so
 * network creatives land in ad_stats_daily on the same footing as local ones.
 * Previously they were only counted because the overlay fired a beacon by hand.
 *
 * Built with DOMDocument rather than a Blade template on purpose. Creative
 * names and click URLs are admin-supplied and end up inside CDATA sections,
 * where string templating is exactly how a stray `]]>` turns into malformed XML
 * that fails silently in the player.
 */
class VastBuilder
{
    /**
     * Nominal values for creatives uploaded before ProcessAdCreativeJob started
     * probing the source. VAST requires <Duration> and MediaFile dimensions, and
     * a rejected document means no ad at all — so a plausible guess beats an
     * invalid document. `ads:backfill-media-metadata` replaces these with real
     * numbers.
     */
    public const FALLBACK_DURATION = 30;
    public const FALLBACK_WIDTH = 640;
    public const FALLBACK_HEIGHT = 360;

    /** Default slot for a non-linear (HTML) creative; matches Fluid Player's supported sizes. */
    public const NONLINEAR_WIDTH = 300;
    public const NONLINEAR_HEIGHT = 250;

    /**
     * Build the document for one ad, or the empty document when there is none.
     *
     * A null $ad is the normal "nothing to serve" case — an unsold break, a
     * disabled placement, or an ad-free viewer — and must still be well-formed
     * VAST. Players treat an empty <VAST> as an empty break and continue
     * straight to content; anything malformed makes them wait out the timeout.
     */
    public function build(?VideoAd $ad, string $placement, int $skipAfter = 0): string
    {
        $doc = $this->document();
        $root = $doc->documentElement;

        if (! $ad) {
            return $doc->saveXML();
        }

        $adEl = $doc->createElement('Ad');
        $adEl->setAttribute('id', (string) $ad->id);
        $root->appendChild($adEl);

        if (in_array($ad->type, ['vast', 'vpaid'], true)) {
            $this->buildWrapper($doc, $adEl, $ad, $placement);
        } elseif ($ad->type === 'html') {
            $this->buildNonLinear($doc, $adEl, $ad, $placement);
        } else {
            $this->buildLinear($doc, $adEl, $ad, $placement, $skipAfter);
        }

        return $doc->saveXML();
    }

    /** A valid, empty VAST document — the correct response for "no ad". */
    public function empty(): string
    {
        return $this->document()->saveXML();
    }

    // ── Document scaffolding ──────────────────────────────────────────────

    protected function document(): DOMDocument
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElement('VAST');
        $root->setAttribute('version', '3.0');
        $doc->appendChild($root);

        return $doc;
    }

    // ── Creative types ────────────────────────────────────────────────────

    /**
     * A local MP4 creative as an InLine linear ad.
     *
     * The HLS variant is listed first when it exists: players pick the first
     * media file they can play, and the segmented variant starts noticeably
     * faster than downloading the head of a progressive MP4. The MP4 stays as
     * the fallback for players without MSE.
     */
    protected function buildLinear(DOMDocument $doc, DOMElement $adEl, VideoAd $ad, string $placement, int $skipAfter): void
    {
        $inline = $doc->createElement('InLine');
        $adEl->appendChild($inline);

        $this->appendCommon($doc, $inline, $ad, $placement);

        $creatives = $doc->createElement('Creatives');
        $inline->appendChild($creatives);

        $creative = $doc->createElement('Creative');
        $creative->setAttribute('id', (string) $ad->id);
        $creative->setAttribute('sequence', '1');
        $creatives->appendChild($creative);

        $duration = $ad->duration > 0 ? (int) $ad->duration : self::FALLBACK_DURATION;

        $linear = $doc->createElement('Linear');

        // Only advertise a skip offset that falls inside the creative. A
        // skipoffset at or past the duration leaves a skip button that never
        // becomes clickable.
        if ($skipAfter > 0 && $skipAfter < $duration) {
            $linear->setAttribute('skipoffset', $this->timecode($skipAfter));
        }

        $creative->appendChild($linear);

        $linear->appendChild($doc->createElement('Duration', $this->timecode($duration)));
        $this->appendTrackingEvents($doc, $linear, $ad, $placement);
        $this->appendVideoClicks($doc, $linear, $ad, $placement);

        $mediaFiles = $doc->createElement('MediaFiles');
        $linear->appendChild($mediaFiles);

        $width = $ad->width > 0 ? (int) $ad->width : self::FALLBACK_WIDTH;
        $height = $ad->height > 0 ? (int) $ad->height : self::FALLBACK_HEIGHT;

        // Progressive MP4 only, deliberately — no HLS variant is advertised.
        //
        // Fluid Player pre-validates an ad by fetching *only the first*
        // MediaFile and rejecting the whole ad unless that response's
        // Content-Type contains "video" and canPlayType() accepts it
        // (resolveAdTreeRequests -> validateMediaFile). An HLS playlist fails
        // both tests outside Safari, which silently discards the entire ad.
        //
        // Listing HLS second does not help either: the later selection pass
        // (getSupportedMediaFileObject) only stops early on a "probably" match,
        // and bare "video/mp4" scores "maybe", so a trailing HLS entry
        // overwrites the MP4 and routes playback through hls.js anyway.
        //
        // Ad creatives are short and already downscaled by ProcessAdCreativeJob,
        // so segmented delivery buys little here, and staying on MP4 keeps the
        // hls.js chunk off pages whose main video does not need it.
        $mediaFiles->appendChild(
            $this->mediaFile($doc, $ad->mediaUrl(), 'video/mp4', 'progressive', $width, $height)
        );
    }

    /**
     * An HTML creative as a non-linear ad.
     *
     * Non-linear is the honest mapping: these creatives are markup overlays with
     * no duration of their own, which is precisely what <NonLinear> describes,
     * and it is the shape an on-pause placement expects.
     */
    protected function buildNonLinear(DOMDocument $doc, DOMElement $adEl, VideoAd $ad, string $placement): void
    {
        $inline = $doc->createElement('InLine');
        $adEl->appendChild($inline);

        $this->appendCommon($doc, $inline, $ad, $placement);

        $creatives = $doc->createElement('Creatives');
        $inline->appendChild($creatives);

        $creative = $doc->createElement('Creative');
        $creative->setAttribute('id', (string) $ad->id);
        $creatives->appendChild($creative);

        $nonLinearAds = $doc->createElement('NonLinearAds');
        $creative->appendChild($nonLinearAds);

        $nonLinear = $doc->createElement('NonLinear');
        $nonLinear->setAttribute('width', (string) ($ad->width > 0 ? $ad->width : self::NONLINEAR_WIDTH));
        $nonLinear->setAttribute('height', (string) ($ad->height > 0 ? $ad->height : self::NONLINEAR_HEIGHT));
        $nonLinearAds->appendChild($nonLinear);

        $nonLinear->appendChild($this->cdataElement($doc, 'HTMLResource', (string) $ad->content));

        if ($ad->click_url) {
            $nonLinear->appendChild($this->cdataElement($doc, 'NonLinearClickThrough', $ad->click_url));
            $nonLinear->appendChild(
                $this->cdataElement($doc, 'NonLinearClickTracking', $this->trackUrl('click', $ad, $placement))
            );
        }

        $this->appendTrackingEvents($doc, $nonLinearAds, $ad, $placement);
    }

    /**
     * A third-party tag as a wrapper.
     *
     * The player resolves the inner tag itself, so the network keeps full
     * control of the creative, skip behaviour and its own reporting. We attach
     * our tracking alongside rather than replacing theirs.
     */
    protected function buildWrapper(DOMDocument $doc, DOMElement $adEl, VideoAd $ad, string $placement): void
    {
        $wrapper = $doc->createElement('Wrapper');
        $adEl->appendChild($wrapper);

        $this->appendCommon($doc, $wrapper, $ad, $placement);

        $wrapper->appendChild(
            $this->cdataElement($doc, 'VASTAdTagURI', trim((string) $ad->content))
        );

        $creatives = $doc->createElement('Creatives');
        $wrapper->appendChild($creatives);

        $creative = $doc->createElement('Creative');
        $creative->setAttribute('id', (string) $ad->id);
        $creatives->appendChild($creative);

        // A wrapper's <Linear> carries tracking only — no Duration, no
        // MediaFiles. Those belong to the wrapped document.
        $linear = $doc->createElement('Linear');
        $creative->appendChild($linear);

        $this->appendTrackingEvents($doc, $linear, $ad, $placement);
        $this->appendWrapperClicks($doc, $linear, $ad, $placement);
    }

    // ── Shared fragments ──────────────────────────────────────────────────

    /** AdSystem, AdTitle and our impression beacon — required in both InLine and Wrapper. */
    protected function appendCommon(DOMDocument $doc, DOMElement $parent, VideoAd $ad, string $placement): void
    {
        $system = $doc->createElement('AdSystem', 'HubTube');
        $system->setAttribute('version', '1.0');
        $parent->appendChild($system);

        $parent->appendChild($this->cdataElement($doc, 'AdTitle', (string) $ad->name));
        $parent->appendChild($this->cdataElement($doc, 'Impression', $this->trackUrl('impression', $ad, $placement)));
    }

    /**
     * Only `complete` is emitted.
     *
     * The full quartile set would multiply beacon volume by five to plot a curve
     * nothing in the admin renders. Completion alone answers the question that
     * was previously unanswerable — did anyone watch this creative through?
     */
    protected function appendTrackingEvents(DOMDocument $doc, DOMElement $parent, VideoAd $ad, string $placement): void
    {
        $events = $doc->createElement('TrackingEvents');
        $parent->appendChild($events);

        $tracking = $this->cdataElement($doc, 'Tracking', $this->trackUrl('event', $ad, $placement, 'complete'));
        $tracking->setAttribute('event', 'complete');
        $events->appendChild($tracking);
    }

    protected function appendVideoClicks(DOMDocument $doc, DOMElement $linear, VideoAd $ad, string $placement): void
    {
        if (! $ad->click_url) {
            return;
        }

        $clicks = $doc->createElement('VideoClicks');
        $linear->appendChild($clicks);

        $clicks->appendChild($this->cdataElement($doc, 'ClickThrough', $ad->click_url));
        $clicks->appendChild($this->cdataElement($doc, 'ClickTracking', $this->trackUrl('click', $ad, $placement)));
    }

    /** A wrapper may add click *tracking*, but the wrapped ad owns the destination. */
    protected function appendWrapperClicks(DOMDocument $doc, DOMElement $linear, VideoAd $ad, string $placement): void
    {
        $clicks = $doc->createElement('VideoClicks');
        $linear->appendChild($clicks);

        $clicks->appendChild($this->cdataElement($doc, 'ClickTracking', $this->trackUrl('click', $ad, $placement)));
    }

    protected function mediaFile(
        DOMDocument $doc,
        string $url,
        string $mime,
        string $delivery,
        int $width,
        int $height
    ): DOMElement {
        $el = $this->cdataElement($doc, 'MediaFile', $url);
        $el->setAttribute('delivery', $delivery);
        $el->setAttribute('type', $mime);
        $el->setAttribute('width', (string) $width);
        $el->setAttribute('height', (string) $height);

        return $el;
    }

    // ── Primitives ────────────────────────────────────────────────────────

    /**
     * An element whose text is wrapped in CDATA, as VAST expects for URLs and
     * markup.
     *
     * The `]]>` strip is the point of this helper. Ad names and click URLs come
     * from admin input, and a literal `]]>` anywhere inside would close the
     * section early and produce a document that parses as garbage — a failure
     * that surfaces as "the ad silently did not play", never as an exception.
     */
    protected function cdataElement(DOMDocument $doc, string $name, string $value): DOMElement
    {
        $el = $doc->createElement($name);
        $el->appendChild($doc->createCDATASection(str_replace(']]>', ']]&gt;', $value)));

        return $el;
    }

    /** Seconds as the HH:MM:SS timecode VAST requires. */
    protected function timecode(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    /**
     * A tracking beacon URL.
     *
     * `placement` rides along on every one so ad_stats_daily keeps the same
     * per-break dimension the overlay used to supply by hand — without it every
     * roll collapses into one undifferentiated bucket.
     */
    protected function trackUrl(string $kind, VideoAd $ad, string $placement, ?string $event = null): string
    {
        $params = ['ad_id' => $ad->id, 'placement' => $placement];

        if ($event !== null) {
            $params['event'] = $event;
        }

        return route("vast.track.{$kind}", $params);
    }
}
