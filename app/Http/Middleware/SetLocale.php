<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $availableLanguages = ['br', 'en', 'es'];
        $locale = Session::get('locale');

        if ($locale && in_array($locale, $availableLanguages)) {
            App::setLocale($locale);
        } else {
            // Session locale not set, so detect from browser headers
            $preferredLanguage = $request->getPreferredLanguage($availableLanguages);

            if (str_starts_with($preferredLanguage, 'pt')) {
                $locale = 'br';
            } elseif (str_starts_with($preferredLanguage, 'es')) {
                $locale = 'es';
            } else {
                $locale = config('app.fallback_locale', 'br'); // Fallback to config
            }

            App::setLocale($locale);
            Session::put('locale', $locale);
        }

        return $next($request);
    }
}
