<script setup>
/**
 * Outbound creator links.
 *
 * Two rules here are load-bearing and should not be relaxed:
 *
 *  - Every anchor carries rel="nofollow noopener noreferrer ugc". On an adult
 *    tube, profile links are the primary spam vector; nofollow/ugc stop the
 *    site passing link equity to arbitrary user-supplied destinations, and
 *    noopener prevents the destination reaching back through window.opener.
 *  - The visible text is the platform label plus the hostname, never the raw
 *    URL. Rendering attacker-controlled text as the link label invites
 *    spoofing ("onlyfans.com/real" pointing anywhere).
 *
 * URLs are validated server-side against a per-platform host allowlist in
 * config/social_links.php; this component assumes that has already happened.
 */
import { computed } from 'vue';
import { Globe, Instagram, Link2, Send, Twitter, Youtube } from 'lucide-vue-next';

const props = defineProps({
    links: { type: Array, default: () => [] },
});

// lucide has no OnlyFans/Fansly/Reddit/TikTok marks; those fall back to a
// generic link glyph rather than borrowing an unrelated brand icon.
const ICONS = {
    twitter: Twitter,
    instagram: Instagram,
    youtube: Youtube,
    telegram: Send,
    website: Globe,
};

const LABELS = {
    twitter: 'X / Twitter',
    instagram: 'Instagram',
    youtube: 'YouTube',
    telegram: 'Telegram',
    reddit: 'Reddit',
    tiktok: 'TikTok',
    onlyfans: 'OnlyFans',
    fansly: 'Fansly',
    linktree: 'Linktree',
    website: 'Website',
};

const items = computed(() =>
    (props.links || [])
        .filter((link) => link && typeof link.url === 'string')
        .map((link) => {
            let hostname = '';
            try {
                hostname = new URL(link.url).hostname.replace(/^www\./, '');
            } catch {
                // Server-side validation should make this unreachable; skip
                // rather than render a link we cannot parse.
                return null;
            }

            const platform = link.platform || 'website';
            const isFreeform = platform === 'website' || platform === 'other';

            return {
                url: link.url,
                hostname,
                icon: ICONS[platform] || Link2,
                // A custom label is only honoured for free-form entries, so a
                // link can't present itself as "Twitter" while pointing
                // somewhere else.
                label: (isFreeform && link.label) || LABELS[platform] || hostname,
            };
        })
        .filter(Boolean),
);
</script>

<template>
    <ul v-if="items.length" class="space-y-2">
        <li v-for="item in items" :key="item.url">
            <a
                :href="item.url"
                target="_blank"
                rel="nofollow noopener noreferrer ugc"
                class="group flex items-center gap-2.5 text-sm text-text-secondary hover:text-text-primary transition-colors"
            >
                <component :is="item.icon" class="w-4 h-4 shrink-0 text-text-muted group-hover:text-accent" />
                <span class="font-medium">{{ item.label }}</span>
                <span class="truncate text-xs text-text-muted">{{ item.hostname }}</span>
            </a>
        </li>
    </ul>
</template>
