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
            $sessionLocale = $request->session()->get('locale');
            if ($request->session()->get('locale_source') !== 'timezone') {
                $candidates[] = $sessionLocale;
            }
        }

        $candidates[] = $request?->cookie('yuejing_locale');

        if ($request?->user() && is_string($request->user()->preferred_locale ?? null)) {
            $candidates[] = $request->user()->preferred_locale;
        }

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $resolved = $this->resolve($candidate, $supported);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        $timezone = $request?->cookie('yuejing_timezone');
        if (! is_string($timezone) && $request?->hasSession()) {
            $timezone = $request->session()->get('timezone');
        }
        if (! is_string($timezone) && $request?->user()) {
            $timezone = $request->user()->timezone;
        }

        $timezoneLocale = $this->localeForTimezone($timezone, $supported);
        if ($timezoneLocale !== null) {
            return $timezoneLocale;
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

    public function localeForTimezone(?string $timezone, ?array $supported = null): ?string
    {
        $supported ??= $this->supported();
        if (! is_string($timezone) || ! in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            return null;
        }

        // Prefer country/city-specific choices, then fall back to the
        // language most commonly used for the IANA region. This covers the
        // full timezone database without pretending every city has its own
        // translation catalog.
        $mapping = [
            'UTC' => 'en', 'Etc/UTC' => 'en', 'Etc/GMT' => 'en',
            'Asia/Shanghai' => 'zh_CN', 'Asia/Hong_Kong' => 'zh_TW', 'Asia/Taipei' => 'zh_TW',
            'Asia/Macau' => 'zh_TW', 'Asia/Singapore' => 'zh_CN',
            'Asia/Tokyo' => 'ja', 'Asia/Seoul' => 'ko', 'Europe/Paris' => 'fr',
            'Europe/Berlin' => 'de', 'Europe/Vienna' => 'de', 'Europe/Zurich' => 'de',
            'Europe/Madrid' => 'es', 'Europe/Barcelona' => 'es', 'Europe/Rome' => 'it',
            'Europe/Lisbon' => 'pt', 'Europe/Amsterdam' => 'nl', 'Europe/Brussels' => 'fr',
            'Europe/Athens' => 'el', 'Europe/Warsaw' => 'pl', 'Europe/Prague' => 'cs',
            'Europe/Bucharest' => 'ro', 'Europe/Budapest' => 'hu', 'Europe/Stockholm' => 'sv',
            'Europe/Copenhagen' => 'da', 'Europe/Oslo' => 'no', 'Europe/Helsinki' => 'fi',
            'Europe/London' => 'en', 'America/New_York' => 'en', 'America/Chicago' => 'en',
            'America/Los_Angeles' => 'en', 'America/Toronto' => 'en', 'America/Vancouver' => 'en',
            'America/Mexico_City' => 'es', 'America/Bogota' => 'es', 'America/Lima' => 'es',
            'America/Santiago' => 'es', 'America/Argentina/Buenos_Aires' => 'es',
            'America/Sao_Paulo' => 'pt', 'America/Fortaleza' => 'pt',
            'Asia/Kolkata' => 'hi', 'Asia/Colombo' => 'hi', 'Asia/Dhaka' => 'bn',
            'Asia/Jakarta' => 'id', 'Asia/Makassar' => 'id', 'Asia/Kuala_Lumpur' => 'ms',
            'Asia/Almaty' => 'kk', 'Asia/Aqtobe' => 'kk', 'Asia/Bishkek' => 'ky',
            'Asia/Riyadh' => 'ar', 'Asia/Dubai' => 'ar', 'Asia/Tehran' => 'fa',
            'Asia/Kabul' => 'ps', 'Asia/Jerusalem' => 'he', 'Asia/Amman' => 'ar',
            'Asia/Baghdad' => 'ar', 'Asia/Beirut' => 'ar', 'Asia/Tashkent' => 'uz',
            'Asia/Ashgabat' => 'tk', 'Asia/Dushanbe' => 'tg', 'Asia/Yangon' => 'en',
            'Africa/Nairobi' => 'sw', 'Africa/Dar_es_Salaam' => 'sw', 'Africa/Kampala' => 'sw',
            'Africa/Addis_Ababa' => 'am', 'Africa/Cairo' => 'ar', 'Africa/Tripoli' => 'ar',
            'Africa/Johannesburg' => 'en', 'Africa/Lagos' => 'en', 'Africa/Accra' => 'en',
        ];

        $locale = $mapping[$timezone] ?? match (true) {
            str_starts_with($timezone, 'America/') && preg_match('/Mexico|Bogota|Lima|Santiago|Argentina|Cordoba|Montevideo|Asuncion|Caracas/', $timezone) === 1 => 'es',
            str_starts_with($timezone, 'America/') && preg_match('/Sao_Paulo|Bahia|Belem|Recife|Manaus|Porto_Velho/', $timezone) === 1 => 'pt',
            str_starts_with($timezone, 'America/') && preg_match('/Havana|Guatemala|El_Salvador|Managua|Tegucigalpa|Panama|Costa_Rica|Guayaquil/', $timezone) === 1 => 'es',
            str_starts_with($timezone, 'America/') && preg_match('/Paramaribo/', $timezone) === 1 => 'nl',
            str_starts_with($timezone, 'America/') && preg_match('/Guyana|Port_of_Spain|Jamaica|Bahia/', $timezone) === 1 => 'en',
            str_starts_with($timezone, 'Europe/') && preg_match('/Paris|Monaco|Brussels|Luxembourg/', $timezone) === 1 => 'fr',
            str_starts_with($timezone, 'Europe/') && preg_match('/Berlin|Vienna|Zurich|Busingen/', $timezone) === 1 => 'de',
            str_starts_with($timezone, 'Europe/') && preg_match('/Madrid|Andorra|Gibraltar/', $timezone) === 1 => 'es',
            str_starts_with($timezone, 'Europe/') && preg_match('/Rome|Vatican|San_Marino/', $timezone) === 1 => 'it',
            str_starts_with($timezone, 'Europe/') && preg_match('/Lisbon|Azores/', $timezone) === 1 => 'pt',
            str_starts_with($timezone, 'Europe/') && preg_match('/Moscow|Kalin|Samar|Volgograd|Astrakhan/', $timezone) === 1 => 'ru',
            str_starts_with($timezone, 'Europe/') && preg_match('/Kiev|Kyiv/', $timezone) === 1 => 'uk',
            str_starts_with($timezone, 'Asia/') && preg_match('/Shanghai|Chongqing|Harbin|Urumqi/', $timezone) === 1 => 'zh_CN',
            str_starts_with($timezone, 'Asia/') && preg_match('/Hong_Kong|Macau|Taipei/', $timezone) === 1 => 'zh_TW',
            str_starts_with($timezone, 'Asia/') && preg_match('/Kolkata|Calcutta/', $timezone) === 1 => 'hi',
            str_starts_with($timezone, 'Asia/') && preg_match('/Dhaka/', $timezone) === 1 => 'bn',
            str_starts_with($timezone, 'Asia/') && preg_match('/Jakarta|Makassar|Jayapura/', $timezone) === 1 => 'id',
            str_starts_with($timezone, 'Asia/') && preg_match('/Kuala_Lumpur|Kuching/', $timezone) === 1 => 'ms',
            str_starts_with($timezone, 'Asia/') && preg_match('/Ho_Chi_Minh|Saigon/', $timezone) === 1 => 'vi',
            str_starts_with($timezone, 'Asia/') && preg_match('/Bangkok/', $timezone) === 1 => 'th',
            str_starts_with($timezone, 'Asia/') && preg_match('/Manila/', $timezone) === 1 => 'en',
            str_starts_with($timezone, 'Asia/') && preg_match('/Riyadh|Dubai|Qatar|Kuwait|Bahrain/', $timezone) === 1 => 'ar',
            str_starts_with($timezone, 'Asia/') && preg_match('/Tehran/', $timezone) === 1 => 'fa',
            str_starts_with($timezone, 'Asia/') && preg_match('/Jerusalem/', $timezone) === 1 => 'he',
            str_starts_with($timezone, 'Asia/') && preg_match('/Istanbul/', $timezone) === 1 => 'tr',
            str_starts_with($timezone, 'Asia/') && preg_match('/Tashkent/', $timezone) === 1 => 'uz',
            str_starts_with($timezone, 'Asia/') && preg_match('/Almaty|Aqtobe/', $timezone) === 1 => 'kk',
            str_starts_with($timezone, 'Africa/') && preg_match('/Nairobi|Kampala|Dar_es_Salaam/', $timezone) === 1 => 'sw',
            default => 'en',
        };

        if (! is_string($locale)) {
            return null;
        }

        return $this->resolve($locale, $supported);
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
