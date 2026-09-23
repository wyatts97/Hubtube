<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SponsoredCard;
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
     * The sponsored-card props every listing grid needs.
     *
     * Sponsored cards are the only in-grid ad (image, video or pasted HTML), so
     * one helper hands out both the cards and the shared spacing. Ad-free
     * viewers get an empty list rather than a flag, so no creative or ad code
     * reaches their page.
     *
     * @param  array<int, string>|null  $types  Restrict to these creative types.
     */
    protected function sponsoredCardProps(string $page, ?int $categoryId = null, ?array $types = null): array
    {
        return [
            'sponsoredCards' => $this->shouldSuppressAds()
                ? []
                : SponsoredCard::getForPage($page, $this->adTargetRole(), $categoryId, types: $types),
            'sponsoredFrequency' => (int) Setting::get('sponsored_card_frequency', 8),
        ];
    }
}
