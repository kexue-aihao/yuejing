<?php

namespace App\Services;

use Illuminate\Http\Request;

final class LocaleManager
{
    public function supported(): array
    {
        $supported = config('locales.supported', []);

        if (! is_array($supported)) {
            return [];
        }

        return array_filter($supported, static fn (mixed $definition): bool => is_array($definition));
    }

    public function current(?Request $request = null): string
    {
        $supported = $this->supported();
        $fallback = $this->configuredLocale('locales.default', $supported);

        if ($request === null && app()->bound('request')) {
            $request = app('request');
        }

        $candidates = [];

        if ($request?->hasSession()) {
            $candidates[] = $request->session()->get('locale');
        }

        $candidates[] = $request?->cookie('yuejing_locale');

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $resolved = $this->resolve($candidate, $supported);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $acceptLanguage = $request?->headers->get('Accept-Language');

        if (config('locales.browser_detection', true)
            && is_string($acceptLanguage)
            && trim($acceptLanguage) !== '') {
            foreach ($this->browserLocales($acceptLanguage) as $browserLocale) {
                $resolved = $this->resolve($browserLocale, $supported);

                if ($resolved !== null) {
                    return $resolved;
                }
            }
        }

        return $fallback;
    }

    /**
     * Return browser language ranges in preference order while excluding
     * ranges explicitly assigned q=0. Symfony exposes the sorted language
     * tags but not their quality values through Request::getLanguages().
     *
     * @return list<string>
     */
    private function browserLocales(string $header): array
    {
        $languages = [];

        foreach (explode(',', $header) as $position => $part) {
            $segments = array_map('trim', explode(';', $part));
            $language = strtolower((string) array_shift($segments));

            if ($language === '' || $language === '*') {
                continue;
            }

            $quality = 1.0;

            foreach ($segments as $segment) {
                if (str_starts_with(strtolower($segment), 'q=')) {
                    $value = trim(substr($segment, 2));
                    $quality = is_numeric($value) ? (float) $value : 0.0;
                    break;
                }
            }

            if ($quality <= 0) {
                continue;
            }

            $languages[] = [
                'language' => $language,
                'quality' => min(1.0, max(0.0, $quality)),
                'position' => $position,
            ];
        }

        usort($languages, static fn (array $left, array $right): int =>
            ($right['quality'] <=> $left['quality'])
                ?: ($left['position'] <=> $right['position'])
        );

        return array_values(array_unique(array_column($languages, 'language')));
    }

    public function translationLocale(string $locale): string
    {
        $supported = $this->supported();
        $resolved = $this->resolve($locale, $supported)
            ?? $this->configuredLocale('locales.default', $supported);

        $translation = $this->catalogLocale($resolved, $supported);

        if ($translation !== null) {
            return $translation;
        }

        return $this->catalogLocale(
            $this->configuredLocale('locales.fallback', $supported),
            $supported,
        ) ?? $this->catalogLocale('en', $supported)
            ?? array_key_first($supported)
            ?? 'en';
    }

    public function definition(string $locale): array
    {
        $supported = $this->supported();
        $locale = $this->resolve($locale, $supported)
            ?? $this->configuredLocale('locales.default', $supported);

        return $supported[$locale]
            ?? $supported[$this->configuredLocale('locales.default', $supported)]
            ?? [];
    }

    /**
     * Resolve a configured locale without allowing malformed configuration to
     * escape into App::setLocale(), HTML attributes, or translation lookups.
     */
    private function configuredLocale(string $key, array $supported): string
    {
        $candidate = config($key);

        if (is_string($candidate)) {
            $resolved = $this->resolve($candidate, $supported);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        if (array_key_exists('en', $supported)) {
            return 'en';
        }

        return array_key_first($supported) ?? 'en';
    }

    /**
     * Resolve exact, BCP-47, legacy, and base language tags to a catalog.
     * This lets browser preferences such as pt-BR and zh-Hans use the closest
     * native catalog without creating duplicate regional files.
     *
     * @param array<string, array<string, mixed>> $supported
     */
    private function resolve(string $locale, array $supported): ?string
    {
        $locale = trim($locale);

        if ($locale === '' || strlen($locale) > 128 || preg_match('/[\x00-\x1F\x7F]/', $locale) === 1) {
            return null;
        }

        $normalizedLocale = $this->normalizeTag($locale);

        foreach ($supported as $code => $definition) {
            $tags = [$code];

            if (is_array($definition)) {
                $tags[] = $definition['html'] ?? null;
            }

            foreach ($tags as $tag) {
                if (is_string($tag) && $this->normalizeTag($tag) === $normalizedLocale) {
                    return $code;
                }
            }
        }

        $base = strtolower((string) preg_replace('/[-_].*$/', '', $locale));
        $legacy = ['iw' => 'he', 'in' => 'id', 'ji' => 'yi', 'nb' => 'no'];
        $base = $legacy[$base] ?? $base;

        foreach ($supported as $code => $definition) {
            $html = is_array($definition) ? ($definition['html'] ?? null) : null;
            $translation = is_array($definition) ? ($definition['translation'] ?? null) : null;

            if ($code === $base || $html === $base || $translation === $base) {
                return $code;
            }
        }

        if ($base === 'zh') {
            return str_contains(strtolower($locale), 'tw')
                || str_contains(strtolower($locale), 'hant')
                ? (array_key_exists('zh_TW', $supported) ? 'zh_TW' : null)
                : (array_key_exists('zh_CN', $supported) ? 'zh_CN' : null);
        }

        return null;
    }

    /**
     * Return the actual translation catalog for a display locale. Regional
     * aliases may point through another alias, so follow the chain with a
     * cycle guard before handing a locale to Laravel's translator.
     *
     * @param array<string, array<string, mixed>> $supported
     */
    private function catalogLocale(string $locale, array $supported): ?string
    {
        $current = $locale;
        $visited = [];

        for ($index = 0; $index <= count($supported); $index++) {
            if (isset($visited[$current]) || ! array_key_exists($current, $supported)) {
                return null;
            }

            $visited[$current] = true;
            $definition = $supported[$current];
            $translation = is_array($definition) ? ($definition['translation'] ?? null) : null;

            if (! is_string($translation) || ! array_key_exists($translation, $supported)) {
                return null;
            }

            if ($translation === $current) {
                return $current;
            }

            $current = $translation;
        }

        return null;
    }

    private function normalizeTag(string $locale): string
    {
        return strtolower(str_replace('_', '-', trim($locale)));
    }
}
