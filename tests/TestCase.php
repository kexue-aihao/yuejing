<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('zh_CN');
        config([
            'app.locale' => 'zh_CN',
            'app.fallback_locale' => 'zh_CN',
            'locales.default' => 'zh_CN',
            'locales.fallback' => 'zh_CN',
            'locales.browser_detection' => false,
        ]);
    }

    protected function postWithCsrf(string $uri, array $data = [])
    {
        $token = bin2hex(random_bytes(16));

        return $this->withSession(['_token' => $token])->post($uri, ['_token' => $token, ...$data]);
    }

    protected function postJsonWithCsrf(string $uri, array $data = [])
    {
        $token = bin2hex(random_bytes(16));

        return $this->withSession(['_token' => $token])
            ->withHeaders(['X-CSRF-TOKEN' => $token, 'X-Requested-With' => 'XMLHttpRequest'])
            ->postJson($uri, ['_token' => $token, ...$data]);
    }

    protected function putJsonWithCsrf(string $uri, array $data = [])
    {
        $token = bin2hex(random_bytes(16));

        return $this->withSession(['_token' => $token])->putJson($uri, ['_token' => $token, ...$data]);
    }

    protected function deleteJsonWithCsrf(string $uri, array $data = [])
    {
        $token = bin2hex(random_bytes(16));

        return $this->withSession(['_token' => $token])
            ->withHeaders(['X-CSRF-TOKEN' => $token, 'X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson($uri, ['_token' => $token, ...$data]);
    }
}
