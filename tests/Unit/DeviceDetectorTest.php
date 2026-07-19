<?php

namespace Tests\Unit;

use App\Services\DeviceDetector;
use PHPUnit\Framework\TestCase;

class DeviceDetectorTest extends TestCase
{
    public function test_android_phone_and_webview_are_detected(): void
    {
        $device = (new DeviceDetector)->detect(
            'Mozilla/5.0 (Linux; Android 14; Pixel 8 Build/UQ1A.240205.002; wv) AppleWebKit/537.36 Chrome/121.0.6167.101 Mobile Safari/537.36',
        );

        $this->assertSame('android', $device['platform']);
        $this->assertSame('phone', $device['device_type']);
        $this->assertTrue($device['is_android']);
        $this->assertTrue($device['is_mobile']);
        $this->assertTrue($device['is_webview']);
    }

    public function test_iphone_safari_is_detected_without_being_marked_as_webview(): void
    {
        $device = (new DeviceDetector)->detect(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 Version/17.3 Mobile/15E148 Safari/604.1',
        );

        $this->assertSame('ios', $device['platform']);
        $this->assertSame('phone', $device['device_type']);
        $this->assertTrue($device['is_ios']);
        $this->assertTrue($device['is_mobile']);
        $this->assertFalse($device['is_webview']);
    }

    public function test_iphone_embedded_webview_is_detected_as_ios_webview(): void
    {
        $device = (new DeviceDetector)->detect(
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148',
        );

        $this->assertSame('ios', $device['platform']);
        $this->assertTrue($device['is_ios']);
        $this->assertTrue($device['is_webview']);
    }

    public function test_ipad_desktop_mode_is_detected_as_an_ios_tablet(): void
    {
        $device = (new DeviceDetector)->detect(
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 Version/17.3 Mobile/15E148 Safari/604.1',
        );

        $this->assertSame('ios', $device['platform']);
        $this->assertSame('tablet', $device['device_type']);
        $this->assertTrue($device['is_ios']);
        $this->assertTrue($device['is_tablet']);
    }

    public function test_desktop_user_agent_is_not_marked_as_mobile(): void
    {
        $device = (new DeviceDetector)->detect(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/121.0.0.0 Safari/537.36',
        );

        $this->assertSame('desktop', $device['platform']);
        $this->assertSame('desktop', $device['device_type']);
        $this->assertFalse($device['is_mobile']);
        $this->assertFalse($device['is_webview']);
    }
}
