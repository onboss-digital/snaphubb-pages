<?php

namespace App\Factories;

use App\Services\PushingPayPixService;

/**
 * Factory para resolver qual serviço PIX usar baseado em configuração
 * 
 * Suporta:
 * - "pushinpay" → PushingPayPixService (único provider)
 * 
 * Use em .env:
 *   PIX_PROVIDER=pushinpay
 */
class PixServiceFactory
{
    /**
     * Resolve e retorna a instância do serviço PIX configurado
     * 
     * @return PushingPayPixService
     */
    public static function make()
    {
        // Only Pushing Pay PIX provider is supported
        return app(PushingPayPixService::class);
    }

    /**
     * Alias para make()
     */
    public static function resolve()
    {
        return self::make();
    }

    /**
     * Retorna lista de providers disponíveis
     */
    public static function available(): array
    {
        return [
            'pushinpay' => PushingPayPixService::class,
        ];
    }

    /**
     * Verifica se um provider é válido
     */
    public static function isValid(string $provider): bool
    {
        return in_array(strtolower($provider), ['pushinpay']);
    }
}
