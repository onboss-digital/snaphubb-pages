<?php

namespace App\Factories;

use App\Services\MercadoPagoPixService;
use App\Services\PushingPayPixService;

/**
 * Factory para resolver qual serviço PIX usar baseado em configuração
 * 
 * Suporta:
 * - "mercadopago" → MercadoPagoPixService
 * - "pushinpay" → PushingPayPixService
 * 
 * Use em .env:
 *   PIX_PROVIDER=mercadopago  (padrão)
 *   PIX_PROVIDER=pushinpay
 */
class PixServiceFactory
{
    /**
     * Resolve e retorna a instância do serviço PIX configurado
     * 
     * @return MercadoPagoPixService|PushingPayPixService
     */
    public static function make()
    {
        $provider = strtolower(env('PIX_PROVIDER', 'mercadopago'));

        return match ($provider) {
            'pushinpay', 'pushingpay' => app(PushingPayPixService::class),
            'mercadopago', 'default' => app(MercadoPagoPixService::class),
            default => app(MercadoPagoPixService::class),
        };
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
            'mercadopago' => MercadoPagoPixService::class,
            'pushinpay' => PushingPayPixService::class,
        ];
    }

    /**
     * Verifica se um provider é válido
     */
    public static function isValid(string $provider): bool
    {
        return in_array(strtolower($provider), ['mercadopago', 'pushinpay']);
    }
}
