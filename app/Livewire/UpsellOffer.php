<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UpsellOffer extends Component
{
    public $product;
    public $usingMock = true;
    public $qr_mode = false;
    // Loader / UI flags (to mirror PagePay)
    public $showProcessingModal = false;
    public $isProcessingCard = false;
    public $loadingMessage = null;

    // PIX properties
    public $pixTransactionId;
    public $pixQrImage;
    public $pixQrCodeText;
    public $pixStatus = 'idle';
    public $pixExpiresAt;
    public $pixAmount;
    public $errorMessage;

    public function mount()
    {
        // Load mock plans and find the upsell product
        $mockPath = resource_path('mock/get-plans.json');
        $this->product = [
            'hash' => 'painel_das_garotas',
            'label' => 'Painel das garotas',
            'price' => 3700,
            'currency' => 'BRL',
            'image' => null,
        ];

        if (file_exists($mockPath)) {
            try {
                $json = file_get_contents($mockPath);
                $data = json_decode($json, true);
                if (isset($data['painel_das_garotas'])) {
                    $p = $data['painel_das_garotas'];
                    $this->product['hash'] = $p['hash'] ?? null;
                    $this->product['label'] = $p['label'] ?? $this->product['label'];
                    $this->product['price'] = isset($p['prices']['BRL']['descont_price']) ? (int)round($p['prices']['BRL']['descont_price'] * 100) : $this->product['price'];
                    $this->product['origin_price'] = $p['prices']['BRL']['origin_price'] ?? null;
                    $this->product['recurring'] = $p['prices']['BRL']['recurring'] ?? false;
                    $this->product['image'] = $p['image'] ?? null;
                }
            } catch (\Exception $e) {
                Log::error('UpsellOffer: failed to read mock', ['error' => $e->getMessage()]);
            }
        }

        // If a customerId was provided in the query string (redirect from checkout),
        // try to resolve the customer via Stripe and populate session so getCustomerData()
        // can find the info even if the original session was lost.
        try {
            $customerId = request()->query('customerId');
            if (!empty($customerId)) {
                $gateway = app(\App\Services\PaymentGateways\StripeGateway::class);
                $cust = $gateway->getCustomerById($customerId);
                if (!empty($cust['id'])) {
                    $sess = session('last_order_customer', []);
                    $sess['email'] = $cust['email'] ?? $sess['email'] ?? null;
                    $sess['name'] = $cust['name'] ?? $sess['name'] ?? null;
                    $sess['phone'] = $cust['phone'] ?? $sess['phone'] ?? null;
                    // Store stripe customer id to allow card upsell off-session
                    $sess['stripe_customer_id'] = $cust['id'];
                    session(['last_order_customer' => $sess]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('UpsellOffer: could not resolve customerId from query', ['error' => $e->getMessage()]);
        }
    }

    public function render()
    {
        return view('livewire.upsell-offer');
    }

    public function aproveOffer()
    {
            // Create PIX for this upsell using existing backend controller flow
        try {
            // show loader in UI
            // Use the same loader message as PagePay for PIX flows to keep UX consistent
            $this->loadingMessage = $this->qr_mode ? __('payment.loader_processing') : __('payment.processing_payment');
            $this->isProcessingCard = !$this->qr_mode;
            $this->showProcessingModal = true;
            // Prefer off-session card charge when we have a Stripe customer id
            $sessionCustomer = session('last_order_customer', []);
            $stripeCustomerId = $sessionCustomer['stripe_customer_id'] ?? null;
            // Try to obtain the upsell product external id from backend first
            $upsellProductId = null;
            try {
                $apiUrl = env('STREAMIT_API_URL') ?? 'http://127.0.0.1:8000/api';
                $client = new \GuzzleHttp\Client();

                // First, prefer an explicit query param if it carries a Stripe product id
                $qpUpsell = request()->query('upsell_productId') ?? request()->query('upsell_product_id') ?? null;
                if (!empty($qpUpsell) && preg_match('/^prod_/', (string)$qpUpsell)) {
                    $upsellProductId = $qpUpsell;
                    try {
                        \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('UpsellOffer: using upsell_productId from query param', ['upsellProductId' => $upsellProductId]);
                    } catch (\Throwable $_) {}
                }

                // First try the lightweight lookup endpoint to resolve the plan by upsell URL
                $foundPlan = null;
                try {
                    // Prefer using the Referer header when the current request is a Livewire internal endpoint
                    $currentFull = url()->full();
                    $currentPath = parse_url($currentFull, PHP_URL_PATH) ?: $currentFull;
                    if (str_starts_with($currentPath, '/livewire')) {
                        $referer = request()->server('HTTP_REFERER') ?: request()->headers->get('referer');
                        $useUrl = $referer ?: url()->current();
                    } else {
                        $useUrl = url()->current();
                    }
                    $lookupUrl = rtrim($apiUrl, '/') . '/get-plan-by-upsell?url=' . urlencode($useUrl);
                    $respSingle = $client->request('GET', $lookupUrl, [
                        'headers' => ['Accept' => 'application/json'],
                        'timeout' => 2,
                    ]);
                    $singleRaw = json_decode($respSingle->getBody()->getContents(), true);
                    if (!empty($singleRaw['status']) && !empty($singleRaw['data'])) {
                        $foundPlan = $singleRaw['data'];
                        try {
                            \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('UpsellOffer: plan resolved via get-plan-by-upsell', [
                                'lookupUrl' => $lookupUrl,
                                'pages_upsell_product_external_id' => $foundPlan['pages_upsell_product_external_id'] ?? null,
                            ]);
                        } catch (\Throwable $_) {}
                    }
                } catch (\Throwable $e) {
                    // ignore and fallback to full plans list
                }

                // If the lightweight lookup didn't find a plan, fall back to fetching all plans
                if (!$foundPlan) {
                    $resp = $client->request('GET', rtrim($apiUrl, '/') . '/get-plans?lang=' . (app()->getLocale() ?? 'br'), [
                        'headers' => ['Accept' => 'application/json'],
                        'timeout' => 3,
                    ]);
                    $raw = json_decode($resp->getBody()->getContents(), true);
                    // backend may return data under 'data' or as associative map
                    $plansList = $raw['data'] ?? $raw;
                } else {
                    $plansList = [];
                }
                // Diagnostic: log a short fingerprint of backend response to help debug
                try {
                    \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('UpsellOffer: fetched plans from backend', [
                        'api' => rtrim($apiUrl, '/') . '/get-plans',
                        'plans_count' => is_array($plansList) ? count($plansList) : 0,
                        'sample' => is_array($plansList) && count($plansList) ? array_slice($plansList, 0, 3) : null,
                    ]);
                } catch (\Throwable $_) {
                    // ignore logging failures
                }
                if (is_array($plansList)) {
                    // Prefer to find the plan that has an upsell URL matching this page
                    $currentUrl = url()->current();
                    $currentPath = parse_url($currentUrl, PHP_URL_PATH) ?: $currentUrl;
                    foreach ($plansList as $p) {
                        if (!is_array($p)) continue;
                        // If the plan declares the upsell URL and it matches current page, pick it
                        if (!empty($p['pages_upsell_url'])) {
                            $planPath = parse_url($p['pages_upsell_url'], PHP_URL_PATH) ?: $p['pages_upsell_url'];
                            // Normalize and compare paths (handles host/port differences)
                            $normCurrent = rtrim($currentPath, '/');
                            $normPlan = rtrim($planPath, '/');
                            if ($normCurrent === $normPlan || str_ends_with($normCurrent, $normPlan) || str_ends_with($normPlan, $normCurrent)) {
                                $foundPlan = $p;
                                break;
                            }
                        }
                    }

                    // Fallback: if not found by upsell URL, try known identifiers (hash/identifier/external_id)
                    if (!$foundPlan) {
                        foreach ($plansList as $p) {
                            if (!is_array($p)) continue;
                            if ((isset($p['hash']) && $p['hash'] == ($this->product['hash'] ?? null))
                                || (isset($p['identifier']) && $p['identifier'] == ($this->product['hash'] ?? null))
                                || (isset($p['external_id']) && $p['external_id'] == ($this->product['hash'] ?? null))
                            ) {
                                $foundPlan = $p;
                                break;
                            }
                        }
                    }
                }
                if ($foundPlan) {
                    $upsellProductId = $foundPlan['pages_upsell_product_external_id'] ?? $foundPlan['pages_upsell_product_external'] ?? null;
                    // store the resolved plan and prepare bumps for PIX flow
                    $this->foundPlan = $foundPlan;
                    $this->bumps = [];
                    if (!empty($foundPlan['order_bumps']) && is_array($foundPlan['order_bumps'])) {
                        foreach ($foundPlan['order_bumps'] as $bump) {
                            // consider only PIX bumps
                            if (($bump['payment_method'] ?? 'pix') !== 'pix') continue;
                            $price = isset($bump['original_price']) ? floatval($bump['original_price']) : (isset($bump['price']) ? floatval($bump['price']) : 0);
                            $this->bumps[] = [
                                'id' => $bump['id'] ?? null,
                                'title' => $bump['title'] ?? null,
                                'price' => (int) round($price * 100), // cents
                                'active' => ($bump['active'] ?? true),
                            ];
                        }
                    }
                    try {
                        \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('UpsellOffer: found plan for upsell', [
                            'found_hash' => $foundPlan['hash'] ?? $foundPlan['identifier'] ?? null,
                            'pages_upsell_product_external_id' => $foundPlan['pages_upsell_product_external_id'] ?? null,
                            'pages_upsell_url' => $foundPlan['pages_upsell_url'] ?? null,
                        ]);
                    } catch (\Throwable $_) {
                        // ignore
                    }
                }
            } catch (\Throwable $e) {
                // ignore and fallback to query param / product hash
            }

            // Fallbacks: prefer explicit query param, then product hash
            if (empty($upsellProductId)) {
                $upsellProductId = request()->query('upsell_productId') ?? ($this->product['hash'] ?? null);
                try {
                    \Illuminate\Support\Facades\Log::channel('payment_checkout')->warning('UpsellOffer: upsellProductId fallback to hash or query param', [
                        'upsellProductId' => $upsellProductId,
                        'currentUrl' => url()->current(),
                    ]);
                } catch (\Throwable $_) {
                    // ignore
                }
            }

                // If this component is rendered in QR mode, force PIX flow instead of attempting
                // an off-session Stripe charge. QR pages must always generate PIX on button click.
                if (!$this->qr_mode && !empty($stripeCustomerId) && !empty($upsellProductId)) {
                    // Safety guard: if upsellProductId looks like a local hash (not a Stripe product id),
                    // do not attempt a Stripe off-session charge to avoid wrong amounts.
                    if (!preg_match('/^prod_/', $upsellProductId)) {
                        try {
                            \Illuminate\Support\Facades\Log::channel('payment_checkout')->warning('UpsellOffer: resolved upsellProductId appears to be local hash; aborting card charge', [
                                'upsellProductId' => $upsellProductId,
                                'currentUrl' => url()->current(),
                            ]);
                        } catch (\Throwable $_) {
                            // ignore
                        }
                        $this->errorMessage = 'Não foi possível resolver o produto de upsell para cobrança com cartão. Tente novamente ou use PIX.';
                        return;
                    }
                // Try to charge the customer's default card via StripeGateway
                $gateway = app(\App\Services\PaymentGateways\StripeGateway::class);

                // Resolve price/price_id for the upsell product
                $productResp = $gateway->getProductWithPrices($upsellProductId);
                $cartItem = [
                    'title' => $this->product['label'] ?? 'Upsell',
                    'product_hash' => $upsellProductId,
                    'quantity' => 1,
                ];

                if (($productResp['status'] ?? null) === 'success' && !empty($productResp['prices'])) {
                    $found = null;
                    foreach ($productResp['prices'] as $p) {
                        if (strtoupper($p['currency'] ?? '') === strtoupper($this->product['currency'] ?? 'BRL') || empty($found)) {
                            $found = $p;
                            if (strtoupper($p['currency'] ?? '') === strtoupper($this->product['currency'] ?? 'BRL')) break;
                        }
                    }
                    if (!empty($found) && !empty($found['id'])) {
                        $cartItem['price_id'] = $found['id'];
                        $cartItem['price'] = $found['unit_amount'] ?? null;
                        $cartItem['currency'] = strtoupper($found['currency'] ?? ($this->product['currency'] ?? 'BRL'));
                    }
                }

                if (empty($cartItem['price_id']) && empty($cartItem['price'])) {
                    // Fallback to product price in component (cents)
                    $cartItem['price'] = $this->product['price'] ?? null;
                    $cartItem['currency'] = strtoupper($this->product['currency'] ?? 'BRL');
                }

                $idempotency = md5($upsellProductId . '|' . $stripeCustomerId . '|' . now()->timestamp);

                // Determine upsell URLs: prefer backend-declared values (from foundPlan), then query params
                $upsellSuccessUrl = null;
                $upsellFailedUrl = null;
                
                if (!empty($foundPlan) && is_array($foundPlan)) {
                    // For Stripe/cartão: use pages_upsell_succes_url or pages_upsell_url
                    $upsellSuccessUrl = $foundPlan['pages_upsell_succes_url'] ?? $foundPlan['pages_upsell_url'] ?? null;
                    $upsellFailedUrl = $foundPlan['pages_upsell_fail_url'] ?? null;
                }
                
                // Fallback to query params if backend didn't provide
                if (empty($upsellSuccessUrl)) {
                    $upsellSuccessUrl = request()->query('upsell_success_url') ?? null;
                }
                if (empty($upsellFailedUrl)) {
                    $upsellFailedUrl = request()->query('upsell_failed_url') ?? null;
                }
                
                $paymentPayload = [
                    'customer' => [
                        'id' => $stripeCustomerId,
                        'email' => $sessionCustomer['email'] ?? null,
                        'name' => $sessionCustomer['name'] ?? null,
                    ],
                    'cart' => [ $cartItem ],
                    'metadata' => [ 'is_upsell' => true, 'offer_hash' => $upsellProductId, 'product_external_id' => $upsellProductId ],
                    'upsell_success_url' => $upsellSuccessUrl,
                    'upsell_failed_url' => $upsellFailedUrl,
                ];

                $res = $gateway->processPayment($paymentPayload, $idempotency);
                \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('UpsellOffer: calling StripeGateway::processPayment', [
                    'stripe_customer_id' => $stripeCustomerId,
                    'upsellProductId' => $upsellProductId,
                    'cartItem' => $cartItem,
                    'paymentPayload_meta' => $paymentPayload['metadata'] ?? null,
                ]);

                if (($res['status'] ?? '') === 'success') {
                    $redirect = $res['data']['redirect_url'] ?? ($paymentPayload['upsell_success_url'] ?? url('/upsell/thank-you-card'));
                    // Emit Livewire/browser events instead of redirecting immediately so client
                    // can show the loader for a minimum duration before navigating.
                    $purchaseData = [
                        'transaction_id' => $res['data']['transaction_id'] ?? null,
                        'value' => ($cartItem['price'] ?? null) ? (($cartItem['price'] / 100) ?? null) : null,
                        'currency' => $cartItem['currency'] ?? null,
                        'content_ids' => [$cartItem['product_hash'] ?? null],
                    ];

                    $payload = ['redirect_url' => $redirect, 'purchaseData' => $purchaseData];
                    try {
                        $this->dispatch('checkout-success', $payload);
                        $this->dispatchBrowserEvent('checkout-success', $payload);
                    } catch (\Throwable $_) {
                        // ignore
                    }

                    // Keep modal visible until client handles redirect
                    return;
                }

                // If not success, show error and fallthrough to PIX fallback
                $this->errorMessage = $res['message'] ?? ($res['errors'][0] ?? 'Erro ao realizar cobrança do upsell.');
                try {
                    $this->dispatch('checkout-failed', ['message' => $this->errorMessage]);
                    $this->dispatchBrowserEvent('checkout-failed', ['message' => $this->errorMessage]);
                } catch (\Throwable $_) {}
                // ensure loader hidden
                $this->isProcessingCard = false;
                $this->showProcessingModal = false;
                return;
            }

            // Fallback PIX flow (original behavior)
            $customer = $this->getCustomerData();
            if (!$customer || empty($customer['email'])) {
                $this->errorMessage = 'Dados do cliente não disponíveis. Faça login ou use o mesmo navegador da compra.';
                return;
            }

            // Determine base price (in cents). Prefer backend-declared values when available.
            $basePriceCents = isset($this->product['price']) ? (int)$this->product['price'] : 0;
            if (!empty($foundPlan) && is_array($foundPlan)) {
                // Prefer explicit pages_pix_price if provided by backend (decimal string in BRL)
                if (isset($foundPlan['pages_pix_price']) && $foundPlan['pages_pix_price'] !== null && $foundPlan['pages_pix_price'] !== '') {
                    $pp = floatval($foundPlan['pages_pix_price']);
                    $basePriceCents = (int) round($pp * 100);
                } elseif (isset($foundPlan['gateways']['pushinpay']['amount_override']) && $foundPlan['gateways']['pushinpay']['amount_override'] !== null && $foundPlan['gateways']['pushinpay']['amount_override'] !== '') {
                    $ao = floatval($foundPlan['gateways']['pushinpay']['amount_override']);
                    $basePriceCents = (int) round($ao * 100);
                }
            }

            $bumpsTotal = array_sum(array_map(function($b){ return (isset($b['active']) && $b['active']) ? (int)($b['price'] ?? 0) : 0; }, $this->bumps ?? []));

            // Build cart: main item + each active bump as operation_type 2
            $cart = [];
            $cart[] = [
                'product_hash' => $this->product['hash'] ?? null,
                'title' => $this->product['label'],
                'price' => $basePriceCents,
                'quantity' => 1,
                'operation_type' => 1,
            ];

            if (!empty($this->bumps) && is_array($this->bumps)) {
                foreach ($this->bumps as $b) {
                    if (!isset($b['active']) || !$b['active']) continue;
                    $cart[] = [
                        'product_hash' => ($this->product['hash'] ?? null) . '_bump_' . ($b['id'] ?? uniqid()),
                        'title' => $b['title'] ?? 'Order bump',
                        'price' => (int) ($b['price'] ?? 0),
                        'quantity' => 1,
                        'operation_type' => 2,
                    ];
                }
            }

            // Determine PIX upsell URLs: prefer backend-declared values (from foundPlan), then defaults
            $pixUpsellSuccessUrl = null;
            $pixUpsellFailUrl = null;
            
            if (!empty($foundPlan) && is_array($foundPlan)) {
                // For PIX: use pages_pix_upsell_succes_url or pages_pix_upsell_url
                $pixUpsellSuccessUrl = $foundPlan['pages_pix_upsell_succes_url'] ?? $foundPlan['pages_pix_upsell_url'] ?? null;
                $pixUpsellFailUrl = $foundPlan['pages_pix_upsell_fail_url'] ?? null;
            }

            $pixData = [
                // amount in cents: backend base price + any active bumps
                'amount' => $basePriceCents + $bumpsTotal,
                'currency_code' => $this->product['currency'] ?? 'BRL',
                'plan_key' => $this->product['hash'] ?? 'painel_das_garotas',
                'offer_hash' => $this->product['hash'] ?? 'painel_das_garotas',
                'customer' => [
                    'name' => $customer['name'] ?? 'Cliente',
                    'email' => $customer['email'],
                    'phone_number' => $customer['phone'] ?? null,
                    'document' => $customer['document'] ?? null,
                ],
                'cart' => $cart,
                'metadata' => [
                    'payment_method' => 'pix',
                    'is_upsell' => true,
                    'upsell_success_url' => $pixUpsellSuccessUrl,
                    'upsell_fail_url' => $pixUpsellFailUrl,
                    'webhook_url' => url('/api/pix/webhook'),
                ],
            ];

            // Diagnostic: log the PIX payload and local state before calling backend
            try {
                \Illuminate\Support\Facades\Log::channel('payment_checkout')->info('UpsellOffer: PIX payload prepared', [
                    'pixData' => $pixData,
                    'product_price' => $this->product['price'] ?? null,
                    'bumps' => $this->bumps ?? null,
                    'session_customer' => $sessionCustomer ?? null,
                ]);
            } catch (\Throwable $_) {
                // non-fatal
            }

            // Call PixController directly (same pattern as PagePay)
            $controller = new \App\Http\Controllers\PixController(app(\App\Services\PushingPayPixService::class));
            $request = \Illuminate\Http\Request::create('/api/pix/create', 'POST', $pixData, [], [], [], json_encode($pixData));
            $request->headers->set('Accept', 'application/json');
            $request->headers->set('Content-Type', 'application/json');

            $response = $controller->create($request);
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $json = json_decode($response->getContent(), true);
                if (($json['status'] ?? '') === 'success' && isset($json['data'])) {
                    $d = $json['data'];
                    $this->pixTransactionId = $d['payment_id'] ?? null;
                    $this->pixQrImage = $d['qr_code_base64'] ?? null;
                    $this->pixQrCodeText = $d['qr_code'] ?? null;
                    $this->pixAmount = $d['amount'] ?? ($this->product['price'] / 100);
                    $this->pixExpiresAt = $d['expiration_date'] ?? null;
                    $this->pixStatus = $d['status'] ?? 'pending';

                    // Start polling in browser
                    $this->dispatchBrowserEvent('upsell:pix-generated', ['payment_id' => $this->pixTransactionId]);
                } else {
                    $this->errorMessage = $json['message'] ?? 'Erro ao gerar PIX para upsell.';
                }
            } else {
                $this->errorMessage = 'Resposta inesperada ao gerar PIX.';
            }
        } catch (\Exception $e) {
            Log::error('UpsellOffer: error generating pix', ['exception' => $e->getMessage()]);
            $this->errorMessage = 'Erro ao gerar PIX. Tente novamente.';
        }
    }

    public function declineOffer()
    {
        return redirect('/upsell/thank-you-recused');
    }

    private function getCustomerData()
    {
        // Prefer authenticated user
        $user = Auth::user();
        if ($user) {
            return [
                'name' => $user->name ?? ($user->full_name ?? null),
                'email' => $user->email ?? null,
                'phone' => $user->phone ?? $user->phone_number ?? null,
                'document' => $user->cpf ?? $user->document ?? null,
            ];
        }

        // Fallback to session 'last_order_customer'
        $session = session('last_order_customer');
        if ($session && is_array($session)) {
            return $session;
        }

        return null;
    }
}
