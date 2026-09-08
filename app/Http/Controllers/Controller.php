<?php

namespace App\Http\Controllers;

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
}
