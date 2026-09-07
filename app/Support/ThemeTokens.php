<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Single source of truth for the public site's colour tokens.
 *
 * Before this class existed the palette was declared in four places that had to
 * be edited in lockstep — the `@theme` and `:root` blocks in resources/css/app.css,
 * an inline <style> in app.blade.php, and hardcoded fallbacks in useTheme.js.
 * They drifted. Now app.blade.php (first paint), HandleInertiaRequests (the
 * Inertia `theme` prop consumed by useTheme.js) and the Filament ThemeSettings
 * page all read from here, so a token can only be defined once.
 *
 * app.css still declares the dark values inside `@theme`, because Tailwind v4
 * needs them at build time to generate the `bg-bg-primary` / `text-text-primary`
 * utilities. Those are shape declarations; the runtime values below win.
 */
class ThemeTokens
{
    /**
     * Dark palette.
     *
     * Every neutral is a TRUE grey (R = G = B). The first pass carried a blue
     * channel 2-10 points above red and green, which read as a slate/blue cast
     * — most visibly on large flat fills like the search field, which looked
     * blue against surroundings that were meant to match it. Keep these
     * channels equal when adjusting; a couple of points of drift is enough to
     * tint a whole surface.
     *
     * Deliberately near-black rather than pure black: thumbnails carry their own
     * black letterboxing, and a pure-black ground makes them bleed into it with
     * no visible card edge.
     *
     * bgInput is one step LIGHTER than bgSecondary on purpose — the search field
     * sits on the header, and matching them made the field read as a hole rather
     * than an input.
     */
    public const DARK = [
        'bgPrimary'      => '#0d0d0d',
        'bgSecondary'    => '#161616',
        'bgCard'         => '#1c1c1c',
        'bgElevated'     => '#242424',
        'bgHover'        => '#2c2c2c',
        'bgInput'        => '#202020',
        'accent'         => '#e11d34',
        'accentHover'    => '#f43553',
        'accentText'     => '#f1556a',
        'accentSubtle'   => 'rgba(225, 29, 52, 0.14)',
        'accentContrast' => '#ffffff',
        'textPrimary'    => '#f4f4f4',
        'textSecondary'  => '#a6a6a6',
        'textMuted'      => '#8a8a8a',
        'border'         => '#2e2e2e',
        'borderSubtle'   => '#212121',
        'borderStrong'   => '#3d3d3d',
        'success'        => '#3ecf70',
        'overlay'        => 'rgba(0, 0, 0, 0.78)',
    ];

    /**
     * Light palette.
     *
     * Adult thumbnails are overwhelmingly skin tones under low-key lighting. On
     * a pure #ffffff ground they float and wash out, so the page sits on a warm
     * off-white and cards are pure white — the card lifts off the ground instead
     * of dissolving into it. Borders also carry more structural weight here than
     * in dark mode; that is what keeps a dense grid legible.
     *
     * Two reds, deliberately. `accent` is deep enough to double as text on white
     * (6.3:1), so here accentText matches it; in dark mode the two diverge —
     * see DARK, where the fill red would fail AA as text and accentText is a
     * lighter tint. Anything rendering red *text* must use --color-accent-text,
     * and anything filling a surface must use --color-accent.
     */
    public const LIGHT = [
        'bgPrimary'      => '#faf8f7',
        'bgSecondary'    => '#ffffff',
        'bgCard'         => '#ffffff',
        'bgElevated'     => '#ffffff',
        'bgHover'        => '#f1eeec',
        'bgInput'        => '#f4f1ef',
        'accent'         => '#c00d26',
        'accentHover'    => '#a20a1f',
        'accentText'     => '#c00d26',
        'accentSubtle'   => 'rgba(192, 13, 38, 0.09)',
        'accentContrast' => '#ffffff',
        'textPrimary'    => '#17151a',
        'textSecondary'  => '#5c5761',
        'textMuted'      => '#7b727e',
        'border'         => '#e3ded9',
        'borderSubtle'   => '#efeae6',
        'borderStrong'   => '#cdc5bd',
        'success'        => '#1a8f45',
        'overlay'        => 'rgba(0, 0, 0, 0.72)',
    ];

    /** Maps a palette key to the CSS custom property it is emitted as. */
    public const CSS_VARS = [
        'bgPrimary'      => '--color-bg-primary',
        'bgSecondary'    => '--color-bg-secondary',
        'bgCard'         => '--color-bg-card',
        'bgElevated'     => '--color-bg-elevated',
        'bgHover'        => '--color-bg-hover',
        'bgInput'        => '--color-bg-input',
        'accent'         => '--color-accent',
        'accentHover'    => '--color-accent-hover',
        'accentText'     => '--color-accent-text',
        'accentSubtle'   => '--color-accent-subtle',
        'accentContrast' => '--color-accent-contrast',
        'textPrimary'    => '--color-text-primary',
        'textSecondary'  => '--color-text-secondary',
        'textMuted'      => '--color-text-muted',
        'border'         => '--color-border',
        'borderSubtle'   => '--color-border-subtle',
        'borderStrong'   => '--color-border-strong',
        'success'        => '--color-success',
        'overlay'        => '--color-overlay',
    ];

    /**
     * Only these keys are exposed as admin colour pickers. The rest are derived,
     * so an operator who swaps the accent still gets a coherent ramp rather than
     * eighteen fields to keep in sync by hand.
     */
    public const EDITABLE = [
        'bgPrimary', 'bgSecondary', 'bgCard', 'accent', 'textPrimary', 'textSecondary', 'border',
    ];

    /**
     * Settings-table suffix for each editable key. Mostly the snake_case of the
     * key, but `accent` and `border` shipped as `*_accent_color` / `*_border_color`
     * and existing installs have rows under those names — renaming them would
     * silently reset every operator's palette on upgrade.
     */
    public const SETTING_SUFFIX = [
        'accent' => 'accent_color',
        'border' => 'border_color',
    ];

    public static function defaults(string $mode): array
    {
        return $mode === 'light' ? self::LIGHT : self::DARK;
    }

    /**
     * The palette for a mode, with admin overrides from the settings table laid
     * over the defaults. Setting keys are dark_bg_primary, light_accent, etc.
     */
    public static function palette(string $mode): array
    {
        $defaults = self::defaults($mode);
        $palette = $defaults;

        foreach (self::EDITABLE as $key) {
            $value = Setting::get(self::settingKey($mode, $key), null);

            if (is_string($value) && $value !== '') {
                $palette[$key] = $value;
            }
        }

        // Keep derived tokens consistent with an overridden accent, so a custom
        // brand colour doesn't leave hover and subtle states pointing at our
        // default red.
        if ($palette['accent'] !== $defaults['accent']) {
            $palette['accentHover'] = self::shift($palette['accent'], $mode === 'light' ? -0.14 : 0.12);
            $palette['accentSubtle'] = self::alpha($palette['accent'], $mode === 'light' ? 0.09 : 0.14);
            // Text needs more contrast than a fill does. In dark mode that means
            // lightening the brand colour; in light mode, darkening it. Without
            // this split an operator's accent fails WCAG AA as link text.
            $palette['accentText'] = self::shift($palette['accent'], $mode === 'light' ? -0.18 : 0.22);
        }

        return $palette;
    }

    /** The palette as `--color-*: value;` declarations, for a CSS rule body. */
    public static function cssVariables(string $mode): string
    {
        $lines = [];

        foreach (self::palette($mode) as $key => $value) {
            if (isset(self::CSS_VARS[$key])) {
                $lines[] = self::CSS_VARS[$key] . ': ' . $value . ';';
            }
        }

        // Consumed by NProgress in app.css. Kept here so the loading bar tracks
        // the accent automatically instead of needing its own fallback.
        $bar = Setting::get('progress_bar_color', '');
        $lines[] = '--nprogress-color: ' . ($bar !== '' ? $bar : 'var(--color-accent)') . ';';

        return implode("\n            ", $lines);
    }

    /** 'dark' | 'light' | 'user' — see useTheme.js for how 'user' resolves. */
    public static function mode(): string
    {
        $mode = Setting::get('theme_mode', 'user');

        return in_array($mode, ['dark', 'light', 'user'], true) ? $mode : 'user';
    }

    /**
     * Which palette the server renders for first paint.
     *
     * A signed-in user's saved choice is honoured here rather than left to the
     * inline script, so someone who picked light gets it server-rendered on a
     * new device where localStorage is still empty — no flash, no wrong-theme
     * first paint. Anonymous visitors fall through to dark and the inline script
     * upgrades them from localStorage before paint if they had chosen light.
     *
     * Defaulting an adult site to light for someone who never asked would be a
     * bad surprise, so 'user' resolves to dark and never to prefers-color-scheme.
     */
    public static function defaultMode(): string
    {
        $mode = self::mode();

        if ($mode !== 'user') {
            return $mode;
        }

        return self::savedUserTheme() === 'light' ? 'light' : 'dark';
    }

    /**
     * The signed-in user's stored theme, or null.
     *
     * Guarded because this is also called from the standalone error and
     * maintenance views, which can render before the session or database is
     * available — a theme lookup must never be the reason an error page 500s.
     */
    private static function savedUserTheme(): ?string
    {
        try {
            $theme = auth()->user()?->settings['theme'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }

        return in_array($theme, ['light', 'dark'], true) ? $theme : null;
    }

    /** Settings-table key for one editable colour, e.g. ('dark', 'accent') => dark_accent_color. */
    public static function settingKey(string $mode, string $key): string
    {
        return $mode . '_' . (self::SETTING_SUFFIX[$key] ?? self::snake($key));
    }

    /**
     * True when the server already knows the correct theme and the pre-paint
     * script must leave it alone — either the admin pinned the site to one
     * theme, or a signed-in user has a saved choice. Otherwise the visitor is
     * anonymous and localStorage is the only place their choice lives.
     */
    public static function authoritativeMode(): bool
    {
        if (self::mode() !== 'user') {
            return true;
        }

        return self::savedUserTheme() !== null;
    }

    private static function snake(string $key): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $key));
    }

    /** Lighten (positive amount) or darken (negative) a hex colour by a ratio. */
    private static function shift(string $hex, float $amount): string
    {
        [$r, $g, $b] = self::rgb($hex);

        $adjust = function (int $c) use ($amount): int {
            $c = $amount >= 0
                ? $c + (255 - $c) * $amount
                : $c * (1 + $amount);

            return (int) max(0, min(255, round($c)));
        };

        return sprintf('#%02x%02x%02x', $adjust($r), $adjust($g), $adjust($b));
    }

    private static function alpha(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::rgb($hex);

        return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, $alpha);
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [225, 29, 52];
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
