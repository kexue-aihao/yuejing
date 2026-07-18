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

        return back()
            ->withCookie(cookie('yuejing_locale', $data['locale'], 60 * 24 * 365))
            ->withHeaders([
                // Prevent the redirect from restoring a cached homepage
                // before the new locale cookie is applied.
                'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
    }
}
