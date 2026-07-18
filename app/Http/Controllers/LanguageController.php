<?php

namespace App\Http\Controllers;

use App\Services\LocaleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\JsonResponse;

class LanguageController extends Controller
{
    public function update(Request $request, LocaleManager $locales): RedirectResponse
    {
        $supported = $locales->supported();

        $data = $request->validate([
            'locale' => ['bail', 'required', 'string', Rule::in(array_keys($supported))],
        ]);

        $request->session()->put('locale', $data['locale']);
        $request->session()->put('locale_source', 'manual');
        if ($request->user()) {
            $request->user()->forceFill(['preferred_locale' => $data['locale']])->save();
        }

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

    public function timezone(Request $request, LocaleManager $locales): JsonResponse
    {
        $data = $request->validate([
            'timezone' => ['required', 'string', 'max:64', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! in_array($value, \DateTimeZone::listIdentifiers(), true)) {
                    $fail('The selected timezone is invalid.');
                }
            }],
        ]);

        $previousLocale = $locales->current($request);

        if ($request->user()) {
            $request->user()->forceFill(['timezone' => $data['timezone']])->save();
        }

        $request->session()->put('timezone', $data['timezone']);
        $explicit = $request->session()->get('locale_source') === 'manual'
            || $request->cookie('yuejing_locale') !== null
            || (bool) ($request->user()?->preferred_locale);
        $locale = $locales->localeForTimezone($data['timezone']);
        $changed = false;

        if (! $explicit && $locale !== null && $previousLocale !== $locale) {
            $request->session()->put(['locale' => $locale, 'locale_source' => 'timezone']);
            $changed = true;
        }

        return response()->json(['locale' => $locale, 'changed' => $changed])
            ->withCookie(cookie('yuejing_timezone', $data['timezone'], 60 * 24 * 365));
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
