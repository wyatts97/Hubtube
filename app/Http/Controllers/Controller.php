<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\AdService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Ad gating helpers.
     *
     * These live on the base controller rather than in each ad-serving
     * controller because the previous per-controller copies were the reason
     * two surfaces shipped ungated: a new ad prop is easy to add without
     * noticing that the local helper exists. Inheriting them means any
     * controller can gate correctly without first copying anything.
     */
    protected function shouldSuppressAds(): bool
    {
        return app(AdService::class)->shouldSuppress(auth()->user());
    }

    /** Targeting role for the current viewer: admin > pro > default > guest. */
    protected function adTargetRole(): string
    {
        return app(AdService::class)->resolveRole(auth()->user());
    }

    /**
     * The in-grid ad variants an admin has configured, filtered to a category.
     *
     * Lives here rather than on the two controllers that used to own identical
     * copies. Every listing page that shows a video grid needs it, and the
     * duplicated version had already drifted: neither copy honoured the legacy
     * single-code settings that AdSettings still reads, so a site upgraded from
     * before multi-variant support saw its ad code in the admin form and never
     * on the page.
     */
    protected function buildGridAdVariants(?int $categoryId = null): array
    {
        $all = Setting::getAll();
        $s = fn (string $key, mixed $default = null) => $all[$key] ?? $default;

        $count = (int) $s('video_grid_ad_count', 1);
        $variants = [];

        for ($n = 1; $n <= max(1, $count); $n++) {
            // Variant 1 falls back to the pre-multi-variant keys, matching how
            // AdSettings populates its form.
            $code = (string) $s("video_grid_ad_{$n}_code", '');
            if ($n === 1 && $code === '') {
                $code = (string) $s('video_grid_ad_code', '');
            }
            if ($code === '') {
                continue;
            }

            $cats = json_decode((string) $s("video_grid_ad_{$n}_categories", '[]'), true) ?? [];
            if ($categoryId !== null && ! empty($cats) && ! in_array($categoryId, $cats)) {
                continue;
            }

            $mobileCode = (string) $s("video_grid_ad_{$n}_mobile_code", '');
            if ($n === 1 && $mobileCode === '') {
                $mobileCode = (string) $s('video_grid_ad_mobile_code', '');
            }

            $variants[] = [
                'code' => $code,
                'mobileCode' => $mobileCode ?: $code,
            ];
        }

        return $variants;
    }

    /**
     * The standard grid-ad prop block, or a disabled stub for ad-free viewers.
     *
     * Every listing page needs the identical shape; handing it out from one
     * place is what stops the next page from inventing a fifth variation.
     */
    protected function gridAdSettings(?int $categoryId = null): array
    {
        if ($this->shouldSuppressAds()) {
            return ['videoGridEnabled' => false];
        }

        return [
            'videoGridEnabled' => (bool) Setting::get('video_grid_ad_enabled', false),
            'videoGridAds' => $this->buildGridAdVariants($categoryId),
            'videoGridFrequency' => (int) Setting::get('video_grid_ad_frequency', 8),
        ];
    }
}
