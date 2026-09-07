<?php

namespace App\Support;

/**
 * Curated Google Fonts catalogue and delivery helpers.
 *
 * Fonts are SERVED FROM fonts.bunny.net, not fonts.googleapis.com. Bunny Fonts
 * mirrors the Google catalogue with an identical URL shape but logs no visitor
 * data, which matters here: loading fonts from Google means every page view
 * sends a visitor's IP to Google, and German courts have found that to be an
 * unlawful transfer under GDPR without consent. The body font already used
 * Bunny; the admin-selected title font did not, and that inconsistency is what
 * this class exists to remove.
 *
 * The list is bundled rather than fetched from the Google Fonts Developer API:
 * the API needs a key, and an admin page that can't render its font dropdown
 * because a key expired or the network is down is worse than a list that covers
 * the families anyone actually picks.
 */
class GoogleFonts
{
    /**
     * family => [category, [available weights]]
     *
     * Weights are the subset Bunny serves as static instances for that family;
     * offering a weight the family doesn't have silently falls back to the
     * nearest one, which looks like a bug to whoever picked it.
     */
    public const FAMILIES = [
        // ── Sans-serif ─────────────────────────────────────────────────────
        'Inter' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Roboto' => ['sans-serif', [300, 400, 500, 700, 900]],
        'Open Sans' => ['sans-serif', [300, 400, 500, 600, 700, 800]],
        'Lato' => ['sans-serif', [300, 400, 700, 900]],
        'Montserrat' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Poppins' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Raleway' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Nunito' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Nunito Sans' => ['sans-serif', [300, 400, 600, 700, 800, 900]],
        'Work Sans' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Rubik' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Mulish' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Manrope' => ['sans-serif', [300, 400, 500, 600, 700, 800]],
        'DM Sans' => ['sans-serif', [400, 500, 700]],
        'Karla' => ['sans-serif', [300, 400, 500, 600, 700, 800]],
        'Barlow' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Barlow Condensed' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Barlow Semi Condensed' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Archivo' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Archivo Narrow' => ['sans-serif', [400, 500, 600, 700]],
        'Figtree' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Outfit' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Plus Jakarta Sans' => ['sans-serif', [300, 400, 500, 600, 700, 800]],
        'Sora' => ['sans-serif', [300, 400, 500, 600, 700, 800]],
        'Urbanist' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Public Sans' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Lexend' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Epilogue' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Sarabun' => ['sans-serif', [300, 400, 500, 600, 700, 800]],
        'Source Sans 3' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Noto Sans' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'PT Sans' => ['sans-serif', [400, 700]],
        'Ubuntu' => ['sans-serif', [300, 400, 500, 700]],
        'Fira Sans' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Cabin' => ['sans-serif', [400, 500, 600, 700]],
        'Quicksand' => ['sans-serif', [300, 400, 500, 600, 700]],
        'Josefin Sans' => ['sans-serif', [300, 400, 500, 600, 700]],
        'Asap' => ['sans-serif', [400, 500, 600, 700]],
        'Assistant' => ['sans-serif', [300, 400, 500, 600, 700, 800]],
        'Heebo' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Overpass' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Red Hat Display' => ['sans-serif', [400, 500, 600, 700, 800, 900]],
        'Red Hat Text' => ['sans-serif', [400, 500, 600, 700]],
        'Space Grotesk' => ['sans-serif', [300, 400, 500, 600, 700]],
        'Chivo' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Exo 2' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Titillium Web' => ['sans-serif', [300, 400, 600, 700, 900]],
        'Signika' => ['sans-serif', [300, 400, 500, 600, 700]],
        'Hind' => ['sans-serif', [300, 400, 500, 600, 700]],
        'Mukta' => ['sans-serif', [300, 400, 500, 600, 700, 800]],
        'Catamaran' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Kanit' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Prompt' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'IBM Plex Sans' => ['sans-serif', [300, 400, 500, 600, 700]],
        'Jost' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Commissioner' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Readex Pro' => ['sans-serif', [300, 400, 500, 600, 700]],
        'Onest' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Geologica' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Schibsted Grotesk' => ['sans-serif', [400, 500, 600, 700, 800, 900]],
        'Instrument Sans' => ['sans-serif', [400, 500, 600, 700]],
        'Bricolage Grotesque' => ['sans-serif', [300, 400, 500, 600, 700, 800]],
        'Anek Latin' => ['sans-serif', [300, 400, 500, 600, 700, 800]],
        'Familjen Grotesk' => ['sans-serif', [400, 500, 600, 700]],
        'Golos Text' => ['sans-serif', [400, 500, 600, 700, 800, 900]],
        'Wix Madefor Display' => ['sans-serif', [400, 500, 600, 700, 800]],
        'Albert Sans' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Be Vietnam Pro' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Encode Sans' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Saira' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Saira Condensed' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Oxygen' => ['sans-serif', [300, 400, 700]],
        'Dosis' => ['sans-serif', [300, 400, 500, 600, 700, 800]],
        'Maven Pro' => ['sans-serif', [400, 500, 600, 700, 800, 900]],
        'Varela Round' => ['sans-serif', [400]],
        'Questrial' => ['sans-serif', [400]],
        'Didact Gothic' => ['sans-serif', [400]],
        'Arimo' => ['sans-serif', [400, 500, 600, 700]],
        'Tajawal' => ['sans-serif', [300, 400, 500, 700, 800, 900]],
        'Almarai' => ['sans-serif', [300, 400, 700, 800]],
        'Cairo' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Noto Sans JP' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Noto Sans KR' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],
        'Noto Sans SC' => ['sans-serif', [300, 400, 500, 600, 700, 800, 900]],

        // ── Condensed / display sans (good for dense card titles) ──────────
        'Oswald' => ['display', [300, 400, 500, 600, 700]],
        'Fjalla One' => ['display', [400]],
        'Anton' => ['display', [400]],
        'Bebas Neue' => ['display', [400]],
        'Archivo Black' => ['display', [400]],
        'Teko' => ['display', [300, 400, 500, 600, 700]],
        'Rajdhani' => ['display', [300, 400, 500, 600, 700]],
        'Roboto Condensed' => ['display', [300, 400, 500, 600, 700, 800, 900]],
        'Fira Sans Condensed' => ['display', [300, 400, 500, 600, 700, 800, 900]],
        'Encode Sans Condensed' => ['display', [300, 400, 500, 600, 700, 800, 900]],
        'Yanone Kaffeesatz' => ['display', [300, 400, 500, 600, 700]],
        'Big Shoulders Display' => ['display', [300, 400, 500, 600, 700, 800, 900]],
        'League Spartan' => ['display', [300, 400, 500, 600, 700, 800, 900]],
        'Bungee' => ['display', [400]],
        'Righteous' => ['display', [400]],
        'Russo One' => ['display', [400]],
        'Staatliches' => ['display', [400]],
        'Alfa Slab One' => ['display', [400]],
        'Bowlby One SC' => ['display', [400]],
        'Titan One' => ['display', [400]],
        'Passion One' => ['display', [400, 700, 900]],
        'Squada One' => ['display', [400]],
        'Chakra Petch' => ['display', [300, 400, 500, 600, 700]],
        'Orbitron' => ['display', [400, 500, 600, 700, 800, 900]],
        'Audiowide' => ['display', [400]],
        'Michroma' => ['display', [400]],
        'Syncopate' => ['display', [400, 700]],
        'Monoton' => ['display', [400]],
        'Unbounded' => ['display', [300, 400, 500, 600, 700, 800, 900]],

        // ── Serif ──────────────────────────────────────────────────────────
        'Playfair Display' => ['serif', [400, 500, 600, 700, 800, 900]],
        'Merriweather' => ['serif', [300, 400, 700, 900]],
        'Lora' => ['serif', [400, 500, 600, 700]],
        'PT Serif' => ['serif', [400, 700]],
        'Noto Serif' => ['serif', [300, 400, 500, 600, 700, 800, 900]],
        'Source Serif 4' => ['serif', [300, 400, 500, 600, 700, 800, 900]],
        'Libre Baskerville' => ['serif', [400, 700]],
        'Crimson Text' => ['serif', [400, 600, 700]],
        'Cormorant Garamond' => ['serif', [300, 400, 500, 600, 700]],
        'EB Garamond' => ['serif', [400, 500, 600, 700, 800]],
        'Bitter' => ['serif', [300, 400, 500, 600, 700, 800, 900]],
        'Arvo' => ['serif', [400, 700]],
        'Domine' => ['serif', [400, 500, 600, 700]],
        'Zilla Slab' => ['serif', [300, 400, 500, 600, 700]],
        'Roboto Slab' => ['serif', [300, 400, 500, 600, 700, 800, 900]],
        'Josefin Slab' => ['serif', [300, 400, 500, 600, 700]],
        'Cardo' => ['serif', [400, 700]],
        'Spectral' => ['serif', [300, 400, 500, 600, 700, 800]],
        'Frank Ruhl Libre' => ['serif', [300, 400, 500, 600, 700, 800, 900]],
        'Literata' => ['serif', [300, 400, 500, 600, 700, 800, 900]],
        'Newsreader' => ['serif', [300, 400, 500, 600, 700, 800]],
        'Fraunces' => ['serif', [300, 400, 500, 600, 700, 800, 900]],
        'Instrument Serif' => ['serif', [400]],
        'DM Serif Display' => ['serif', [400]],
        'DM Serif Text' => ['serif', [400]],
        'Abril Fatface' => ['serif', [400]],
        'Bodoni Moda' => ['serif', [400, 500, 600, 700, 800, 900]],
        'Prata' => ['serif', [400]],
        'Rozha One' => ['serif', [400]],
        'Yeseva One' => ['serif', [400]],
        'Marcellus' => ['serif', [400]],
        'Cinzel' => ['serif', [400, 500, 600, 700, 800, 900]],
        'Vollkorn' => ['serif', [400, 500, 600, 700, 800, 900]],
        'Alegreya' => ['serif', [400, 500, 600, 700, 800, 900]],
        'Petrona' => ['serif', [300, 400, 500, 600, 700, 800, 900]],
        'Faustina' => ['serif', [300, 400, 500, 600, 700, 800]],
        'Gelasio' => ['serif', [400, 500, 600, 700]],

        // ── Monospace ──────────────────────────────────────────────────────
        'JetBrains Mono' => ['monospace', [300, 400, 500, 600, 700, 800]],
        'Fira Code' => ['monospace', [300, 400, 500, 600, 700]],
        'IBM Plex Mono' => ['monospace', [300, 400, 500, 600, 700]],
        'Source Code Pro' => ['monospace', [300, 400, 500, 600, 700, 800, 900]],
        'Space Mono' => ['monospace', [400, 700]],
        'Roboto Mono' => ['monospace', [300, 400, 500, 600, 700]],
        'Inconsolata' => ['monospace', [300, 400, 500, 600, 700, 800, 900]],
        'DM Mono' => ['monospace', [300, 400, 500]],
        'Courier Prime' => ['monospace', [400, 700]],

        // ── Handwriting / script ───────────────────────────────────────────
        'Pacifico' => ['handwriting', [400]],
        'Lobster' => ['handwriting', [400]],
        'Dancing Script' => ['handwriting', [400, 500, 600, 700]],
        'Caveat' => ['handwriting', [400, 500, 600, 700]],
        'Satisfy' => ['handwriting', [400]],
        'Great Vibes' => ['handwriting', [400]],
        'Sacramento' => ['handwriting', [400]],
        'Permanent Marker' => ['handwriting', [400]],
        'Shadows Into Light' => ['handwriting', [400]],
        'Indie Flower' => ['handwriting', [400]],
        'Kalam' => ['handwriting', [300, 400, 700]],
        'Courgette' => ['handwriting', [400]],
        'Yellowtail' => ['handwriting', [400]],
    ];

    /** Human labels for the category each family is grouped under. */
    public const CATEGORY_LABELS = [
        'sans-serif' => 'Sans-serif',
        'display' => 'Display & condensed',
        'serif' => 'Serif',
        'monospace' => 'Monospace',
        'handwriting' => 'Handwriting & script',
    ];

    private const CDN = 'https://fonts.bunny.net';

    /** Weight offered when a family doesn't publish the requested one. */
    public const DEFAULT_WEIGHT = 400;

    public static function exists(?string $family): bool
    {
        return $family !== null && $family !== '' && isset(self::FAMILIES[$family]);
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::FAMILIES);
    }

    /**
     * Options for a Filament Select, grouped by category so the list is
     * navigable rather than ~170 flat rows.
     *
     * Each label is a span carrying the family's own font-family, so the
     * dropdown previews the typeface rather than naming it. Requires
     * `->allowHtml()` on the Select and the preview stylesheet in the admin
     * page's head; without the stylesheet every row falls back to the panel
     * font and the preview is silently useless.
     *
     * The family name is the only thing rendered, so there is no user-supplied
     * text here to escape — but it is run through e() regardless, because these
     * strings are emitted as raw HTML and that should not depend on the current
     * contents of a constant.
     *
     * @return array<string, array<string, string>>
     */
    public static function groupedOptions(): array
    {
        $groups = [];

        foreach (self::FAMILIES as $family => [$category]) {
            $label = sprintf(
                '<span style="font-family:%s; font-size:1.05em;">%s</span>',
                e(self::stack($family)),
                e($family)
            );

            $groups[self::CATEGORY_LABELS[$category]][$family] = $label;
        }

        return $groups;
    }

    /** Weights a family actually publishes. @return list<int> */
    public static function weights(?string $family): array
    {
        return self::exists($family) ? self::FAMILIES[$family][1] : [self::DEFAULT_WEIGHT];
    }

    /** Standard weight ladder, used when no specific family constrains it. */
    public const WEIGHT_LABELS = [
        300 => 'Light (300)',
        400 => 'Regular (400)',
        500 => 'Medium (500)',
        600 => 'Semibold (600)',
        700 => 'Bold (700)',
        800 => 'Extrabold (800)',
        900 => 'Black (900)',
    ];

    /**
     * Weight options for a Select, labelled by their typographic name so the
     * choice means something to someone who doesn't think in numbers.
     *
     * With no family chosen the slot inherits the theme font, which publishes
     * the full ladder — so the full ladder is offered. Narrowing to a single
     * weight here would make any stored value other than 400 fail validation the
     * next time the form is saved, which is not obviously connected to the empty
     * font field that caused it.
     *
     * @return array<int, string>
     */
    public static function weightOptions(?string $family): array
    {
        if (! self::exists($family)) {
            return self::WEIGHT_LABELS;
        }

        $options = [];
        foreach (self::weights($family) as $weight) {
            $options[$weight] = self::WEIGHT_LABELS[$weight] ?? (string) $weight;
        }

        return $options;
    }

    /** Nearest published weight, so a stored value can't request a missing file. */
    public static function resolveWeight(?string $family, int|string|null $weight): int
    {
        $available = self::weights($family);
        $weight = (int) ($weight ?: self::DEFAULT_WEIGHT);

        if (in_array($weight, $available, true)) {
            return $weight;
        }

        usort($available, fn ($a, $b) => abs($a - $weight) <=> abs($b - $weight));

        return $available[0] ?? self::DEFAULT_WEIGHT;
    }

    /** A family's CSS font-stack, with a category-appropriate fallback. */
    public static function stack(?string $family): ?string
    {
        if (! self::exists($family)) {
            return null;
        }

        $generic = match (self::FAMILIES[$family][0]) {
            'serif' => 'Georgia, serif',
            'monospace' => 'ui-monospace, monospace',
            'handwriting' => 'cursive',
            default => 'system-ui, -apple-system, sans-serif',
        };

        return "'" . $family . "', " . $generic;
    }

    /**
     * Bunny Fonts stylesheet URL for the given families.
     *
     * @param array<string, list<int>> $families family => weights
     */
    public static function stylesheetUrl(array $families): ?string
    {
        $parts = [];

        foreach ($families as $family => $weights) {
            if (! self::exists($family)) {
                continue;
            }

            $available = self::weights($family);
            $weights = array_values(array_unique(array_filter(
                array_map('intval', $weights),
                fn ($w) => in_array($w, $available, true)
            )));

            if (empty($weights)) {
                $weights = [self::resolveWeight($family, self::DEFAULT_WEIGHT)];
            }

            sort($weights);

            // Bunny uses the legacy Google CSS1 syntax: lowercase, hyphenated
            // family name, then a comma-separated weight list.
            $parts[] = strtolower(str_replace(' ', '-', $family)) . ':' . implode(',', $weights);
        }

        if (empty($parts)) {
            return null;
        }

        return self::CDN . '/css?family=' . implode('|', $parts) . '&display=swap';
    }
}
