<?php

namespace App\Http\Middleware;

use Exception;
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
        try {
            $defaultLocale = TranslationService::getDefaultLocale();
        } catch (Exception $e) {
            // DB may not be available yet (install, migrations)
            return $next($request);
        }

        // 1. Check if this is a locale-prefixed route (e.g. /es/trending)
        $routeLocale = $request->route('locale');

        if ($routeLocale && TranslationService::isValidLocale($routeLocale) && $routeLocale !== $defaultLocale) {
            // URL has an explicit locale prefix — use it and persist to session
            App::setLocale($routeLocale);
            session(['locale' => $routeLocale]);
        } else {
            // 2. No locale prefix in URL — check session for persisted locale
            $sessionLocale = session('locale');

            if ($sessionLocale && $sessionLocale !== $defaultLocale && TranslationService::isValidLocale($sessionLocale)) {
                App::setLocale($sessionLocale);
            } else {
                App::setLocale($defaultLocale);
                // Clear stale session locale if it was set to default
                if ($sessionLocale === $defaultLocale) {
                    session()->forget('locale');
                }
            }
        }

        // 3. Set URL defaults so route() helper includes locale prefix
        $currentLocale = App::getLocale();
        if ($currentLocale !== $defaultLocale) {
            URL::defaults(['locale' => $currentLocale]);
        }

        // 4. Drop {locale} from the route parameters now that it has been read.
        //
        // Controller actions receive route parameters positionally, so without
        // this every localised route needed a parallel method whose only job was
        // to absorb a leading string $locale. Forgetting it lets one action
        // serve both /category/foo and /es/category/foo. URL::defaults above is
        // unaffected — it feeds route generation, not the current parameter bag.
        $request->route()?->forgetParameter('locale');

        $response = $next($request);

        // Set Content-Language header for search engine crawlers
        $response->headers->set('Content-Language', App::getLocale());

        return $response;
    }
}
