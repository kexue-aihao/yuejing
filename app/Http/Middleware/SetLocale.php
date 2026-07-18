<?php

namespace App\Http\Middleware;

use App\Services\LocaleManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private readonly LocaleManager $locales)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->locales->current($request);
        App::setLocale($this->locales->translationLocale($locale));
        App::setFallbackLocale($this->locales->translationLocale((string) config('locales.fallback')));
        $request->attributes->set('display_locale', $locale);

        return $next($request);
    }
}
