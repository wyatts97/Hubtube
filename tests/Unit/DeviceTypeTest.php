<?php

namespace Tests\Unit;

use App\Support\DeviceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeviceTypeTest extends TestCase
{
    public static function userAgents(): array
    {
        return [
            'windows chrome' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36', DeviceType::DESKTOP],
            'macos safari' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', DeviceType::DESKTOP],
            'linux firefox' => ['Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0', DeviceType::DESKTOP],
            'iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1', DeviceType::MOBILE],
            'android phone' => ['Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Mobile Safari/537.36', DeviceType::MOBILE],
            'ipad' => ['Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/604.1', DeviceType::TABLET],
            'android tablet' => ['Mozilla/5.0 (Linux; Android 13; SM-X700) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36', DeviceType::TABLET],
            'kindle silk' => ['Mozilla/5.0 (Linux; U; Silk/3.0) AppleWebKit/537.36 (KHTML, like Gecko) Safari/537.36', DeviceType::TABLET],
            'empty' => ['', DeviceType::DESKTOP],
        ];
    }

    #[DataProvider('userAgents')]
    public function test_it_classifies_user_agents(string $ua, string $expected): void
    {
        $this->assertSame($expected, DeviceType::detect($ua));
    }

    public function test_a_null_user_agent_is_desktop(): void
    {
        $this->assertSame(DeviceType::DESKTOP, DeviceType::detect(null));
    }

    /**
     * Creative selection must keep treating tablets as mobile.
     *
     * The regex this helper replaced listed iPad alongside the phones, so every
     * tablet visitor has been served the mobile ad code. Narrowing that would
     * change which creative real traffic receives — a revenue change disguised
     * as a refactor — so only the statistics use the finer split.
     */
    public function test_tablets_still_receive_the_mobile_creative(): void
    {
        $ipad = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/604.1';

        $this->assertSame(DeviceType::TABLET, DeviceType::detect($ipad));
        $this->assertTrue(DeviceType::prefersMobileCreative($ipad));
    }

    public function test_desktop_does_not_receive_the_mobile_creative(): void
    {
        $this->assertFalse(DeviceType::prefersMobileCreative(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36'
        ));
    }
}
