<?php

/*
|--------------------------------------------------------------------------
| Channel social links
|--------------------------------------------------------------------------
|
| The platforms a creator may link out to from their channel sidebar, and
| the hosts each one is allowed to point at.
|
| The host allowlist is the security boundary. Profile links are the primary
| spam vector on a tube site, so a link labelled "OnlyFans" must actually
| resolve to onlyfans.com — otherwise the label becomes a way to lend the
| site's credibility to an arbitrary destination.
|
| A null `hosts` means "any host" and must stay reserved for the explicitly
| free-form entries (website / other), which render with the destination
| hostname visible rather than a trusted brand name.
|
| `label` is the display name. `icon` maps to the icon set in
| resources/js/Components/Channel/SocialLinks.vue; platforms with no
| dedicated glyph there fall back to a generic link mark.
|
*/

return [

    'twitter' => [
        'label' => 'X / Twitter',
        'icon' => 'twitter',
        'hosts' => ['twitter.com', 'www.twitter.com', 'x.com', 'www.x.com'],
    ],

    'instagram' => [
        'label' => 'Instagram',
        'icon' => 'instagram',
        'hosts' => ['instagram.com', 'www.instagram.com'],
    ],

    'onlyfans' => [
        'label' => 'OnlyFans',
        'icon' => 'link',
        'hosts' => ['onlyfans.com', 'www.onlyfans.com'],
    ],

    'fansly' => [
        'label' => 'Fansly',
        'icon' => 'link',
        'hosts' => ['fansly.com', 'www.fansly.com'],
    ],

    'reddit' => [
        'label' => 'Reddit',
        'icon' => 'link',
        'hosts' => ['reddit.com', 'www.reddit.com', 'old.reddit.com'],
    ],

    'tiktok' => [
        'label' => 'TikTok',
        'icon' => 'link',
        'hosts' => ['tiktok.com', 'www.tiktok.com'],
    ],

    'youtube' => [
        'label' => 'YouTube',
        'icon' => 'youtube',
        'hosts' => ['youtube.com', 'www.youtube.com', 'youtu.be', 'm.youtube.com'],
    ],

    'telegram' => [
        'label' => 'Telegram',
        'icon' => 'telegram',
        'hosts' => ['t.me', 'telegram.me', 'telegram.org'],
    ],

    'snapchat' => [
        'label' => 'Snapchat',
        'icon' => 'link',
        'hosts' => ['snapchat.com', 'www.snapchat.com'],
    ],

    'linktree' => [
        'label' => 'Links',
        'icon' => 'link',
        'hosts' => ['linktr.ee', 'beacons.ai', 'allmylinks.com', 'linkr.bio'],
    ],

    'amazon' => [
        'label' => 'Wishlist',
        'icon' => 'link',
        'hosts' => ['amazon.com', 'www.amazon.com', 'amzn.to', 'throne.com', 'throne.me'],
    ],

    'website' => [
        'label' => 'Website',
        'icon' => 'globe',
        'hosts' => null,
    ],

];
