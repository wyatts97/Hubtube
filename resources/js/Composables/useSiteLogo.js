import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTheme } from '@/Composables/useTheme';

/**
 * Resolves which logo file to show for the active theme.
 *
 * Artwork drawn for a dark ground has light lettering that disappears on white,
 * so each logo slot is a pair: a primary file used in dark mode and as the
 * fallback, plus an optional light-mode override.
 *
 * `site_logo` is the DARK one, not the light one. The site was dark-only before
 * light mode existed, so every install already has dark-ground artwork stored
 * under that key — treating it as the light logo would silently break every
 * existing header.
 *
 * Header, login dialog and footer all read from here so the three cannot drift.
 */
export function useSiteLogo() {
    const page = usePage();
    const { isDark } = useTheme();

    const theme = computed(() => page.props.theme || {});

    /** Pick the light override only in light mode, else fall back to the dark file. */
    const pick = (dark, light) => (!isDark.value && light ? light : dark);

    const siteLogo = computed(() => pick(theme.value.site_logo, theme.value.site_logo_light));

    /**
     * The footer can either mirror the site logo or carry its own pair.
     *
     * Mirroring is resolved HERE rather than by copying site_logo into
     * footer_logo_url when the settings form is saved, which is what used to
     * happen: a copied path is a snapshot of one file, so it could never follow
     * the theme, and it went stale the moment the site logo was replaced.
     */
    const footerLogo = computed(() => {
        if (theme.value.footer_logo_match_site) {
            return siteLogo.value;
        }

        return pick(theme.value.footer_logo_url, theme.value.footer_logo_url_light);
    });

    return { siteLogo, footerLogo };
}
