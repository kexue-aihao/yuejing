<?php

namespace App\Http\Middleware;

use App\Services\DeviceDetector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectDevice
{
    public function __construct(private readonly DeviceDetector $detector)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $device = $this->detector->detect($request->userAgent());
        $request->attributes->set('device', $device);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Yuejing-Platform', $device['platform']);
        $response->headers->set('X-Yuejing-Device-Type', $device['device_type']);
        $response->headers->set('X-Yuejing-WebView', $device['is_webview'] ? '1' : '0');

        $vary = collect(explode(',', (string) $response->headers->get('Vary')))
            ->map(fn (string $value): string => trim($value))
            ->filter()
            ->values();
        if (! $vary->contains(fn (string $value): bool => strcasecmp($value, 'User-Agent') === 0)) {
            $vary->push('User-Agent');
        }
        $response->headers->set('Vary', $vary->implode(', '));

        return $response;
    }
}
