<?php

namespace App\Services;

final class DeviceDetector
{
    public function detect(?string $userAgent): array
    {
        $userAgent = trim((string) $userAgent);
        $normalized = strtolower($userAgent);
        $isAndroid = str_contains($normalized, 'android');
        $isIos = preg_match('/iphone|ipad|ipod/', $normalized) === 1
            || (str_contains($normalized, 'macintosh') && str_contains($normalized, 'mobile'));
        $isTablet = str_contains($normalized, 'ipad')
            || str_contains($normalized, 'tablet')
            || ($isIos && str_contains($normalized, 'macintosh') && str_contains($normalized, 'mobile'))
            || ($isAndroid && ! str_contains($normalized, 'mobile'));
        $isMobile = $isAndroid
            || $isIos
            || preg_match('/mobile|phone|ipod|blackberry|iemobile|opera mini/', $normalized) === 1;
        $isWebView = ($isAndroid && (preg_match('/;\s*wv[;\)]/', $normalized) === 1
            || (str_contains($normalized, 'version/4.0') && str_contains($normalized, 'chrome/'))))
            || ($isIos
                && str_contains($normalized, 'applewebkit')
                && preg_match('/safari|crios|fxios|opios|edgios/', $normalized) !== 1);

        $platform = $isAndroid ? 'android' : ($isIos ? 'ios' : 'desktop');
        $deviceType = ! $isMobile ? 'desktop' : ($isTablet ? 'tablet' : 'phone');

        return [
            'platform' => $platform,
            'device_type' => $deviceType,
            'is_mobile' => $isMobile,
            'is_tablet' => $isTablet,
            'is_phone' => $isMobile && ! $isTablet,
            'is_android' => $isAndroid,
            'is_ios' => $isIos,
            'is_webview' => $isWebView,
        ];
    }
}
