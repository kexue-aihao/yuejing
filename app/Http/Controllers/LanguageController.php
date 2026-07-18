<?php

namespace App\Http\Controllers;

use App\Services\LocaleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    public function update(Request $request, LocaleManager $locales): RedirectResponse
    {
        $supported = $locales->supported();

        $data = $request->validate([
            'locale' => ['bail', 'required', 'string', Rule::in(array_keys($supported))],
        ]);

        $request->session()->put('locale', $data['locale']);

        return redirect()->to($this->localizedRedirectTarget($request))
            ->withCookie(cookie('yuejing_locale', $data['locale'], 60 * 24 * 365))
            ->withHeaders([
                // Prevent the redirect from restoring a cached homepage
                // before the new locale cookie is applied.
                'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'CDN-Cache-Control' => 'no-store',
                'Cloudflare-CDN-Cache-Control' => 'no-store',
                'Surrogate-Control' => 'no-store',
            ]);
    }

    private function localizedRedirectTarget(Request $request): string
    {
        $fallback = route('home');
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || trim($referer) === '') {
            $referer = $fallback;
        }

        $parts = parse_url($referer);
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $path = is_array($parts) ? ($parts['path'] ?? '/') : '/';

        // Never turn the language form into an open redirect. The browser's
        // Referer is only used when it points back to this application.
        if ($host !== $request->getHost() || ! in_array($scheme, ['http', 'https'], true) || ! is_string($path) || ! str_starts_with($path, '/')) {
            $parts = parse_url($fallback) ?: [];
            $host = $parts['host'] ?? $request->getHost();
            $scheme = $parts['scheme'] ?? $request->getScheme();
            $path = $parts['path'] ?? '/';
        }

        $port = is_array($parts) && isset($parts['port']) ? ':'.$parts['port'] : '';
        $query = is_array($parts) && isset($parts['query']) ? '?'.$parts['query'] : '?';

        // Some production CDNs still serve a cached HTML response after a
        // cookie-setting POST. A unique query makes the redirected document
        // uncacheable by URL while the response headers disable future reuse.
        return $scheme.'://'.$host.$port.$path.$query
            .($query === '?' ? '' : '&')
            .'_locale_refresh='.bin2hex(random_bytes(8));
    }
}
