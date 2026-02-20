<?php

namespace App\Services;

use App\Models\Setting;

class AdminNavConfig
{
    protected array $itemConfig  = [];
    protected array $groupConfig = [];
    protected bool  $loaded      = false;

    protected function load(): void
    {
        if ($this->loaded) return;
        $this->loaded = true;

        try {
            $raw = Setting::get('admin_nav_config', null);
            if (!$raw) return;
            $config = json_decode($raw, true);
            if (!is_array($config)) return;

            foreach ($config as $group) {
                $gKey = $group['key'];
                $this->groupConfig[$gKey] = [
                    'sort'      => (int)  ($group['sort']      ?? 99),
                    'hidden'    => (bool) ($group['hidden']    ?? false),
                    'collapsed' => (bool) ($group['collapsed'] ?? false),
                    'label'     => $group['label'] ?? $gKey,
                ];
                foreach ($group['items'] ?? [] as $item) {
                    $this->itemConfig[$item['key']] = [
                        'hidden' => (bool) ($item['hidden'] ?? false),
                        'sort'   => (int)  ($item['sort']   ?? 99),
                        'group'  => $gKey,
                    ];
                }
            }
        } catch (\Throwable) {}
    }

    public function isItemHidden(string $class): bool
    {
        $this->load();
        return $this->itemConfig[$class]['hidden'] ?? false;
    }

    public function itemSort(string $class): ?int
    {
        $this->load();
        return isset($this->itemConfig[$class]) ? $this->itemConfig[$class]['sort'] : null;
    }

    public function isGroupHidden(string $groupKey): bool
    {
        $this->load();
        return $this->groupConfig[$groupKey]['hidden'] ?? false;
    }

    public function groupSort(string $groupKey): ?int
    {
        $this->load();
        return isset($this->groupConfig[$groupKey]) ? $this->groupConfig[$groupKey]['sort'] : null;
    }

    public function isGroupCollapsed(string $groupKey): bool
    {
        $this->load();
        return $this->groupConfig[$groupKey]['collapsed'] ?? false;
    }
}
