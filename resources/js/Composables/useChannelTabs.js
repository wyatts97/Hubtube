import { computed, unref } from 'vue';
import { useI18n } from '@/Composables/useI18n';

/**
 * The channel tab strip, defined once.
 *
 * This block used to be duplicated verbatim in all six Channel pages, with
 * two bugs baked into the copies:
 *
 *  - hrefs were raw template strings, so they bypassed localizedUrl() and a
 *    visitor on /es/channel/foo lost the locale prefix on every tab click.
 *  - `active` was hardcoded per file, and Show.vue set it on none of them, so
 *    the Videos tab never highlighted on the canonical channel URL.
 *
 * `activeTab` now comes from the server (ChannelController::renderTab), so the
 * highlighted tab and the rendered page can't drift apart.
 *
 * Accepts refs or plain values.
 */
export function useChannelTabs(channel, activeTab, visibility = {}) {
    const { t, localizedUrl } = useI18n();

    return computed(() => {
        const username = unref(channel)?.username;
        if (!username) return [];

        const base = `/channel/${username}`;
        const current = unref(activeTab);
        const showLiked = unref(visibility.liked) ?? false;
        const showHistory = unref(visibility.history) ?? false;

        const items = [
            { key: 'videos', name: t('channel.videos'), href: localizedUrl(base) },
            { key: 'playlists', name: t('channel.playlists'), href: localizedUrl(`${base}/playlists`) },
        ];

        if (showLiked) {
            items.push({ key: 'liked', name: t('channel.liked_videos'), href: localizedUrl(`${base}/liked`) });
        }

        if (showHistory) {
            items.push({ key: 'history', name: t('channel.recently_watched'), href: localizedUrl(`${base}/history`) });
        }

        items.push({ key: 'about', name: t('channel.about'), href: localizedUrl(`${base}/about`) });

        return items.map((item) => ({ ...item, active: item.key === current }));
    });
}
