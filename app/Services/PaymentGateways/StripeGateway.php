<?php

namespace App\Services\PaymentGateways;

use App\Interfaces\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class StripeGateway implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $baseUrl;
    private string $redirectURL;
    private Client $client;

    public function __construct(Client $client = null)
    {
        $this->apiKey = config('services.stripe.api_secret_key');
        $this->baseUrl = config('services.stripe.api_url');

        // Default redirect URL after successful payments. Prefer env override, then app.url
        $this->redirectURL = env('THANKS_URL', rtrim(config('app.url') ?? '', '/') . '/upsell/thank-you-card');

        $this->client = $client ?: new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'verify' => !env('APP_DEBUG'),
        ]);
    }

    private function request(string $method, string $endpoint, array $params = [], array $extraHeaders = [])
    {
        try {
            $options = [];

            if (in_array(strtolower($method), ['post', 'put', 'patch'])) {
                $options['form_params'] = $params; // Stripe espera x-www-form-urlencoded
            } elseif (!empty($params)) {
                $options['query'] = $params; // GET com query string
            }

            if (!empty($extraHeaders)) {
                $options['headers'] = $extraHeaders;
            }

            $response = $this->client->request(strtoupper($method), $this->baseUrl . $endpoint, $options);
            return json_decode($response->getBody()->getContents(), true);
        } catch (RequestException $e) {
            $body = $e->getResponse() ? json_decode($e->getResponse()->getBody()->getContents(), true) : null;
            $errorMessage = $body['error']['message'] ?? $e->getMessage();
            throw new \Exception($errorMessage);
        }
    }

    public function createCardToken(array $cardData): array
    {
        return [
            'status' => 'error',
            'message' => 'Use Stripe.js para criar métodos de pagamento no front-end.',
        ];
    }

    public function processPayment(array $paymentData, string $idempotencyKey = null): array
    {
        try {
            try {
                \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - START', [
                    'timestamp' => now()->toDateTimeString(),
                ]);
                \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - incoming payload', [
                    'metadata' => $paymentData['metadata'] ?? null,
                    'cart' => $paymentData['cart'] ?? null,
                    'upsell_success_url' => $paymentData['upsell_success_url'] ?? null,
                ]);
            } catch (\Throwable $_) {
                // ignore logging errors
            }
            $paymentMethodId = $paymentData['payment_method_id'] ?? null;
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - step 1: paymentMethodId', ['id' => $paymentMethodId ?? 'null']);
            if (!$paymentMethodId) {
                // Try to resolve a default payment method from provided customer id
                $custId = $paymentData['customer']['id'] ?? $paymentData['customer_id'] ?? $paymentData['customer']['stripe_id'] ?? null;
                if (!empty($custId)) {
                    try {
                        $cust = $this->request('get', "/customers/{$custId}");
                        // Try invoice_settings.default_payment_method
                        $pm = $cust['invoice_settings']['default_payment_method'] ?? null;
                        if (!empty($pm)) {
                            $paymentMethodId = $pm;
                        } else {
                            // Fallback: list payment methods for customer and pick first
                            $list = $this->request('get', '/payment_methods', [
                                'customer' => $custId,
                                'type' => 'card',
                                'limit' => 1,
                            ]);
                            if (!empty($list['data'][0]['id'])) {
                                $paymentMethodId = $list['data'][0]['id'];
                            }
                        }
                    } catch (\Throwable $e) {
                        Log::channel('payment_checkout')->warning('StripeGateway: could not resolve default payment method', ['customer_id' => $custId, 'error' => $e->getMessage()]);
                    }
                }

                if (empty($paymentMethodId)) {
                    return ['status' => 'error', 'message' => 'Missing payment method ID and no default card found for customer'];
                }
            }

            $email = $paymentData['customer']['email'] ?? null;
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - step 2: email', ['email' => $email ?? 'null']);

            // 🔍 Buscar cliente existente
            $existing = $this->request('get', '/customers', [
                'email' => $email,
                'limit' => 1,
            ]);
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - step 3: customer lookup done', ['has_existing' => !empty($existing['data'])]);

            if (!empty($existing['data'])) {
                $customer = $existing['data'][0];
            } else {
                $customer = $this->request('post', '/customers', [
                    'name'  => $paymentData['customer']['name'] ?? 'Cliente',
                    'email' => $email,
                    'phone' => $paymentData['customer']['phone_number'] ?? null,
                ]);
            }
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - step 4: customer created', ['customer_id' => $customer['id'] ?? 'null']);

            // 🔗 Buscar PaymentMethod
            $paymentMethod = $this->request('get', "/payment_methods/{$paymentMethodId}");
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - step 5: payment method fetched', ['method_id' => $paymentMethodId]);

            if (empty($paymentMethod['customer'])) {
                $this->request('post', "/payment_methods/{$paymentMethodId}/attach", [
                    'customer' => $customer['id'],
                ]);
            } elseif ($paymentMethod['customer'] !== $customer['id']) {
                // Reusar o mesmo customer do cartão
                $customer['id'] = $paymentMethod['customer'];
            }
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - step 6: payment method attached');

            // Definir PaymentMethod como padrão
            $this->request('post', "/customers/{$customer['id']}", [
                'invoice_settings[default_payment_method]' => $paymentMethodId,
            ]);
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - step 7: set default payment method');

            $results = [];

            // 0) Validar o payment_method antes de tudo
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - step 8: creating setup intent');
            $setupIntent = $this->request('post', '/setup_intents', [
                'customer' => $customer['id'],
                'payment_method' => $paymentMethodId,
                'confirm' => 'true',
                'usage' => 'off_session', // 🔹 garante que pode cobrar recorrente depois
                'automatic_payment_methods' => [
                    'enabled' => 'true',
                    'allow_redirects' => 'never' // 🔹 bloqueia redirect
                ]
            ], $idempotencyKey ? ['Idempotency-Key' => $idempotencyKey . '-setup'] : []);
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - step 9: setup intent created', ['status' => $setupIntent['status'] ?? 'unknown']);

            // If setup intent requires action (SCA), return structured response so frontend can handle
            if (($setupIntent['status'] ?? null) !== 'succeeded') {
                $status = $setupIntent['status'] ?? 'unknown';
                if (in_array($status, ['requires_action', 'requires_confirmation', 'requires_payment_method']) && !empty($setupIntent['client_secret'])) {
                    return [
                        'status' => 'requires_action',
                        'action' => 'setup_intent',
                        'client_secret' => $setupIntent['client_secret'] ?? null,
                        'setup_intent' => $setupIntent,
                    ];
                }

                return [
                    'status' => 'error',
                    'errors' => ["Falha ao validar método de pagamento ({$status})"],
                    'setup_intent' => $setupIntent,
                ];
            }

            // 1) Criar cobranças (agora temos certeza que o cartão é válido)
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - step 10: starting cart processing', ['cart_items' => count($paymentData['cart'])]);
            foreach ($paymentData['cart'] as $idx => $product) {
                \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - processing cart item', ['index' => $idx, 'product' => $product['product_hash'] ?? 'unknown', 'recurring' => !empty($product['recurring'])]);
                $isRecurring = !empty($product['recurring']);

                if ($isRecurring) {
                    \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - product is recurring, preparing subscription', ['product' => $product['product_hash'] ?? 'unknown', 'price_id' => $product['price_id'] ?? 'unknown']);
                    // Criar assinatura já ativa (não precisa pré-autorizar)
                    $subscriptionData = [
                        'customer' => $customer['id'],
                        'items' => [
                            ['price' => $product['price_id'], 'quantity' => $product['quantity'] ?? 1],
                        ],
                        'default_payment_method' => $paymentMethodId,
                        'payment_behavior' => 'allow_incomplete', // ⚡ permite criar e cobrar
                        'collection_method' => 'charge_automatically',
                        'expand' => ['latest_invoice.payment_intent'],
                        'metadata' => array_merge($paymentData['metadata'] ?? [], [
                            'price_id' => $product['price_id'],
                            'product_id' => $product['product_hash'],
                            'title' => $product['title']
                        ])
                    ];


                    \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - calling Stripe API to create subscription', ['product' => $product['product_hash'] ?? 'unknown']);
                    $sub = $this->request('post', '/subscriptions', $subscriptionData, $idempotencyKey ? ['Idempotency-Key' => $idempotencyKey . '-sub'] : []);
                    \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - subscription created', ['product' => $product['product_hash'] ?? 'unknown', 'subscription_id' => $sub['id'] ?? 'unknown']);

                    // ⚡ Confirmar pagamento da primeira fatura
                    \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - checking subscription payment intent', ['product' => $product['product_hash'] ?? 'unknown', 'has_invoice' => !empty($sub['latest_invoice'])]);
                    if (!empty($sub['latest_invoice']['payment_intent'])) {
                        $pi = $sub['latest_invoice']['payment_intent'];
                        $piStatus = $pi['status'] ?? null;
                        if (in_array($piStatus, ['requires_action','requires_confirmation','requires_payment_method']) && !empty($pi['client_secret'])) {
                            return [
                                'status' => 'requires_action',
                                'action' => 'payment_intent',
                                'client_secret' => $pi['client_secret'] ?? null,
                                'payment_intent' => $pi,
                                'subscription' => $sub,
                            ];
                        }

                        if ($piStatus === 'requires_payment_method') {
                            $piConfirmed = $this->request('post', "/payment_intents/{$pi['id']}/confirm", [
                                'payment_method' => $paymentMethodId
                            ], $idempotencyKey ? ['Idempotency-Key' => $idempotencyKey . '-pi-confirm'] : []);
                            $results[] = $piConfirmed;
                        }
                    }
                    $results[] = $sub;
                } else {
                    \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - product is one-time/non-recurring', ['product' => $product['product_hash'] ?? 'unknown']);
                    // Produto avulso -> pode criar PaymentIntent normalmente
                    // Se não houver `price_id` (ex: backend fallback), não chamar /prices/ sem id
                    if (!empty($product['price_id'])) {
                        $price = $this->request('get', "/prices/{$product['price_id']}");
                        $unitAmount = $price['unit_amount'] ?? null;
                        $currency = $price['currency'] ?? ($product['currency'] ?? 'brl');
                        $metaProductId = $price['product'] ?? ($product['product_hash'] ?? null);
                    } else {
                        // Try to resolve price from provided product external id in metadata
                        $resolvedUnit = null;
                        $resolvedCurrency = null;
                        $metaProduct = $paymentData['metadata']['product_external_id'] ?? $product['product_hash'] ?? null;
                        if (!empty($metaProduct)) {
                            try {
                                $prodResp = $this->getProductWithPrices($metaProduct);
                                if (($prodResp['status'] ?? null) === 'success' && !empty($prodResp['prices'])) {
                                    // pick first matching currency
                                    $found = null;
                                    foreach ($prodResp['prices'] as $p) {
                                        if (strtoupper($p['currency'] ?? '') === strtoupper($product['currency'] ?? 'BRL') || empty($found)) {
                                            $found = $p;
                                            if (strtoupper($p['currency'] ?? '') === strtoupper($product['currency'] ?? 'BRL')) break;
                                        }
                                    }
                                    if (!empty($found)) {
                                        $resolvedUnit = $found['unit_amount'] ?? null;
                                        $resolvedCurrency = $found['currency'] ?? ($product['currency'] ?? 'brl');
                                        $metaProductId = $metaProduct;
                                    }
                                }
                            } catch (\Throwable $e) {
                                Log::channel('payment_checkout')->warning('StripeGateway: could not resolve product prices from metadata', ['product' => $metaProduct, 'error' => $e->getMessage()]);
                            }
                        }

                        if ($resolvedUnit !== null) {
                            $unitAmount = $resolvedUnit;
                            $currency = $resolvedCurrency;
                        } else {
                            // Fallback: usar valor enviado pelo backend. O `price` no frontend geralmente
                            // já vem em centavos (ex: 2790). Detectamos se o valor parece já estar em
                            // centavos para evitar multiplicação dupla.
                            $fallback = $product['price'] ?? $product['amount'] ?? null;
                            if ($fallback === null) {
                                throw new \Exception('Missing price_id and fallback amount for product');
                            }
                            $fallbackFloat = (float) $fallback;
                            if ($fallbackFloat >= 1000) {
                                // valor provavelmente já em centavos (ex: 2790 -> R$27.90)
                                $unitAmount = (int) round($fallbackFloat);
                            } else {
                                // valor provavelmente em unidades (ex: 27.9) -> converter para centavos
                                $unitAmount = (int) round($fallbackFloat * 100);
                            }
                            $currency = strtolower($product['currency'] ?? 'brl');
                            $metaProductId = $product['product_hash'] ?? null;
                        }
                    }

                    $intent = $this->request('post', '/payment_intents', [
                        'customer' => $customer['id'],
                        'payment_method' => $paymentMethodId,
                        'amount' => $unitAmount,
                        'currency' => $currency,
                        'confirm' => 'true',
                        'capture_method' => 'automatic', // 🔹 agora pode cobrar direto
                        'description' => "Pagamento {$product['title']}",
                        'automatic_payment_methods' => [
                            'enabled' => 'true',
                            'allow_redirects' => 'never'
                        ],
                        'metadata' => [
                            'price_id' => $product['price_id'] ?? null,
                            'product_id' => $metaProductId,
                            'title' => $product['title']
                        ]
                    ], $idempotencyKey ? ['Idempotency-Key' => $idempotencyKey . '-pi'] : []);
                    \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - payment intent created and confirmed', ['product' => $product['product_hash'] ?? 'unknown', 'pi_status' => $intent['status'] ?? 'unknown', 'pi_id' => $intent['id'] ?? 'unknown']);

                    // If PaymentIntent requires action (SCA), return structured response so frontend can handle
                    if (in_array($intent['status'] ?? '', ['requires_action','requires_confirmation']) && !empty($intent['client_secret'])) {
                        \Illuminate\Support\Facades\Log::channel('payment_checkout')->warning('StripeGateway::processPayment - payment intent requires customer action', ['product' => $product['product_hash'] ?? 'unknown', 'status' => $intent['status'] ?? 'unknown']);
                        return [
                            'status' => 'requires_action',
                            'action' => 'payment_intent',
                            'client_secret' => $intent['client_secret'] ?? null,
                            'payment_intent' => $intent,
                        ];
                    }

                    $results[] = $intent;
                    \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - non-recurring product processed', ['product' => $product['product_hash'] ?? 'unknown']);
                }
            }
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - STEP 11: all cart items processed successfully', ['total_results' => count($results), 'total_items' => count($paymentData['cart'])]);
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - STEP 13: building final response', ['customer_id' => $customer['id'] ?? 'unknown']);
            $redirect = $paymentData['upsell_success_url'] ?? $paymentData['upsell_url'] ?? (env('THANKS_URL', rtrim(config('app.url') ?? '', '/') . '/upsell/thank-you-card'));

            $offerId = $paymentData['offer_hash'] ?? $paymentData['product_id'] ?? $paymentData['metadata']['product_external_id'] ?? $paymentData['metadata']['offer_hash'] ?? null;

            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('StripeGateway::processPayment - STEP 14: returning success response to frontend', ['customer_id' => $customer['id'] ?? 'unknown', 'offer_id' => $offerId ?? 'unknown', 'redirect_url' => $redirect ?? 'unknown']);

            return [
                'status' => 'success',
                'data' => [
                    'customerId' => $customer['id'],
                    'upsell_productId' => $offerId,
                    'redirect_url' => $redirect
                ]
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->error('StripeGateway::processPayment - EXCEPTION', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            Log::channel('payment_checkout')->error('StripeGateway: API Error', [
                'message' => $e->getMessage(),
            ]);
            $failRedirect = $paymentData['upsell_failed_url'] ?? $paymentData['upsell_url'] ?? null;
            $ret = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
            if (!empty($failRedirect)) $ret['redirect_url'] = $failRedirect;
            return $ret;
        }
    }
    public function handleResponse(array $responseData): array
    {
        if (($responseData['status'] ?? null) === 'succeeded') {
            return [
                'status' => 'success',
                'transaction_id' => $responseData['id'] ?? null,
                'redirect_url' => $this->redirectURL . "?id=" . ($responseData['payment_method'] ?? ''),
                'data' => $responseData,
            ];
        }

        return [
            'status' => 'error',
            'message' => $responseData['last_payment_error']['message'] ?? 'Payment failed.',
            'data' => $responseData,
        ];
    }

    public function getCustomerById(string $customerId): array
    {
        try {
            return $this->request('get', "/customers/{$customerId}");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::channel('payment_checkout')->error('StripeGateway: getCustomerById Error', [
                'message' => $e->getMessage(),
                'customer_id' => $customerId,
            ]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function getProductWithPrices(string $productId): array
    {
        try {
            // 🔹 Buscar produto e preços associados em paralelo (se sua request suportar)
            $product   = $this->request('get', "/products/{$productId}");
            $pricesRes = $this->request('get', '/prices', [
                'product' => $productId,
                'limit'   => 100,
            ]);

            // 🔹 Filtra apenas os ativos
            $activePrices = array_filter($pricesRes['data'] ?? [], static function ($price) {
                return $price['active'] === true;
            });

            // 🔹 Mapeia os dados que você precisa
            $prices = array_map(static function ($price) {
                return [
                    'id'          => $price['id'] ?? null,
                    'unit_amount' => $price['unit_amount'] ?? null,
                    'currency'    => strtoupper($price['currency'] ?? ''),
                    'recurring'   => $price['recurring'] ?? null,
                ];
            }, $activePrices);

            return [
                'status'  => 'success',
                'product' => [
                    'id'          => $product['id'] ?? null,
                    'name'        => $product['name'] ?? null,
                    'description' => $product['description'] ?? null,
                ],
                'prices'  => array_filter($prices, fn($p) => !empty($p['id'])), // remove inválidos
            ];
        } catch (\Throwable $e) {
            Log::channel('payment_checkout')->error('StripeGateway: getProductWithPrices Error', [
                'message'    => $e->getMessage(),
                'product_id' => $productId,
            ]);

            return [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function formatPlans(mixed $data, string $selectedCurrency): array
    {
        // If data already looks like the formatted mock (keys are plan slugs and values contain 'prices'),
        // return it after ensuring minimal gateway defaults so the frontend can rely on consistent shape.
        if (is_array($data) && !isset($data['data'])) {
            $normalized = [];
            foreach ($data as $key => $plan) {
                // ensure prices currencies are uppercased
                if (isset($plan['prices']) && is_array($plan['prices'])) {
                    $prices = [];
                    foreach ($plan['prices'] as $cur => $p) {
                        $prices[strtoupper($cur)] = $p;
                    }
                    $plan['prices'] = $prices;
                }

                // ensure gateways default structure
                $plan['gateways'] = $plan['gateways'] ?? [
                    'stripe' => ['product_id' => null, 'supported' => false],
                    'pushinpay' => ['reference' => $plan['hash'] ?? null, 'supported' => false, 'amount_override' => null],
                ];

                $normalized[$key] = $plan;
            }

            return $normalized;
        }
        $normalizeAmount = static function ($amount): ?float {
            if ($amount === null) return null;
            if (is_numeric($amount)) return round((float)$amount / (ctype_digit((string)$amount) ? 100 : 1), 2);
            return null;
        };

        $result = [];

        foreach ($data['data'] ?? [] as $plan) {
            // Diagnostic: log which external id fields the backend returned for this plan
            try {
                \Illuminate\Support\Facades\Log::info('StripeGateway::formatPlans - plan external id candidates', [
                    'pages_product_external_id' => $plan['pages_product_external_id'] ?? null,
                    'product_external_id' => $plan['product_external_id'] ?? null,
                    'external_id' => $plan['external_id'] ?? null,
                    'identifier' => $plan['identifier'] ?? null,
                    'hash' => $plan['hash'] ?? null,
                    'name' => $plan['name'] ?? null,
                ]);
            } catch (\Throwable $_) {
                // ignore logging failures
            }
            // Accept multiple possible external id fields from backend.
            // IMPORTANT: prefer the explicit `product_external_id` (main product) coming from backend
            $externalId = $plan['product_external_id'] ?? $plan['external_product_id'] ?? $plan['external_id'] ?? $plan['identifier'] ?? null;

            // If there's no external id, still continue but we'll rely on backend price fallback later
            if (empty($externalId)) {
                // allow processing so fallback to backend price can happen
                $externalId = null;
            }

            // 🔹 Definição da chave do plano
            $key = match ($plan['duration']) {
                'week'  => 'weekly',
                'month' => match ((int)$plan['duration_value']) {
                    3       => 'quarterly',
                    6       => 'semi-annual',
                    default => 'monthly',
                },
                'year'  => 'annual',
                default => $plan['duration'],
            };

            // 🔹 Order Bumps
            $bumps = array_values(array_filter(array_map(function ($order_bump) use ($selectedCurrency, $normalizeAmount) {
                if (empty($order_bump['external_id'])) return null;

                // 🔹 Se é PIX, usar price_order_pix do backend, não buscar do Stripe
                if (($order_bump['payment_method'] ?? null) === 'pix' && isset($order_bump['price_order_pix'])) {
                    $bumpPrice = floatval($order_bump['price_order_pix']);
                    return [
                        'id'          => $order_bump['external_id'],
                        'title'       => $order_bump['title'],
                        'text_button' => $order_bump['text_button'],
                        'description' => $order_bump['description'],
                        'price'       => $bumpPrice,
                        'price_id'    => null,
                        'currency'    => $selectedCurrency,
                        'hash'        => $order_bump['external_id'],
                        'active'      => false,
                        'payment_method' => 'pix',
                    ];
                }

                // 🔹 Para Stripe (card ou sem payment_method), buscar preços da API
                // Diagnostic log for bump price resolution
                \Illuminate\Support\Facades\Log::info('StripeGateway::formatPlans - fetching bump prices', ['bump_external_id' => $order_bump['external_id'], 'selected_currency' => $selectedCurrency]);

                $response = $this->getProductWithPrices($order_bump['external_id']);

                \Illuminate\Support\Facades\Log::info('StripeGateway::formatPlans - bump fetch result', ['bump_external_id' => $order_bump['external_id'], 'status' => $response['status'] ?? 'unknown', 'prices_count' => count($response['prices'] ?? [])]);

                if (($response['status'] ?? 'error') !== 'success') return null;

                $prices = [];
                foreach ($response['prices'] as $p) {
                    $currency = strtoupper($p['currency'] ?? '');
                    if ($currency === '') continue;

                    $amount = $normalizeAmount($p['unit_amount'] ?? $p['amount'] ?? null);
                    if ($amount === null) continue;

                    $prices[$currency] = [
                        'id'       => $p['id'],
                        'price'    => $amount,
                        'recurring' => $p['recurring'] ?? null,
                    ];
                }

                return isset($prices[$selectedCurrency]) ? [
                    'id'          => $order_bump['external_id'],
                    'title'       => $order_bump['title'],
                    'text_button' => $order_bump['text_button'],
                    'description' => $order_bump['description'],
                    'price'       => $prices[$selectedCurrency]['price'],
                    'price_id'    => $prices[$selectedCurrency]['id'],
                    'recurring' => $prices[$selectedCurrency]['recurring'] ?? null,
                    'currency'    => $selectedCurrency,
                    'hash'        => $order_bump['external_id'],
                    'active'      => false,
                    'payment_method' => $order_bump['payment_method'] ?? 'card',
                ] : null;
            }, $plan['order_bumps'] ?? [])));

            // 🔹 Buscar preços do produto principal (use fallback externalId)
            $gatewayResponse = $externalId ? $this->getProductWithPrices($externalId) : ['status' => 'error', 'prices' => []];
            $prices = [];

            // Diagnostic logging: show gateway response status and available currencies
            try {
                $foundCurrencies = array_map(fn($p) => strtoupper($p['currency'] ?? ''), $gatewayResponse['prices'] ?? []);
            } catch (\Throwable $e) {
                $foundCurrencies = [];
            }
            \Illuminate\Support\Facades\Log::info('StripeGateway::formatPlans - product prices fetched', [
                'product_id' => $externalId,
                'status' => $gatewayResponse['status'] ?? 'unknown',
                'currencies' => $foundCurrencies,
                'selected_currency' => $selectedCurrency,
            ]);

            foreach (($gatewayResponse['prices'] ?? []) as $p) {
                $currency = strtoupper($p['currency'] ?? '');
                if ($currency === '') continue;

                $amount = $normalizeAmount($p['unit_amount'] ?? $p['amount'] ?? null);
                if ($amount === null) {
                    \Illuminate\Support\Facades\Log::warning('StripeGateway::formatPlans - price amount null', [
                        'product' => $plan['pages_product_external_id'] ?? null,
                        'price_id' => $p['id'] ?? null,
                        'unit_amount' => $p['unit_amount'] ?? null,
                    ]);
                    continue;
                }

                    $prices[$currency] = [
                        'id'            => $p['id'],
                        'origin_price'  => $plan['price'],
                        'descont_price' => $amount,
                        'recurring'     => $p['recurring'] ?? null,
                    ];
            }

            // Fallback: se não houve preços válidos vindos do Stripe, usa o preço fornecido
            // pelo backend (campo `price`/`total_price`) para evitar plans vazios.
            if (empty($prices)) {
                $fallbackAmount = $normalizeAmount($plan['price'] ?? $plan['total_price'] ?? null);
                if ($fallbackAmount !== null) {
                    $prices[strtoupper($selectedCurrency)] = [
                        'id' => null,
                        'origin_price' => $plan['price'] ?? $plan['total_price'] ?? null,
                        'descont_price' => $fallbackAmount,
                        'recurring' => $plan['recurring'] ?? null,
                    ];
                    \Illuminate\Support\Facades\Log::info('StripeGateway::formatPlans - fallback to backend price used', [
                        'product_id' => $plan['pages_product_external_id'] ?? null,
                        'selected_currency' => $selectedCurrency,
                        'fallback_amount' => $fallbackAmount,
                    ]);
                }
            }

            $result[$key] = [
                'hash'          => $externalId ?? ($plan['identifier'] ?? ($plan['plan_id'] ?? null)),
                'product_external_id' => $externalId ?? null,
                'pages_product_external_id' => $plan['pages_product_external_id'] ?? null,
                'pages_upsell_product_external_id' => $plan['pages_upsell_product_external_id'] ?? null,
                'label'         => (isset($plan['name']) ? ucfirst($plan['name']) : 'Plano') . ' - ' . ($plan['duration_value'] ?? $plan['duration_value'] ?? 1) . '/' . ($plan['duration'] ?? 'period'),
                'nunber_months' => $plan['duration_value'] ?? 1,
                'prices'        => $prices,
                'gateways'      => (function() use ($plan, $normalizeAmount) {
                    $gw = $plan['gateways'] ?? [];
                    if (!empty($gw['pushinpay']['amount_override'])) {
                        $gw['pushinpay']['amount_override'] = $normalizeAmount($gw['pushinpay']['amount_override']);
                    }
                    if (isset($gw['pushinpay']['supported'])) {
                        $gw['pushinpay']['supported'] = (bool) $gw['pushinpay']['supported'];
                    }
                    return $gw;
                })(),
                'upsell_url' => $plan['pages_upsell_url'] ?? null,
                'order_bumps'   => $bumps,
                'pages_upsell_succes_url' => $plan['pages_upsell_succes_url'] ?? null,
                'pages_upsell_fail_url' => $plan['pages_upsell_fail_url'] ?? null,
                'pages_downsell_url' => $plan['pages_downsell_url'] ?? null,
            ];
        }
        return $result;
    }
}
