<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DetectLanguageByHeader
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Se já tem idioma travado na sessão, usa esse
        if (session()->has('user_language_locked')) {
            app()->setLocale(session('user_language'));
            return $next($request);
        }

        // Detecta do header Accept-Language
        $language = $this->detectLanguage($request->header('Accept-Language', ''));

        // Trava na sessão (nunca muda mais neste navegador)
        session([
            'user_language' => $language,
            'user_language_locked' => true,
            'language_detected_at' => now()->toDateTimeString(),
        ]);

        app()->setLocale($language);

        return $next($request);
    }

    /**
     * Detecta idioma do header Accept-Language
     * Ordem de prioridade: pt-BR > pt > es > en > br (default)
     */
    private function detectLanguage(string $acceptLanguage): string
    {
        $acceptLanguage = strtolower($acceptLanguage);

        // Português (Brasil e Portugal)
        if (str_contains($acceptLanguage, 'pt-br') || str_contains($acceptLanguage, 'pt_br')) {
            return 'br';
        }
        if (str_contains($acceptLanguage, 'pt')) {
            return 'br'; // Portugal → português BR
        }

        // Espanhol (España, México, Argentina, etc.)
        if (str_contains($acceptLanguage, 'es')) {
            return 'es';
        }

        // Inglês
        if (str_contains($acceptLanguage, 'en')) {
            return 'en';
        }

        // Fallback (seu mercado principal)
        return 'br';
    }
}
