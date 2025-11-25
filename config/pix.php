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
    |   - 'pushinpay' → PushinPay (único provider)
    |
    */
    'pix_provider' => env('PIX_PROVIDER', 'pushinpay'),

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
