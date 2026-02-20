<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

use App\Filament\Concerns\HasCustomizableNavigation;

class AdminNavCustomizer extends Page
{
    use HasCustomizableNavigation;
    protected static ?string $navigationIcon  = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Admin Customization';
    protected static ?string $navigationGroup = 'Tools';
    protected static ?int    $navigationSort  = 1;
    protected static string  $view            = 'filament.pages.admin-nav-customizer';

    // The full nav config: array of groups, each with items
    public array $groups = [];

    // Default group/item definitions (source of truth for labels/icons)
    public static function defaultNav(): array
    {
        return [
            [
                'key'       => 'Content',
                'label'     => 'Content',
                'collapsed' => false,
                'hidden'    => false,
                'sort'      => 1,
                'items'     => [
                    ['key' => 'App\\Filament\\Resources\\VideoResource',        'label' => 'Videos',           'icon' => 'heroicon-o-video-camera',           'hidden' => false, 'sort' => 1],
                    ['key' => 'App\\Filament\\Resources\\ImageResource',        'label' => 'Images',           'icon' => 'heroicon-o-photo',                  'hidden' => false, 'sort' => 2],
                    ['key' => 'App\\Filament\\Resources\\CategoryResource',     'label' => 'Categories',       'icon' => 'heroicon-o-tag',                    'hidden' => false, 'sort' => 3],
                    ['key' => 'App\\Filament\\Resources\\GalleryResource',      'label' => 'Galleries',        'icon' => 'heroicon-o-rectangle-group',        'hidden' => false, 'sort' => 4],
                    ['key' => 'App\\Filament\\Resources\\CommentResource',      'label' => 'Comments',         'icon' => 'heroicon-o-chat-bubble-left-right', 'hidden' => false, 'sort' => 5],
                    ['key' => 'App\\Filament\\Resources\\ChannelResource',      'label' => 'Channels',         'icon' => 'heroicon-o-tv',                     'hidden' => false, 'sort' => 6],
                    ['key' => 'App\\Filament\\Resources\\LiveStreamResource',   'label' => 'Live Streams',     'icon' => 'heroicon-o-signal',                 'hidden' => false, 'sort' => 7],
                ],
            ],
            [
                'key'       => 'Users & Messages',
                'label'     => 'Users & Messages',
                'collapsed' => false,
                'hidden'    => false,
                'sort'      => 2,
                'items'     => [
                    ['key' => 'App\\Filament\\Resources\\UserResource',           'label' => 'Users',            'icon' => 'heroicon-o-users',    'hidden' => false, 'sort' => 1],
                    ['key' => 'App\\Filament\\Resources\\ContactMessageResource', 'label' => 'Contact Messages', 'icon' => 'heroicon-o-envelope',  'hidden' => false, 'sort' => 2],
                ],
            ],
            [
                'key'       => 'Monetization',
                'label'     => 'Monetization',
                'collapsed' => false,
                'hidden'    => false,
                'sort'      => 3,
                'items'     => [
                    ['key' => 'App\\Filament\\Resources\\WalletTransactionResource', 'label' => 'Wallet Transactions', 'icon' => 'heroicon-o-banknotes',    'hidden' => false, 'sort' => 1],
                    ['key' => 'App\\Filament\\Resources\\WithdrawalRequestResource', 'label' => 'Withdrawals',         'icon' => 'heroicon-o-arrow-up-tray', 'hidden' => false, 'sort' => 2],
                    ['key' => 'App\\Filament\\Resources\\GiftResource',              'label' => 'Gifts',               'icon' => 'heroicon-o-gift',          'hidden' => false, 'sort' => 3],
                    ['key' => 'App\\Filament\\Pages\\PaymentSettings',               'label' => 'Payment Gateways',    'icon' => 'heroicon-o-credit-card',   'hidden' => false, 'sort' => 4],
                ],
            ],
            [
                'key'       => 'Appearance',
                'label'     => 'Appearance',
                'collapsed' => false,
                'hidden'    => false,
                'sort'      => 4,
                'items'     => [
                    ['key' => 'App\\Filament\\Pages\\ThemeSettings',                 'label' => 'Theme & Appearance', 'icon' => 'heroicon-o-paint-brush',      'hidden' => false, 'sort' => 1],
                    ['key' => 'App\\Filament\\Pages\\AdSettings',                    'label' => 'Ad Settings',        'icon' => 'heroicon-o-currency-dollar',  'hidden' => false, 'sort' => 2],
                    ['key' => 'App\\Filament\\Resources\\VideoAdResource',           'label' => 'Ad Creatives',       'icon' => 'heroicon-o-film',             'hidden' => false, 'sort' => 3],
                    ['key' => 'App\\Filament\\Resources\\MenuItemResource',          'label' => 'Menu Builder',       'icon' => 'heroicon-o-bars-3',           'hidden' => false, 'sort' => 4],
                    ['key' => 'App\\Filament\\Resources\\SponsoredCardResource',     'label' => 'Sponsored Cards',    'icon' => 'heroicon-o-megaphone',        'hidden' => false, 'sort' => 5],
                    ['key' => 'App\\Filament\\Pages\\SeoSettings',                   'label' => 'SEO Settings',       'icon' => 'heroicon-o-magnifying-glass', 'hidden' => false, 'sort' => 6],
                    ['key' => 'App\\Filament\\Pages\\LanguageSettings',              'label' => 'Languages',          'icon' => 'heroicon-o-language',         'hidden' => false, 'sort' => 7],
                    ['key' => 'App\\Filament\\Pages\\MediaLibrary',                  'label' => 'Media Library',      'icon' => 'heroicon-o-photo',            'hidden' => false, 'sort' => 8],
                ],
            ],
            [
                'key'       => 'Integrations',
                'label'     => 'Integrations',
                'collapsed' => false,
                'hidden'    => false,
                'sort'      => 5,
                'items'     => [
                    ['key' => 'App\\Filament\\Pages\\IntegrationSettings',   'label' => 'Services & Email', 'icon' => 'heroicon-o-puzzle-piece', 'hidden' => false, 'sort' => 1],
                    ['key' => 'App\\Filament\\Pages\\SocialNetworkSettings', 'label' => 'Social Networks',  'icon' => 'heroicon-o-share',        'hidden' => false, 'sort' => 2],
                ],
            ],
            [
                'key'       => 'System',
                'label'     => 'System',
                'collapsed' => true,
                'hidden'    => false,
                'sort'      => 6,
                'items'     => [
                    ['key' => 'App\\Filament\\Pages\\SiteSettings',    'label' => 'Site Settings',  'icon' => 'heroicon-o-cog-6-tooth',          'hidden' => false, 'sort' => 1],
                    ['key' => 'App\\Filament\\Pages\\StorageSettings', 'label' => 'Storage & CDN',  'icon' => 'heroicon-o-cloud',                'hidden' => false, 'sort' => 2],
                    ['key' => 'App\\Filament\\Pages\\LiveStreamSettings', 'label' => 'Live Streaming', 'icon' => 'heroicon-o-video-camera',      'hidden' => false, 'sort' => 3],
                    ['key' => 'App\\Filament\\Pages\\PwaSettings',     'label' => 'PWA & Push',     'icon' => 'heroicon-o-device-phone-mobile',  'hidden' => false, 'sort' => 4],
                    ['key' => 'App\\Filament\\Resources\\PageResource', 'label' => 'Legal Pages',   'icon' => 'heroicon-o-document-text',        'hidden' => false, 'sort' => 5],
                    ['key' => 'App\\Filament\\Pages\\FailedJobs',      'label' => 'Failed Jobs',    'icon' => 'heroicon-o-exclamation-triangle',  'hidden' => false, 'sort' => 6],
                ],
            ],
            [
                'key'       => 'Tools',
                'label'     => 'Tools',
                'collapsed' => true,
                'hidden'    => false,
                'sort'      => 7,
                'items'     => [
                    ['key' => 'App\\Filament\\Pages\\AdminNavCustomizer',    'label' => 'Admin Customization', 'icon' => 'heroicon-o-adjustments-horizontal', 'hidden' => false, 'sort' => 1],
                    ['key' => 'App\\Filament\\Resources\\ActivityLogResource', 'label' => 'Logs',             'icon' => 'heroicon-o-clipboard-document-list', 'hidden' => false, 'sort' => 2],
                    ['key' => 'App\\Filament\\Pages\\ArchiveImporter',       'label' => 'Archive Import',     'icon' => 'heroicon-o-folder-open',            'hidden' => false, 'sort' => 3],
                    ['key' => 'App\\Filament\\Pages\\WordPressImporter',     'label' => 'WP Import',          'icon' => 'heroicon-o-arrow-down-tray',        'hidden' => false, 'sort' => 4],
                    ['key' => 'App\\Filament\\Pages\\WordPressUserImporter', 'label' => 'WP User Import',     'icon' => 'heroicon-o-users',                  'hidden' => false, 'sort' => 5],
                    ['key' => 'App\\Filament\\Pages\\BunnyStreamMigrator',   'label' => 'Bunny Migration',    'icon' => 'heroicon-o-cloud-arrow-down',        'hidden' => false, 'sort' => 6],
                ],
            ],
        ];
    }

    public function mount(): void
    {
        $saved = Setting::get('admin_nav_config', null);
        if ($saved) {
            $decoded = json_decode($saved, true);
            $this->groups = is_array($decoded) ? $this->mergeWithDefaults($decoded) : static::defaultNav();
        } else {
            $this->groups = static::defaultNav();
        }
    }

    // Merge saved config with defaults so new items added in code appear automatically
    protected function mergeWithDefaults(array $saved): array
    {
        $defaults   = static::defaultNav();
        $savedByKey = collect($saved)->keyBy('key')->toArray();

        foreach ($defaults as &$group) {
            if (isset($savedByKey[$group['key']])) {
                $sg = $savedByKey[$group['key']];
                $group['collapsed'] = $sg['collapsed'] ?? $group['collapsed'];
                $group['hidden']    = $sg['hidden']    ?? false;
                $group['sort']      = $sg['sort']       ?? $group['sort'];
                $group['label']     = $sg['label']      ?? $group['label'];

                $savedItems = collect($sg['items'] ?? [])->keyBy('key')->toArray();
                foreach ($group['items'] as &$item) {
                    if (isset($savedItems[$item['key']])) {
                        $si = $savedItems[$item['key']];
                        $item['hidden'] = $si['hidden'] ?? false;
                        $item['sort']   = $si['sort']   ?? $item['sort'];
                        $item['label']  = $si['label']  ?? $item['label'];
                    }
                }
                unset($item);
                usort($group['items'], fn ($a, $b) => $a['sort'] <=> $b['sort']);
            }
        }
        unset($group);

        usort($defaults, fn ($a, $b) => $a['sort'] <=> $b['sort']);
        return $defaults;
    }

    public function moveGroupUp(int $index): void
    {
        if ($index <= 0) return;
        [$this->groups[$index - 1], $this->groups[$index]] = [$this->groups[$index], $this->groups[$index - 1]];
        $this->reindexGroupSorts();
    }

    public function moveGroupDown(int $index): void
    {
        if ($index >= count($this->groups) - 1) return;
        [$this->groups[$index], $this->groups[$index + 1]] = [$this->groups[$index + 1], $this->groups[$index]];
        $this->reindexGroupSorts();
    }

    public function toggleGroupHidden(int $index): void
    {
        $this->groups[$index]['hidden'] = !($this->groups[$index]['hidden'] ?? false);
    }

    public function toggleGroupCollapsed(int $index): void
    {
        $this->groups[$index]['collapsed'] = !($this->groups[$index]['collapsed'] ?? false);
    }

    public function moveItemUp(int $groupIndex, int $itemIndex): void
    {
        if ($itemIndex <= 0) return;
        $items = $this->groups[$groupIndex]['items'];
        [$items[$itemIndex - 1], $items[$itemIndex]] = [$items[$itemIndex], $items[$itemIndex - 1]];
        $this->groups[$groupIndex]['items'] = $items;
        $this->reindexItemSorts($groupIndex);
    }

    public function moveItemDown(int $groupIndex, int $itemIndex): void
    {
        $items = $this->groups[$groupIndex]['items'];
        if ($itemIndex >= count($items) - 1) return;
        [$items[$itemIndex], $items[$itemIndex + 1]] = [$items[$itemIndex + 1], $items[$itemIndex]];
        $this->groups[$groupIndex]['items'] = $items;
        $this->reindexItemSorts($groupIndex);
    }

    public function toggleItemHidden(int $groupIndex, int $itemIndex): void
    {
        $this->groups[$groupIndex]['items'][$itemIndex]['hidden'] =
            !($this->groups[$groupIndex]['items'][$itemIndex]['hidden'] ?? false);
    }

    protected function reindexGroupSorts(): void
    {
        foreach ($this->groups as $i => &$group) {
            $group['sort'] = $i + 1;
        }
        unset($group);
    }

    protected function reindexItemSorts(int $groupIndex): void
    {
        foreach ($this->groups[$groupIndex]['items'] as $i => &$item) {
            $item['sort'] = $i + 1;
        }
        unset($item);
    }

    public function save(): void
    {
        Setting::set('admin_nav_config', json_encode($this->groups));

        Notification::make()
            ->title('Navigation layout saved â€” reload the page to see changes')
            ->success()
            ->send();
    }

    public function resetToDefaults(): void
    {
        $this->groups = static::defaultNav();
        Setting::set('admin_nav_config', json_encode($this->groups));

        Notification::make()
            ->title('Navigation reset to defaults')
            ->success()
            ->send();
    }
}
