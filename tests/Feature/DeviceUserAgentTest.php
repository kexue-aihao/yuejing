<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceUserAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_android_homepage_exposes_device_context_and_cache_variation(): void
    {
        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/121.0.0.0 Mobile Safari/537.36',
        )->get(route('home'));

        $response->assertOk()
            ->assertHeader('X-Yuejing-Platform', 'android')
            ->assertHeader('X-Yuejing-Device-Type', 'phone')
            ->assertHeader('X-Yuejing-WebView', '0')
            ->assertSee('data-device-platform="android"', false)
            ->assertSee('data-device-type="phone"', false)
            ->assertSee('data-device-mobile="1"', false)
            ->assertSee('data-device-webview="0"', false);

        $this->assertStringContainsString('User-Agent', (string) $response->headers->get('Vary'));
    }

    public function test_iphone_homepage_exposes_ios_device_context(): void
    {
        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3 like Mac OS X) AppleWebKit/605.1.15 Version/17.3 Mobile/15E148 Safari/604.1',
        )->get(route('home'));

        $response->assertOk()
            ->assertHeader('X-Yuejing-Platform', 'ios')
            ->assertHeader('X-Yuejing-Device-Type', 'phone')
            ->assertSee('class="site-body device-ios device-type-phone"', false);
    }

    public function test_ipad_homepage_exposes_ios_tablet_context(): void
    {
        $response = $this->withHeader(
            'User-Agent',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15 Version/17.3 Mobile/15E148 Safari/604.1',
        )->get(route('home'));

        $response->assertOk()
            ->assertHeader('X-Yuejing-Platform', 'ios')
            ->assertHeader('X-Yuejing-Device-Type', 'tablet')
            ->assertSee('data-device-type="tablet"', false);
    }
}
