<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PIX Payment Service Provider
    |--------------------------------------------------------------------------
    |
    | Define qual provider de pagamento PIX será usado na aplicação.
    |
    | Valores suportados:
    |   - 'mercadopago' → MercadoPago (padrão, mais confiável)
    |   - 'pushinpay'   → PushinPay (alternativa)
    |
    */
    'pix_provider' => env('PIX_PROVIDER', 'mercadopago'),

    /*
    |--------------------------------------------------------------------------
    | Configurações MercadoPago
    |--------------------------------------------------------------------------
    */
    'mercadopago' => [
        'env' => env('MERCADOPAGO_ENV', 'sandbox'),
        'sandbox_token' => env('MERCADOPAGO_SANDBOX_TOKEN'),
        'production_token' => env('MERCADOPAGO_PRODUCTION_TOKEN'),
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'notification_url' => env('MERCADOPAGO_NOTIFICATION_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configurações PushinPay
    |--------------------------------------------------------------------------
    */
    'pushinpay' => [
        'environment' => env('ENVIRONMENT', 'production'),
        'access_token' => env('PP_ACCESS_TOKEN_PRODUCTION') ?? env('PP_ACCESS_TOKEN_PROD'),
    ],
];
