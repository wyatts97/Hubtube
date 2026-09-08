<?php

namespace App\Support;

/**
 * Server-side device classification from a User-Agent string.
 *
 * The regex used to live inline in resources/views/app.blade.php, where it
 * picked the desktop or mobile variant of each ad code. Ad statistics need the
 * same answer, and two copies of a UA regex drift — so the single copy lives
 * here and Blade calls it.
 *
 * This is intentionally a coarse three-way split, not a device database. It
 * decides which ad creative to serve and which bucket to count against; a
 * wrong guess costs a slightly mis-sized banner, not correctness.
 */
class DeviceType
{
    public const DESKTOP = 'desktop';
    public const MOBILE = 'mobile';
    public const TABLET = 'tablet';

    /**
     * Tablets are tested first: an iPad's UA contains neither "Mobile" nor
     * "iPhone", but an Android tablet's UA does contain "Android", so testing
     * mobile first would swallow every tablet.
     */
    public static function detect(?string $userAgent): string
    {
        $ua = $userAgent ?? '';

        if ($ua === '') {
            return self::DESKTOP;
        }

        if (preg_match('/iPad|Tablet|PlayBook|Silk|(Android(?!.*Mobile))/i', $ua)) {
            return self::TABLET;
        }

        if (preg_match('/Android|iPhone|iPod|Opera Mini|IEMobile|Mobile|webOS|BlackBerry/i', $ua)) {
            return self::MOBILE;
        }

        return self::DESKTOP;
    }

    /**
     * Whether the mobile ad creative should be preferred.
     *
     * Deliberately NOT `detect() === MOBILE`. The regex this replaced counted
     * iPad and other tablets as mobile, so every tablet visitor has been served
     * the mobile ad code. Narrowing that here would silently change which
     * creative real traffic receives, which is a revenue change disguised as a
     * refactor — so creative selection keeps the original behaviour and only
     * the statistics use the finer three-way split.
     */
    public static function prefersMobileCreative(?string $userAgent): bool
    {
        return in_array(self::detect($userAgent), [self::MOBILE, self::TABLET], true);
    }
}
