<?php

namespace App\Http\Middleware;

use App\Services\TranslationService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        $defaultLocale = TranslationService::getDefaultLocale();

        if ($locale && TranslationService::isValidLocale($locale) && $locale !== $defaultLocale) {
            App::setLocale($locale);
            // Store in session for persistence
            session(['locale' => $locale]);
        } else {
            // Check session/cookie for returning users
            $sessionLocale = session('locale', $defaultLocale);
            if ($sessionLocale !== $defaultLocale && TranslationService::isValidLocale($sessionLocale)) {
                App::setLocale($sessionLocale);
            } else {
                App::setLocale($defaultLocale);
            }
        }

        // Set URL defaults so route() helper includes locale prefix
        $currentLocale = App::getLocale();
        if ($currentLocale !== $defaultLocale) {
            URL::defaults(['locale' => $currentLocale]);
        }

        return $next($request);
    }
}
