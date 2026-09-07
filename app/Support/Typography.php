<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Resolves the admin's font choices into CSS custom properties and the single
 * stylesheet URL that loads them.
 *
 * Sits alongside ThemeTokens and follows the same rule: one source of truth.
 * app.blade.php (first paint and the <link>), HandleInertiaRequests (the Inertia
 * `theme` prop) and the Filament form all read from here, so the font used to
 * render the page and the font actually downloaded cannot disagree.
 *
 * Only the weights a slot actually uses are requested. A family like Inter
 * publishes seven weights; loading all of them to render metadata at 400 costs
 * the visitor six font files they will never see.
 */
class Typography
{
    /**
     * The typographic slots, their settings keys, and the built-in defaults.
     *
     * Archivo / Archivo Narrow are the design system's defaults — see the
     * --font-sans and --font-display tokens in resources/css/app.css. An
     * operator overriding them here replaces those tokens at runtime.
     *
     * `extraWeights` are weights the slot renders beyond its configured one:
     * body text needs its regular weight plus the semibold used for buttons and
     * emphasis, or those render as faux-bold.
     */
    public const SLOTS = [
        'body' => [
            'familyKey' => 'font_body_family',
            'weightKey' => 'font_body_weight',
            'cssVar' => '--font-sans',
            'defaultFamily' => 'Archivo',
            'defaultWeight' => 400,
            'extraWeights' => [500, 600, 700],
        ],
        'display' => [
            'familyKey' => 'font_display_family',
            'weightKey' => 'font_display_weight',
            'cssVar' => '--font-display',
            'defaultFamily' => 'Archivo Narrow',
            'defaultWeight' => 700,
            'extraWeights' => [600],
        ],
        'cardTitle' => [
            'familyKey' => 'video_card_title_font',
            'weightKey' => 'video_card_title_weight',
            'cssVar' => null, // applied inline by VideoCard.vue, not as a token
            'defaultFamily' => '',
            'defaultWeight' => 600,
            'extraWeights' => [],
        ],
        'cardMeta' => [
            'familyKey' => 'video_card_meta_font',
            'weightKey' => 'video_card_meta_weight',
            'cssVar' => null,
            'defaultFamily' => '',
            'defaultWeight' => 400,
            'extraWeights' => [],
        ],
    ];

    /**
     * Resolved family/weight/stack for one slot.
     *
     * @return array{family:string,weight:int,stack:?string,isDefault:bool}
     */
    public static function slot(string $name): array
    {
        $spec = self::SLOTS[$name] ?? null;

        if ($spec === null) {
            return ['family' => '', 'weight' => 400, 'stack' => null, 'isDefault' => true];
        }

        $chosen = (string) Setting::get($spec['familyKey'], '');
        $isDefault = ! GoogleFonts::exists($chosen);
        $family = $isDefault ? $spec['defaultFamily'] : $chosen;

        // A slot whose default is empty (the card slots) inherits from the
        // display/body token instead of forcing a family of its own.
        if ($family === '') {
            return ['family' => '', 'weight' => (int) $spec['defaultWeight'], 'stack' => null, 'isDefault' => true];
        }

        $weight = GoogleFonts::resolveWeight(
            $family,
            Setting::get($spec['weightKey'], null) ?: $spec['defaultWeight']
        );

        return [
            'family' => $family,
            'weight' => $weight,
            'stack' => GoogleFonts::stack($family),
            'isDefault' => $isDefault,
        ];
    }

    /** Every slot resolved. @return array<string, array> */
    public static function all(): array
    {
        $out = [];

        foreach (array_keys(self::SLOTS) as $name) {
            $out[$name] = self::slot($name);
        }

        return $out;
    }

    /**
     * Older single-purpose font settings that predate the slot system.
     *
     * These were rendered as inline `font-family` but only `site_title_font`
     * ever had a stylesheet loaded for it — so a category or age-gate font
     * picked in the admin silently did nothing, falling back to the body face.
     * Including them here fixes that, and moves the title font off
     * fonts.googleapis.com onto Bunny with everything else.
     */
    public const LEGACY_FAMILY_KEYS = [
        'site_title_font' => [400, 700],
        'category_title_font' => [400, 600],
        'age_font_family' => [400, 600],
    ];

    /**
     * Font families and the weights each one needs, deduplicated across slots —
     * two slots on the same family produce one entry, not two.
     *
     * @return array<string, list<int>>
     */
    public static function requiredFamilies(): array
    {
        $families = [];

        foreach (self::LEGACY_FAMILY_KEYS as $settingKey => $weights) {
            $family = (string) Setting::get($settingKey, '');

            if (! GoogleFonts::exists($family)) {
                continue;
            }

            foreach ($weights as $weight) {
                $resolved = GoogleFonts::resolveWeight($family, $weight);
                $families[$family][$resolved] = $resolved;
            }
        }

        foreach (self::SLOTS as $name => $spec) {
            $slot = self::slot($name);

            if ($slot['family'] === '') {
                continue;
            }

            $weights = array_merge([$slot['weight']], $spec['extraWeights']);

            foreach ($weights as $weight) {
                $resolved = GoogleFonts::resolveWeight($slot['family'], $weight);
                $families[$slot['family']][$resolved] = $resolved;
            }
        }

        return array_map(
            function (array $weights) {
                $weights = array_values($weights);
                sort($weights);

                return $weights;
            },
            $families
        );
    }

    /** The one Bunny Fonts stylesheet that covers every slot. */
    public static function stylesheetUrl(): ?string
    {
        return GoogleFonts::stylesheetUrl(self::requiredFamilies());
    }

    /**
     * `--font-*` declarations for the slots that map to a token, for a CSS rule
     * body. Slots with no cssVar are applied inline by the component that owns
     * them.
     */
    public static function cssVariables(): string
    {
        $lines = [];

        foreach (self::SLOTS as $name => $spec) {
            if ($spec['cssVar'] === null) {
                continue;
            }

            $slot = self::slot($name);

            if ($slot['stack'] !== null) {
                $lines[] = $spec['cssVar'] . ': ' . $slot['stack'] . ';';
            }
        }

        // Default weight for headings, so a chosen weight applies without every
        // heading rule restating it.
        $display = self::slot('display');
        $lines[] = '--font-display-weight: ' . $display['weight'] . ';';

        return implode("\n            ", $lines);
    }

    /**
     * Shape consumed by VideoCard.vue through page.props.theme.videoCard.
     *
     * @return array{titleFont:string,titleWeight:int,metaFont:string,metaWeight:int}
     */
    public static function videoCard(): array
    {
        $title = self::slot('cardTitle');
        $meta = self::slot('cardMeta');

        return [
            'titleFont' => $title['stack'] ?? '',
            'titleWeight' => $title['weight'],
            'metaFont' => $meta['stack'] ?? '',
            'metaWeight' => $meta['weight'],
        ];
    }
}
