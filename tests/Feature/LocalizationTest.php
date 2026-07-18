<?php

namespace Tests\Feature;

use App\Services\LocaleManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_locale_catalog_is_exposed_in_the_language_switcher(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('data-language-switcher', false)
            ->assertSee('name="locale"', false)
            ->assertSee('value="en"', false)
            ->assertSee('value="ar"', false)
            ->assertSee('value="ja"', false);
    }

    public function test_locale_switch_persists_and_updates_html_language(): void
    {
        $token = bin2hex(random_bytes(16));

        $this->withSession(['_token' => $token])
            ->post(route('language.switch'), ['_token' => $token, 'locale' => 'ja'])
            ->assertRedirect()
            ->assertCookie('yuejing_locale', 'ja');

        $this->withSession(['locale' => 'ja'])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('<html lang="ja"', false)
            ->assertSee('>ホーム<', false)
            ->assertSee('>ライブラリ<', false);
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $token = bin2hex(random_bytes(16));

        $this->withSession(['_token' => $token])
            ->post(route('language.switch'), ['_token' => $token, 'locale' => 'not-a-locale'])
            ->assertSessionHasErrors('locale');
    }

    public function test_browser_language_tags_resolve_to_the_closest_catalog(): void
    {
        config(['locales.browser_detection' => true]);

        $response = $this->withHeaders([
            'Accept-Language' => 'pt-BR,fr-FR;q=0.8,en;q=0.5',
        ])->get(route('home'));

        $response->assertOk()
            ->assertSee('<html lang="pt-BR"', false)
            ->assertSee('>Início<', false);
    }

    public function test_region_specific_session_locale_resolves_to_a_base_catalog(): void
    {
        $response = $this->withSession(['locale' => 'zh-Hant-TW'])->get(route('home'));

        $response->assertOk()
            ->assertSee('<html lang="zh-TW"', false)
            ->assertSee('>首頁<', false);
    }

    public function test_every_supported_locale_has_ui_and_validation_catalogs(): void
    {
        $uiKeys = $this->translationKeys(require base_path('lang/en/ui.php'));
        $validationKeys = $this->translationKeys(require base_path('lang/en/validation.php'));
        $supported = config('locales.supported', []);

        $this->assertIsArray($supported);

        foreach ($supported as $locale => $definition) {
            $this->assertIsArray($definition, "Locale definition must be an array for {$locale}");

            $translation = $definition['translation'] ?? null;
            $this->assertIsString($translation, "Locale translation mapping must be a string for {$locale}");
            $this->assertArrayHasKey($translation, $supported, "Locale translation mapping must reference a supported locale for {$locale}");

            $uiCatalog = $this->loadCatalog($translation, 'ui');
            $validationCatalog = $this->loadCatalog($translation, 'validation');

            $this->assertSame(
                $uiKeys,
                $this->translationKeys($uiCatalog),
                "UI translation keys must match the English catalog for {$locale}",
            );
            $this->assertSame(
                $validationKeys,
                $this->translationKeys($validationCatalog),
                "Validation translation keys must match the English catalog for {$locale}",
            );

            $response = $this->withSession(['locale' => $locale])->get(route('home'));
            $this->assertIsString($definition['html'] ?? null, "Locale HTML tag must be a string for {$locale}");
            $response->assertOk()->assertSee('<html lang="'.$definition['html'].'"', false);
            $response->assertSee('data-language-switcher', false);
        }
    }

    public function test_ui_and_validation_placeholders_match_the_english_catalog(): void
    {
        $supported = config('locales.supported', []);

        $this->assertIsArray($supported);

        foreach (['ui', 'validation'] as $catalogName) {
            $englishPlaceholders = $this->translationPlaceholders($this->loadCatalog('en', $catalogName));

            foreach ($supported as $locale => $definition) {
                $this->assertIsArray($definition, "Locale definition must be an array for {$locale}");

                $translation = $definition['translation'] ?? null;
                $this->assertIsString($translation, "Locale translation mapping must be a string for {$locale}");

                $this->assertSame(
                    $englishPlaceholders,
                    $this->translationPlaceholders($this->loadCatalog($translation, $catalogName)),
                    "{$catalogName} placeholders must match the English catalog for {$locale}",
                );
            }
        }
    }

    public function test_locale_configuration_points_to_valid_and_loadable_catalog_directories(): void
    {
        $supported = config('locales.supported', []);
        $langDirectory = realpath(base_path('lang'));

        $this->assertIsArray($supported);
        $this->assertNotFalse($langDirectory, 'The language root directory must exist.');

        foreach ($supported as $locale => $definition) {
            $this->assertIsArray($definition, "Locale definition must be an array for {$locale}");

            $translation = $definition['translation'] ?? null;
            $this->assertIsString($translation, "Locale translation mapping must be a string for {$locale}");
            $this->assertMatchesRegularExpression(
                '/\\A[A-Za-z0-9_]+\\z/',
                $translation,
                "Locale translation mapping must be a safe directory name for {$locale}",
            );
            $this->assertArrayHasKey($translation, $supported, "Locale translation mapping must reference a supported locale for {$locale}");

            $directory = realpath(base_path("lang/{$translation}"));

            $this->assertNotFalse($directory, "Missing translation directory for {$locale}: {$translation}");
            $this->assertTrue(
                $directory === $langDirectory
                    || str_starts_with($directory, $langDirectory.DIRECTORY_SEPARATOR),
                "Translation directory must remain inside the language root for {$locale}",
            );
            $this->assertDirectoryExists($directory ?: base_path("lang/{$translation}"));

            $this->loadCatalog($translation, 'ui');
            $this->loadCatalog($translation, 'validation');
        }
    }

    public function test_locale_manager_rejects_malformed_default_and_fallback_configuration(): void
    {
        $default = config('locales.default');
        $fallback = config('locales.fallback');

        config([
            'locales.default' => 'missing-default',
            'locales.fallback' => 'missing-fallback',
        ]);

        try {
            $manager = app(LocaleManager::class);

            $this->get(route('home'))
                ->assertOk()
                ->assertSee('<html lang="en"', false);
            $this->assertSame('en', $manager->translationLocale('missing-locale'));
            $this->assertSame(config('locales.supported.en'), $manager->definition('missing-locale'));
        } finally {
            config([
                'locales.default' => $default,
                'locales.fallback' => $fallback,
            ]);
        }
    }

    /** @return list<string> */
    private function translationKeys(array $catalog, string $prefix = ''): array
    {
        $keys = [];

        foreach ($catalog as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            $keys = array_merge(
                $keys,
                is_array($value) ? $this->translationKeys($value, $path) : [$path],
            );
        }

        sort($keys);

        return $keys;
    }

    /** @return array<string, list<string>> */
    private function translationPlaceholders(array $catalog, string $prefix = ''): array
    {
        $placeholders = [];

        foreach ($catalog as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $placeholders += $this->translationPlaceholders($value, $path);
                continue;
            }

            $this->assertIsString($value, "Translation values must be strings at {$path}");
            preg_match_all('/:[A-Za-z_][A-Za-z0-9_]*/', $value, $matches);
            $tokens = array_map(static fn (string $token): string => substr($token, 1), $matches[0] ?? []);
            $tokens = array_values(array_unique($tokens));
            sort($tokens);
            $placeholders[$path] = $tokens;
        }

        ksort($placeholders);

        return $placeholders;
    }

    /** @return array<string, mixed> */
    private function loadCatalog(string $locale, string $catalog): array
    {
        $path = base_path("lang/{$locale}/{$catalog}.php");

        if (! is_file($path)) {
            $this->fail("Missing {$catalog} catalog for {$locale}: {$path}");
        }

        $loaded = require $path;
        $this->assertIsArray($loaded, "The {$catalog} catalog must return an array for {$locale}");

        return is_array($loaded) ? $loaded : [];
    }
}
