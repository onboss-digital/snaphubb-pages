<?php

namespace App\Livewire;

use App\Factories\PaymentGatewayFactory; // Added
use App\Factories\PixServiceFactory;
use App\Interfaces\PaymentGatewayInterface; // Added
use App\Rules\ValidPhoneNumber;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use App\Services\FacebookConversionsService;

class PagePay extends Component
{

    public $paymentMethodId, $cardName, $cardNumber, $cardExpiry, $cardCvv, $email, $phone, $cpf,
        $pixName, $pixEmail, $pixCpf, $pixPhone,
        
        
        $plans, $modalData, $product, $testimonials = [],
        $utm_source, $utm_medium, $utm_campaign, $utm_id, $utm_term, $utm_content, $src, $sck;

    public $selectedPaymentMethod = 'credit_card';
    public $selectedLanguage = 'br';
    public $selectedCurrency = 'BRL';
    public $selectedPlan = 'monthly';

    // Available languages shown in the UI
    public $availableLanguages = [
        'br' => "🇧🇷 Português",
        'en' => "🇺🇸 English",
        'es' => "🇪🇸 Español",
    ];

    // Currency metadata used by the blade views
    public $currencies = [
        'BRL' => [
            'symbol' => 'R$',
            'name' => 'Real Brasileiro',
            'code' => 'BRL',
            'label' => 'BRL',
        ],
        'USD' => [
            'symbol' => '$',
            'name' => 'US Dollar',
            'code' => 'USD',
            'label' => 'USD',
        ],
        'EUR' => [
            'symbol' => '€',
            'name' => 'Euro',
            'code' => 'EUR',
            'label' => 'EUR',
        ],
    ];

    /**
     * Ao mudar o método de pagamento: valida suporte, atualiza bumps e recalcula totals
     */
    public function updatedSelectedPaymentMethod($value)
    {
        Log::info("updatedSelectedPaymentMethod: Método alterado para '{$value}'");

        // Garantir que temos planos carregados e que o selectedPlan existe
        if (empty($this->plans)) {
            $this->plans = $this->getPlans();
        }
        if (!isset($this->plans[$this->selectedPlan]) && !empty($this->plans)) {
            $first = array_key_first($this->plans);
            if ($first) {
                $this->selectedPlan = $first;
                Log::info('updatedSelectedPaymentMethod: selectedPlan ajustado para primeira opção disponível', ['selectedPlan' => $this->selectedPlan]);
            }
        }

        // Validar se plano selecionado suporta este método
        if (!$this->planSupportsPaymentMethod($this->selectedPlan, $value)) {
            $this->addError(
                'payment_method',
                "O plano selecionado não suporta pagamento via " . ($value === 'pix' ? 'PIX' : 'Cartão')
            );
            Log::warning("updatedSelectedPaymentMethod: Plano não suporta método", [
                'plan' => $this->selectedPlan,
                'method' => $value,
            ]);

            // If PIX is not supported, force-switch back to credit card and recalc totals
            if ($value === 'pix') {
                Log::info('updatedSelectedPaymentMethod: PIX não suportado, revertendo para credit_card', ['plan' => $this->selectedPlan]);
                $this->selectedPaymentMethod = 'credit_card';
                try {
                    $this->loadBumpsByMethod('card');
                } catch (\Throwable $e) {
                    Log::warning('updatedSelectedPaymentMethod: falha ao carregar bumps de card fallback', ['error' => $e->getMessage()]);
                }
                $this->setCardTotals($this->selectedPlan);
            } else {
                // Mesmo que o backend marque o plano como não suportando cartão,
                // atualizamos os totals exibidos para evitar que a UI continue
                // mostrando o preço do PushinPay quando o usuário seleciona 'cartão'.
                try {
                    $this->loadBumpsByMethod('card');
                } catch (\Throwable $e) {
                    Log::warning('updatedSelectedPaymentMethod: falha ao carregar bumps de card fallback', ['error' => $e->getMessage()]);
                }

                $this->setCardTotals($this->selectedPlan);
            }

            return;
        }

        // Limpar erros se o método é válido
        $this->resetErrorBag();

        // Gerenciar order bumps e estado ao alternar métodos
        if ($value === 'pix') {
            // Salvar o estado atual dos bumps de cartão
            $this->lastBumpsState = collect($this->bumps)->pluck('active', 'id')->all();

            // Carregar bumps específicos para PIX
            $this->loadBumpsByMethod('pix');
            $this->calculateTotals();
        } else {
            // Se voltar para cartão de crédito, carregar bumps de cartão
            $this->loadBumpsByMethod('card');
            // Force totals to use admin 'price' for card flows
            $this->setCardTotals($this->selectedPlan);
            // Limpar o estado salvo
            $this->lastBumpsState = [];
        }

        Log::info("updatedSelectedPaymentMethod: Método '{$value}' é suportado e estado atualizado");
    }
    public $bumpActive = false;
    public $bumps = [];

    public $countdownMinutes = 14;
    public $countdownSeconds = 22;
    public $spotsLeft = 12;
    public $activityCount = 0;
    public $totals = [];

    private $lastBumpsState = [];

    // ==========================================
    // DATA ORIGIN TRACKING (Fallback vs Backend)
    // ==========================================
    public $dataOrigin = [
        'plans' => 'fallback',      // 'fallback' ou 'backend'
        'bumps' => 'fallback',      // 'fallback' ou 'backend'
        'totals' => 'fallback',     // 'fallback' ou 'backend'
    ];

    private PaymentGatewayInterface $paymentGateway; // Added

    public $gateway;
    // PIX properties
    public $showPixModal = false;
    public $pixTransactionId = null;
    public $pixQrImage = null;
    public $pixQrCodeText = null;
    public $pixExpiresAt = null;
    public $pixAmount = null;
    public $pixStatus = 'pending';
    public $pixError = null;
    public $pixValidationError = null;
    public $isProcessingCard = false;
    // Modal / UI flags used by the blade views
    public $showUpsellModal = false;
    public $showDownsellModal = false;
    public $showProcessingModal = false;
    public $showSuccessModal = false;
    public $showErrorModal = false;
    public $showSecure = false;
    public $loadingMessage = null;
    public $errorMessage = null;
    // Legacy/typo alias observed in debug data
    public $showLodingModal = false;
    protected $apiUrl;
    private $httpClient;
    private $pixService;
    public $cardValidationError = null;
    public $fieldErrors = [];
    
    public function __construct()
    {
        $this->httpClient = new Client([
            'verify' => !env('APP_DEBUG'), // <- ignora verificação de certificado SSL
        ]);
        $this->apiUrl = config('services.streamit.api_url'); // Assuming you'll store the API URL in config
        $this->gateway = config('services.default_payment_gateway');
        // Usar factory para resolver o serviço PIX dinamicamente
        $this->pixService = PixServiceFactory::make();
    }

    protected function rules()
    {
        $rules = [
            'cardName' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => ['nullable', 'string', new ValidPhoneNumber],
        ];

        if ($this->selectedLanguage === 'br') {
            $rules['cpf'] = ['required', 'string', 'regex:/^\d{3}\.\d{3}\.\d{3}\-\d{2}$|^\d{11}$/'];
        }

        if ($this->gateway !== 'stripe') {
            $rules['cardNumber'] = 'required|numeric|digits_between:13,19';
            $rules['cardExpiry'] = ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/?([0-9]{2})$/'];
            $rules['cardCvv'] = 'required|numeric|digits_between:3,4';
        }

        return $rules;
    }

    public function debug()
    {
        $this->cardName = 'João da Silva';
        $this->cardNumber = '4242424242424242'; // Example Visa card number
        $this->cardExpiry = '12/25'; // Example expiry date
        $this->cardCvv = '123'; // Example CVV
        $this->email = 'test@mail.com';
        $this->phone = '+5511999999999'; // Example phone number
        $this->cpf = '123.456.789-09'; // Example CPF, valid format        
        $this->paymentMethodId = 'pm_1SBQpKIVhGS3bBwFk2Idz2kp'; //'pm_1S5yVwIVhGS3bBwFlcYLzD5X'; //adicione um metodo de pagamento pra testar capture no elements do stripe
    }

    public function mount(?PaymentGatewayInterface $paymentGateway = null) // Modified to allow injection, or resolve via factory
    {
        $this->utm_source = request()->query('utm_source');
        $this->utm_medium = request()->query('utm_medium');
        $this->utm_campaign = request()->query('utm_campaign');
        $this->utm_id = request()->query('utm_id');
        $this->utm_term = request()->query('utm_term');
        $this->utm_content = request()->query('utm_content');
        $this->src = request()->query('src');
        $this->sck = request()->query('sck');

        if (env('APP_DEBUG')) {
            $this->debug();
        }

        // Detecta idioma: preferir valor em sessão, senão usar Accept-Language do request
        $sessionLang = session('user_language', null);
        if ($sessionLang) {
            $this->selectedLanguage = $sessionLang;
        } else {
            $preferred = request()->getPreferredLanguage(['br', 'en', 'es']);
            $code = strtolower(substr((string)$preferred, 0, 2));
            $map = [
                'pt' => 'br',
                'br' => 'br',
                'es' => 'es',
                'en' => 'en',
            ];
            $this->selectedLanguage = $map[$code] ?? config('app.locale', 'br');
            session()->put('user_language', $this->selectedLanguage);
        }
        app()->setLocale($this->selectedLanguage);

        // Sincroniza moeda com idioma
        $this->syncCurrencyWithLanguage();

        $this->testimonials = trans('checkout.testimonials') ?? [];
        
        // Sempre carregar planos da API Stripe para o cartão de crédito
            $this->plans = $this->getPlans(); // Always load plans from the API for credit card

            // Recalcular totals com os planos recarregados para garantir parity entre UI e payload
            try {
                $this->calculateTotals();
                Log::info('PagePay::mount: totals recalculados após reload de planos', ['totals' => $this->totals]);
            } catch (\Exception $e) {
                Log::warning('PagePay::mount: falha ao recalcular totals', ['error' => $e->getMessage()]);
            }

        // Log available plan keys for debugging missing-plan issues
        try {
            Log::info('PagePay::mount - available plan keys', ['keys' => is_array($this->plans) ? array_keys($this->plans) : []]);
        } catch (\Throwable $e) {
            Log::warning('PagePay::mount - failed to log plan keys', ['error' => $e->getMessage()]);
        }

        // Se o plano selecionado padrão não existir no conjunto retornado,
        // usar a primeira chave disponível para evitar valores vazios na UI.
        if (!isset($this->plans[$this->selectedPlan]) && !empty($this->plans)) {
            $first = array_key_first($this->plans);
            if ($first) {
                $this->selectedPlan = $first;
                Log::info('PagePay::mount - selectedPlan ajustado para primeira opção disponível', ['selectedPlan' => $this->selectedPlan]);
            }
        }
        Log::info('PagePay::mount - Carregando planos da API Stripe');

        // Se idioma for Português (BR), selecionar PIX como método padrão
        if ($this->selectedLanguage === 'br') {
            $this->selectedPaymentMethod = 'pix';
            Log::info('PagePay: PIX selecionado automaticamente (idioma BR)');
        }

        // Carregar order bumps do backend para o método atualmente selecionado
        $this->loadBumpsByMethod($this->selectedPaymentMethod);

        // Defensive: sempre garantir que bumps não venham pré-selecionados ao montar
        if (is_array($this->bumps) && count($this->bumps) > 0) {
            $onlyForPix = ($this->selectedPaymentMethod === 'pix');
            $this->bumps = $this->sanitizeBumps($this->bumps, $onlyForPix);
            $this->dataOrigin['bumps'] = $this->dataOrigin['bumps'] ?? 'db';
        }
        
        // Recuperar preferência do usuário, se existir
        $this->selectedPlan = Session::get('selectedPlan', 'monthly');

        // Se o plano da sessão não existir entre os planos carregados, usar a primeira opção disponível
        if (!isset($this->plans[$this->selectedPlan]) && !empty($this->plans)) {
            $first = array_key_first($this->plans);
            if ($first) {
                $this->selectedPlan = $first;
                Log::info('PagePay::mount - session selectedPlan not found, adjusted to first available', ['selectedPlan' => $this->selectedPlan]);
            }
        }
        $this->activityCount = rand(1, 50);

        // Update product details based on the selected plan
        $this->updateProductDetails();
        // If default method is credit_card, ensure totals reflect plan 'price' (Preço*)
        if ($this->selectedPaymentMethod !== 'pix') {
            $this->setCardTotals($this->selectedPlan);
        } else {
            $this->calculateTotals();
        }
    }

    /**
     * Force totals to use plan 'price' for card flows (Preço*)
     */
    private function setCardTotals(string $planKey)
    {
        $plan = $this->plans[$planKey] ?? null;
        if (!$plan) {
            Log::warning('setCardTotals: plan not found', ['plan' => $planKey]);
            $this->totals = [];
            return;
        }

        // Prefer explicit 'price' field (admin Preço*). Fallback to prices structure.
        $currency = $this->selectedCurrency ?? 'BRL';
        $base = 0.0;

        // Primeiro, tentar resolver via Product External Id (unificação com upsell behavior)
        $productExternalId = $plan['product_external_id'] ?? $plan['gateways']['stripe']['product_id'] ?? null;
        if (!empty($productExternalId)) {
            try {
                $resolver = app(\App\Services\StripeProductResolver::class);
                $resolved = $resolver->resolvePriceForProduct($productExternalId, $currency);
                if (is_array($resolved) && isset($resolved['amount_cents'])) {
                    $base = floatval($resolved['amount_cents']) / 100.0;
                    Log::info('setCardTotals: using Stripe product lookup', ['product' => $productExternalId, 'amount' => $base, 'price_id' => $resolved['price_id'] ?? null]);
                }
            } catch (\Throwable $e) {
                Log::warning('setCardTotals: stripe product lookup failed', ['error' => $e->getMessage(), 'product' => $productExternalId]);
            }
        }

        // Se não obteve um valor via product lookup, usar campos locais do plano
        if (empty($base) || $base == 0.0) {
            if (isset($plan['price']) && $plan['price'] !== null && $plan['price'] !== '') {
                // plan['price'] may come formatted (e.g., "24,90"), normalize
                $base = floatval(str_replace(',', '.', str_replace('.', '', (string)$plan['price'])));
            } elseif (isset($plan['prices'][$currency]['origin_price'])) {
                $base = floatval($plan['prices'][$currency]['origin_price']);
            } elseif (isset($plan['prices'][$currency]['descont_price'])) {
                $base = floatval($plan['prices'][$currency]['descont_price']);
            }
        }


        // Use numeric floats here; formatting happens in the Blade view
        $months = intval($plan['nunber_months'] ?? 1) ?: 1;

        // Start from base plan price
        $finalPrice = round($base, 2);

        // Add active bumps (support multiple shapes: 'price', 'original_price', 'amount')
        foreach ($this->bumps as $bump) {
            $isActive = false;
            if (is_array($bump)) {
                $isActive = !empty($bump['active']) || (isset($bump['active']) && $bump['active'] === true);
            } else {
                $isActive = (bool)$bump;
            }

            if ($isActive) {
                $bumpPrice = 0.0;
                if (is_array($bump)) {
                    if (isset($bump['price'])) {
                        $bumpPrice = floatval($bump['price']);
                    } elseif (isset($bump['original_price'])) {
                        $bumpPrice = floatval($bump['original_price']);
                    } elseif (isset($bump['amount'])) {
                        $bumpPrice = floatval($bump['amount']);
                    }
                } else {
                    $bumpPrice = floatval($bump);
                }

                $finalPrice += $bumpPrice;
            }
        }

        $month_price = round($finalPrice / $months, 2);

        $this->totals = [
            'month_price' => $month_price,
            'month_price_discount' => $month_price,
            'total_price' => round($finalPrice, 2),
            'final_price' => round($finalPrice, 2),
            'total_discount' => 0.0,
        ];

        $this->dataOrigin['totals'] = 'backend';
        Log::info('setCardTotals: totals set from plan price', ['plan' => $planKey, 'price' => $base]);
    }

    /**
     * Sincroniza a moeda com o idioma detectado
     * BR → BRL | EN → USD | ES → USD
     */
    private function syncCurrencyWithLanguage()
    {
        $currencyMap = [
            'br' => 'BRL',
            'en' => 'USD',
            'es' => 'USD',
        ];

        $this->selectedCurrency = $currencyMap[$this->selectedLanguage] ?? 'BRL';
        
        // Armazena na sessão para consistência
        Session::put('selectedCurrency', $this->selectedCurrency);
        Session::put('user_language', $this->selectedLanguage);
    }

    /**
     * ✅ NOVO: Valida se o plano suporta o método de pagamento selecionado
     */
    private function planSupportsPaymentMethod($planKey, $paymentMethod)
    {
        $plan = $this->plans[$planKey] ?? null;

        if (!$plan) {
            Log::warning("planSupportsPaymentMethod: Plano não encontrado", ['planKey' => $planKey]);

            // Se houver planos carregados, ajusta selectedPlan para a primeira opção disponível
            if (!empty($this->plans)) {
                $first = array_key_first($this->plans);
                if ($first) {
                    $this->selectedPlan = $first;
                    $plan = $this->plans[$first] ?? null;
                    Log::info('planSupportsPaymentMethod: selectedPlan ajustado para primeira opção disponível', ['selectedPlan' => $this->selectedPlan]);
                }
            }

            if (!$plan) {
                return false;
            }
        }

        // Determinar qual gateway o método de pagamento usa
        $gateway = $paymentMethod === 'pix' ? 'pushinpay' : 'stripe';

        // Verificar se o plano tem o produto registrado para este gateway
        $supported = $plan['gateways'][$gateway]['supported'] ?? false;

        Log::info("planSupportsPaymentMethod: Validando suporte", [
            'plan' => $planKey,
            'method' => $paymentMethod,
            'gateway' => $gateway,
            'supported' => $supported,
        ]);

        return $supported;
    }

    /**
     * ✅ NOVO: Ao mudar o método de pagamento, valida se plano suporta
     */
    // Duplicate removed: consolidated implementation is the single definition

    /**
     * ✅ NOVO: Retorna o Product ID do gateway para o plano selecionado
     */
    private function getGatewayProductId($planKey, $paymentMethod)
    {
        $plan = $this->plans[$planKey] ?? null;
        
        if (!$plan) {
            Log::error("getGatewayProductId: Plano não encontrado", ['planKey' => $planKey]);
            return null;
        }

        $gateway = $paymentMethod === 'pix' ? 'pushinpay' : 'stripe';
        $productId = $plan['gateways'][$gateway]['product_id'] ?? null;

        Log::info("getGatewayProductId: Retornando ID", [
            'plan' => $planKey,
            'gateway' => $gateway,
            'product_id' => $productId,
        ]);

        return $productId;
    }

    /**
     * Carrega Order Bumps do banco de dados
     * Busca bumps por método de pagamento
     */
    private function loadBumps()
    {
        try {
            // Usar Model ao invés de HTTP request para evitar timeout
            // Se os bumps já vieram da API do backend, não sobrescreve-los
            if (($this->dataOrigin['bumps'] ?? null) === 'backend' && !empty($this->bumps)) {
                Log::info('PagePay: loadBumps - bumps já provenientes do backend, mantendo os existentes', ['count' => count($this->bumps)]);
                return;
            }

            $this->bumps = \App\Models\OrderBump::getByPaymentMethod('card');

            Log::info('PagePay: Order bumps carregados do banco', [
                'count' => count($this->bumps),
            ]);
        } catch (\Exception $e) {
            Log::error('PagePay: Exceção ao carregar bumps', [
                'error' => $e->getMessage(),
            ]);
            // Não sobrescrever bumps já carregados pelo backend; se não houver nenhum, usar array vazio
            if (empty($this->bumps)) {
                $this->bumps = [];
                $this->dataOrigin['bumps'] = 'fallback';
            }
        }
    }

    public function getPlans()
    {
        try {
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ];

            $query = http_build_query(['lang' => $this->selectedLanguage]);
            $request = new Request(
                'GET',
                rtrim($this->apiUrl, '/') . '/get-plans' . (!empty($query) ? "?{$query}" : ''),
                $headers,
            );

            $response = $this->httpClient->sendAsync($request)
                ->then(function ($res) {
                    return $res;
                })
                ->otherwise(function ($e) {
                    Log::info('PagePay: GetPlans from streamit failed.', [
                        'gateway' => $this->gateway,
                        'error' => $e->getMessage(),
                    ]);
                    return null;
                })
                ->wait();

            if ($response instanceof \Psr\Http\Message\ResponseInterface) {
                $responseBody = $response->getBody()->getContents();
                Log::info('PagePay::getPlans - raw response received', ['length' => strlen($responseBody)]);
                $dataResponse = json_decode($responseBody, true);
                Log::info('PagePay::getPlans - decoded response keys', ['is_array' => is_array($dataResponse), 'keys' => is_array($dataResponse) ? array_keys($dataResponse) : null]);
                if (is_array($dataResponse) && !empty($dataResponse)) {
                    $this->paymentGateway = app(PaymentGatewayFactory::class)->create();
                    $result = $this->paymentGateway->formatPlans($dataResponse, $this->selectedCurrency);

                    // ✅ Marcar como dados do backend
                    $this->dataOrigin['plans'] = 'backend';

                    if (isset($result[$this->selectedPlan]['order_bumps'])) {
                        $this->bumps = $result[$this->selectedPlan]['order_bumps'];
                        // Only sanitize (prevent preselection) when current method is PIX
                        $this->bumps = $this->sanitizeBumps($this->bumps, ($this->selectedPaymentMethod === 'pix'));
                        // ✅ Se bumps vêm da API, também marcar como backend
                        $this->dataOrigin['bumps'] = 'backend';

                        // Filtrar bumps com preço inválido/zero para fluxos de cartão
                        try {
                            if (($this->selectedPaymentMethod ?? 'credit_card') !== 'pix' && is_array($this->bumps)) {
                                $this->bumps = array_values(array_filter($this->bumps, function ($b) {
                                    $price = 0.0;
                                    if (is_array($b)) {
                                        if (isset($b['price'])) {
                                            $price = floatval($b['price']);
                                        } elseif (isset($b['original_price'])) {
                                            $price = floatval($b['original_price']);
                                        } elseif (isset($b['amount'])) {
                                            $price = floatval($b['amount']);
                                        }
                                    } else {
                                        $price = floatval($b);
                                    }
                                    return $price > 0.0;
                                }));
                            }
                        } catch (\Throwable $e) {
                            Log::warning('PagePay: Falha ao filtrar bumps do backend', ['error' => $e->getMessage()]);
                        }
                    } else {
                        $this->bumps = [];
                    }

                    return $result;
                }
            }

            // If API returned nothing, log and return empty plans (do NOT use local hardcoded mock)
            Log::warning('PagePay: No plans available from API. Returning empty plans (mock disabled).');
            $this->dataOrigin['plans'] = 'fallback';
            $this->dataOrigin['bumps'] = 'fallback';
            return [];
        } catch (\Exception $e) {
            Log::error('PagePay: Critical error in getPlans. Using mock as fallback.', [
                'gateway' => $this->gateway,
                'error' => $e->getMessage(),
            ]);
            
            // On exception, do not load local mock data. Return empty plans instead.
            Log::warning('PagePay: Exception while fetching plans, returning empty plans (mock disabled).');
            $this->dataOrigin['plans'] = 'fallback';
            $this->dataOrigin['bumps'] = 'fallback';
            return [];
        }
    }

    // getPlansFromMock removed — PIX flows use backend-only

    // calculateTotals, startCheckout, rejectUpsell, acceptUpsell remain largely the same
    // but sendCheckout and prepareCheckoutData will be modified.

    public function calculateTotals()
    {
        // Se estamos em PIX, já temos $this->plans do MOCK
        // Se estamos em Stripe, temos da API
        // Não precisa recarregar aqui
        
        if (empty($this->plans)) {
            Log::warning('calculateTotals: Nenhum plano carregado', [
                'method' => $this->selectedPaymentMethod,
                'selected_plan' => $this->selectedPlan
            ]);
            return;
        }
        
        // 1. Verificamos se o plano selecionado realmente existe
        if (!isset($this->plans[$this->selectedPlan])) {
            // Tentativa de fallback para a primeira opção disponível
            if (!empty($this->plans)) {
                $first = array_key_first($this->plans);
                Log::warning('calculateTotals: selectedPlan not found, switching to first available', ['requested' => $this->selectedPlan, 'switched_to' => $first]);
                $this->selectedPlan = $first;
            } else {
                Log::error('Plano selecionado não encontrado e não há planos disponíveis.', [
                    'selected_plan' => $this->selectedPlan,
                    'available_plans' => implode(', ', array_keys($this->plans))
                ]);
                return;
            }
        }
        $plan = $this->plans[$this->selectedPlan];

        // 2. Verificamos se existe um array de preços para o plano
            // If the selected method is NOT PIX (i.e., card), always force totals from plan (admin Preço*)
            if ($this->selectedPaymentMethod !== 'pix') {
                $this->setCardTotals($this->selectedPlan);
                return;
            }

            if (!isset($plan['prices']) || !is_array($plan['prices'])) {
            Log::error('Array de preços não encontrado para o plano.', [
                'plan' => $this->selectedPlan
            ]);
            return;
        }

        // 3. Verificamos se a moeda atual tem um preço definido. Se não, tentamos um fallback.
        $availableCurrency = null;
        if (isset($plan['prices'][$this->selectedCurrency])) {
            $availableCurrency = $this->selectedCurrency;
        } elseif (isset($plan['prices']['BRL'])) {
            // Tenta BRL como primeira alternativa
            $availableCurrency = 'BRL';
        } elseif (isset($plan['prices']['USD'])) {
            // Tenta USD como segunda alternativa
            $availableCurrency = 'USD';
        }
        
        // 4. Se nenhuma moeda válida foi encontrada, interrompemos
    if (is_null($availableCurrency)) {
        Log::error('Nenhuma moeda válida (BRL, USD, etc.) encontrada para o plano.', [
            'plan' => $this->selectedPlan
        ]);
        // Opcional: Adiciona uma mensagem de erro para o usuário
        $this->addError('totals', 'Não foi possível carregar os preços. Tente novamente mais tarde.');
        return;
    }

    $this->selectedCurrency = $availableCurrency;
    $prices = $plan['prices'][$this->selectedCurrency];

    // Se o método selecionado for PIX e existe amount_override no gateway PushinPay,
    // priorizamos esse valor como preço base do plano (single source for PIX).
    $pushAmount = $plan['gateways']['pushinpay']['amount_override'] ?? null;
    $pushSupported = (bool)($plan['gateways']['pushinpay']['supported'] ?? false);

    // Diagnóstico: logar situação atual de seleção e presença de amount_override
    Log::info('calculateTotals: diagnostic', [
        'selectedPaymentMethod' => $this->selectedPaymentMethod,
        'pushAmount' => $pushAmount,
        'pushSupported' => $pushSupported,
        'selectedCurrency' => $this->selectedCurrency,
        'available_prices' => array_keys($plan['prices'] ?? []),
    ]);

    if ($this->selectedPaymentMethod === 'pix' && $pushAmount !== null && $pushSupported === true) {
        $pushAmount = floatval($pushAmount);

        $this->totals = [
            'month_price' => $pushAmount / ($plan['nunber_months'] ?? 1),
            'month_price_discount' => $pushAmount / ($plan['nunber_months'] ?? 1),
            'total_price' => $pushAmount,
            'total_discount' => 0,
        ];

        $finalPrice = $pushAmount;

        foreach ($this->bumps as $bump) {
            $isActive = false;
            if (is_array($bump)) {
                $isActive = !empty($bump['active']) || (isset($bump['active']) && $bump['active'] === true);
            } else {
                $isActive = (bool)$bump;
            }

            if ($isActive) {
                $bumpPrice = null;
                if (is_array($bump)) {
                    if (isset($bump['price'])) {
                        $bumpPrice = $bump['price'];
                    } elseif (isset($bump['original_price'])) {
                        $bumpPrice = $bump['original_price'];
                    } elseif (isset($bump['amount'])) {
                        $bumpPrice = $bump['amount'];
                    }
                } else {
                    $bumpPrice = floatval($bump);
                }

                $finalPrice += floatval($bumpPrice ?? 0);
            }
        }

        $this->totals['final_price'] = round(floatval($finalPrice), 2);
        $this->totals = array_map(function ($value) {
            return round(floatval($value), 2);
        }, $this->totals);

        $this->dataOrigin['totals'] = $this->dataOrigin['plans'];
        return;
    }

    // Daqui para baixo, o código original continua, pois agora temos certeza que a variável $prices existe
    $this->totals = [
        'month_price' => $prices['origin_price'] / $plan['nunber_months'],
        'month_price_discount' => $prices['descont_price'] / $plan['nunber_months'],
        'total_price' => $prices['origin_price'],
        'total_discount' => $prices['origin_price'] - $prices['descont_price'],
    ];

    $finalPrice = $prices['descont_price'];

    foreach ($this->bumps as $bump) {
        $isActive = false;
        if (is_array($bump)) {
            $isActive = !empty($bump['active']) || (isset($bump['active']) && $bump['active'] === true);
        } else {
            $isActive = (bool)$bump;
        }

        if ($isActive) {
            // Support multiple bump price shapes: 'price' (from gateway), 'original_price' (from DB), or fallback to 0
            $bumpPrice = null;
            if (is_array($bump)) {
                if (isset($bump['price'])) {
                    $bumpPrice = $bump['price'];
                } elseif (isset($bump['original_price'])) {
                    $bumpPrice = $bump['original_price'];
                } elseif (isset($bump['amount'])) {
                    $bumpPrice = $bump['amount'];
                }
            } else {
                $bumpPrice = floatval($bump);
            }

            $finalPrice += floatval($bumpPrice ?? 0);
        }
    }

    $this->totals['final_price'] = $finalPrice;

    // Keep numeric totals (float) for safe further processing and formatting in the view
    $this->totals = array_map(function ($value) {
        return round(floatval($value), 2);
    }, $this->totals);

    // ✅ Se chegou aqui, totals foram calculados com dados válidos
    $this->dataOrigin['totals'] = $this->dataOrigin['plans'];
}

    /**
     * Valida todos os campos do cartão de crédito
     * Retorna true se válido, false se houver erros
     */
    private function validateCardFields()
    {
        $this->fieldErrors = [];
        
        // Validar nome do titular
        if (empty($this->cardName) || strlen(trim($this->cardName)) === 0) {
            $this->fieldErrors['cardName'] = __('payment.card_name_required');
        }
        
        // Validar email
        if (empty($this->email)) {
            $this->fieldErrors['email'] = __('payment.email_required');
        } elseif (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $this->fieldErrors['email'] = __('payment.email_invalid');
        }
        
        // Validar telefone (OPCIONAL - valida apenas se preenchido)
        if (!empty($this->phone)) {
            $cleanPhone = preg_replace('/[^0-9+]/', '', $this->phone);
            if (strlen($cleanPhone) < 10) {
                $this->fieldErrors['phone'] = __('payment.phone_invalid');
            }
        }
        
        // Validar CPF (se BR - OPCIONAL para Stripe, OBRIGATÓRIO para outros gateways)
        if ($this->selectedLanguage === 'br' && $this->gateway !== 'stripe') {
            if (empty($this->cpf)) {
                $this->fieldErrors['cpf'] = __('payment.cpf_required');
            } else {
                $cpfClean = preg_replace('/\D/', '', $this->cpf);
                if (strlen($cpfClean) != 11) {
                    $this->fieldErrors['cpf'] = __('payment.cpf_invalid');
                }
            }
        }
        
        // Validar campos do cartão (se não for Stripe)
        if ($this->gateway !== 'stripe') {
            // Número do cartão
            if (empty($this->cardNumber)) {
                $this->fieldErrors['cardNumber'] = __('payment.card_number_required');
            } else {
                $cardClean = preg_replace('/\D/', '', $this->cardNumber);
                if (strlen($cardClean) < 13 || strlen($cardClean) > 19) {
                    $this->fieldErrors['cardNumber'] = __('payment.card_number_invalid');
                }
            }
            
            // Data de expiração
            if (empty($this->cardExpiry)) {
                $this->fieldErrors['cardExpiry'] = __('payment.card_expiry_required');
            } else {
                // Verifica formato MM/AA ou MMAA
                if (!preg_match('/^(0[1-9]|1[0-2])\/?([0-9]{2})$/', $this->cardExpiry)) {
                    $this->fieldErrors['cardExpiry'] = __('payment.card_expiry_invalid');
                }
            }
            
            // CVV
            if (empty($this->cardCvv)) {
                $this->fieldErrors['cardCvv'] = __('payment.card_cvv_required');
            } else {
                $cvvClean = preg_replace('/\D/', '', $this->cardCvv);
                if (strlen($cvvClean) < 3 || strlen($cvvClean) > 4) {
                    $this->fieldErrors['cardCvv'] = __('payment.card_cvv_invalid');
                }
            }
        }
        
        // Se houver erros, define mensagem genérica e faz scroll
        if (!empty($this->fieldErrors)) {
            $this->cardValidationError = __('payment.complete_card_fields');
            $this->dispatch('scroll-to-card-form');
            return false;
        }
        
        return true;
    }

    public function startCheckout()
    {
        Log::debug('startCheckout called', ['selectedPaymentMethod' => $this->selectedPaymentMethod]);

        // Limpar mensagem de validação anterior (cartão)
        $this->cardValidationError = null;
        $this->isProcessingCard = false;

        // ✅ Validação COMPLETA de todos os campos (ANTES de mostrar o loader)
        if (!$this->validateCardFields()) {
            return; // Se houver erros, retorna sem mostrar loader
        }

        // Proceed with credit card logic, ensuring totals are up-to-date

        // Recalcular totals antes de iniciar o checkout
        $this->calculateTotals();
        if ($this->cardNumber) {
            $this->cardNumber = preg_replace('/\D/', '', $this->cardNumber);
        }
        if ($this->cardCvv) {
            $this->cardCvv = preg_replace('/\D/', '', $this->cardCvv);
        }
        if ($this->phone) {
            $this->phone = preg_replace('/[^0-9+]/', '', $this->phone);
        }
        if ($this->cpf && $this->selectedLanguage === 'br') {
            $cpf = preg_replace('/\D/', '', $this->cpf);
            if (strlen($cpf) == 11) {
                $this->cpf = substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
            }
        }

        try {
            $this->showSecure = true;
            $this->loadingMessage = __('payment.processing_payment');
            $this->isProcessingCard = true;

            // Lógica de Upsell/Downsell para cartão
            switch ($this->selectedPlan) {
                case 'monthly':
                case 'quarterly':
                    if (isset($this->plans['semi-annual'])) {
                        $this->showUpsellModal = true;
                        $offerValue = round(
                            (float)$this->plans['semi-annual']['prices'][$this->selectedCurrency]['descont_price']
                                / $this->plans['semi-annual']['nunber_months'],
                            1
                        );

                        $offerDiscont = (
                            (float)$this->plans[$this->selectedPlan]['prices'][$this->selectedCurrency]['origin_price']
                            * $this->plans['semi-annual']['nunber_months']
                        ) - ($offerValue * $this->plans['semi-annual']['nunber_months']);

                        $this->modalData = [
                            'actual_month_value'    => $this->totals['month_price_discount'],
                            'offer_month_value'     => number_format((float)$offerValue, 2, ',', '.'),
                            'offer_total_discount'  => number_format((float)$offerDiscont, 2, ',', '.'),
                            'offer_total_value'     => number_format(
                                (float)$this->plans['semi-annual']['prices'][$this->selectedCurrency]['descont_price'],
                                2,
                                ',',
                                '.'
                            ),
                        ];
                        break;
                    }
                default:
                    $this->showProcessingModal = true;
                    $this->sendCheckout();
                    return;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->isProcessingCard = false;
            $this->dispatch('validation:failed');
            throw $e;
        } catch (\Exception $e) {
            $this->isProcessingCard = false;
            Log::error('start_checkout: API Error:', [
                'message' => $e->getMessage(),
            ]);
        }

    }

    public function rejectUpsell()
    {
        $this->showUpsellModal = false;
        // Logic for downsell offer (quarterly)
        if ($this->selectedPlan === 'monthly') { // Only show downsell if current plan is monthly
            $offerValue = round((float)$this->plans['quarterly']['prices'][$this->selectedCurrency]['descont_price'] / $this->plans['quarterly']['nunber_months'], 1);
            // Corrected discount calculation for downsell
            $basePriceForDiscountCalc = (float)$this->plans['monthly']['prices'][$this->selectedCurrency]['origin_price']; // Price of the plan they *were* on
            $offerDiscont = ($basePriceForDiscountCalc * $this->plans['quarterly']['nunber_months']) - ($offerValue * $this->plans['quarterly']['nunber_months']);

            $this->modalData = [
                'actual_month_value' => $this->totals['month_price_discount'], // This should be from the current 'monthly' plan
                'offer_month_value' => number_format((float)$offerValue, 2, ',', '.'),
                'offer_total_discount' => number_format(abs((float)$offerDiscont), 2, ',', '.'), // Ensure positive discount
                'offer_total_value' => number_format((float)$this->plans['quarterly']['prices'][$this->selectedCurrency]['descont_price'], 2, ',', '.'),
            ];
            $this->showDownsellModal = true;
        } else { // If they were on quarterly and rejected upsell to semi-annual, just proceed with quarterly
            $this->sendCheckout();
        }
    }

    public function acceptUpsell()
    {
        $this->selectedPlan = 'semi-annual';
        $this->updateProductDetails(); // Ensure product details are updated
        $this->calculateTotals();
        $this->showUpsellModal = false;
        $this->sendCheckout();
    }

    public function sendCheckout()
    {
        //$this->showDownsellModal = $this->showUpsellModal = false;
        $this->loadingMessage = __('payment.processing_payment');

        $checkoutData = $this->prepareCheckoutData();
        
        // ✅ NOVO: Determinar qual gateway usar baseado no método de pagamento
        // Se PIX, usar Push in Pay; se cartão, usar Stripe
        $gatewayType = $this->selectedPaymentMethod === 'pix' ? 'pushinpay' : 'stripe';
        
        // ✅ NOVO: Adicionar o Product ID do gateway aos dados de checkout
        $productId = $this->getGatewayProductId($this->selectedPlan, $this->selectedPaymentMethod);
        if ($productId) {
            $checkoutData['product_id'] = $productId;
            Log::info("sendCheckout: Usando Product ID do gateway", [
                'gateway' => $gatewayType,
                'product_id' => $productId,
                'payment_method' => $this->selectedPaymentMethod,
            ]);
        }
        
        $this->paymentGateway = app(PaymentGatewayFactory::class)->create($gatewayType);
        $response = $this->paymentGateway->processPayment($checkoutData);

        if ($response['status'] === 'success') {
            Log::info('PagePay: Payment successful via gateway.', [
                'gateway' => get_class($this->paymentGateway),
                'response' => $response
            ]);

            $this->showSuccessModal = true;
            $this->showProcessingModal = false; // Ensure it's hidden on erro
            $this->showErrorModal = false;

            // Prepare data for the Purchase event
            $purchaseData = [
                'transaction_id' => $response['transaction_id'] ?? $response['data']['transaction_id'] ?? null,
                'value' => $checkoutData['amount'] / 100,
                'currency' => $checkoutData['currency_code'],
                'content_ids' => array_map(function ($item) {
                    return $item['product_hash'];
                }, $checkoutData['cart']),
                'content_type' => 'product',
            ];

            // Server-side: send Purchase event to Facebook Conversions API for each configured pixel
            try {
                $fbIds = [];
                if (env('FB_PIXEL_IDS')) {
                    $fbIds = array_filter(array_map('trim', explode(',', env('FB_PIXEL_IDS'))));
                } elseif (env('FB_PIXEL_ID')) {
                    $fbIds = [env('FB_PIXEL_ID')];
                }

                if (!empty($fbIds)) {
                    $fbService = app(FacebookConversionsService::class);
                    foreach ($fbIds as $pixelId) {
                        $fbService->sendPurchaseEvent($pixelId, [
                            'value' => $purchaseData['value'],
                            'currency' => $purchaseData['currency'],
                            'event_id' => $purchaseData['transaction_id'],
                            'email' => $checkoutData['customer']['email'] ?? null,
                            'phone' => $checkoutData['customer']['phone_number'] ?? null,
                            'client_ip' => request()->ip(),
                            'user_agent' => request()->userAgent(),
                            'content_ids' => $purchaseData['content_ids'],
                            'content_type' => $purchaseData['content_type'],
                            'event_source_url' => url()->current(),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('FB Purchase CAPI call failed in sendCheckout', ['error' => $e->getMessage()]);
            }

            // Persist purchase identifiers in session so "Obrigado" pages can emit client events once
            try {
                session()->put('last_order_transaction', $purchaseData['transaction_id']);
                session()->put('last_order_amount', $purchaseData['value']);
            } catch (\Throwable $_) {
                // ignore session failures
            }

            // Dispatch the event to the browser (Livewire emit and browser event)
            $this->dispatch('checkout-success', purchaseData: $purchaseData);
            try {
                $this->dispatchBrowserEvent('checkout-success', $purchaseData);
            } catch (\Exception $e) {
                Log::warning('Could not dispatch browser event checkout-success: ' . $e->getMessage());
            }

                // Preferir URL de sucesso configurada no plano quando disponível
                $plan = $this->plans[$this->selectedPlan] ?? null;
                // Preferir a URL de upsell configurada diretamente no plano
                // (campo `pages_upsell_url`) para redirecionamento após
                // compra do produto principal. Em seguida, considerar outras
                // variantes históricas/alternativas.
                $possibleKeys = [
                    'pages_upsell_url',
                    'upsell_url',
                    'pages_upsell_succes_url', // historical typo
                    'pages_upsell_success_url', // alternative
                    'pages_upsell_succes',
                    'pages_upsell_success',
                    'upsell_success_url',
                ];

                $planRedirect = null;
                $chosenKey = null;
                if ($plan && is_array($plan)) {
                    foreach ($possibleKeys as $k) {
                        if (!empty($plan[$k])) {
                            $planRedirect = $plan[$k];
                            $chosenKey = $k;
                            break;
                        }
                    }
                }

                $customerId = $response['data']['customerId'] ?? null;
                // Prefer plan-defined pages_upsell_product_external_id to ensure redirect carries the Stripe product id
                $upsell_productId = $plan['pages_upsell_product_external_id'] ?? ($response['data']['upsell_productId'] ?? null);

                if (!empty($planRedirect)) {
                    Log::info('sendCheckout: usando URL de sucesso do plano para redirecionamento', ['plan' => $this->selectedPlan, 'url' => $planRedirect, 'field' => $chosenKey]);
                    $queryParams = http_build_query(array_filter([
                        'customerId' => $customerId,
                        'upsell_productId' => $upsell_productId,
                    ]));
                    return redirect()->to($planRedirect . (empty($queryParams) ? '' : '?' . $queryParams));
                }

                // Caso não haja URL no plano, usar a URL retornada pelo gateway (se houver)
                if (isset($response['data']['redirect_url']) && !empty($response['data']['redirect_url'])) {
                    $redirectUrl = $response['data']['redirect_url'];
                    Log::info('sendCheckout: nenhum URL do plano encontrado; usando redirect_url do gateway', ['redirect_url' => $redirectUrl]);
                    $queryParams = http_build_query(array_filter([
                        'customerId' => $customerId,
                        'upsell_productId' => $upsell_productId,
                    ]));
                    return redirect()->to($redirectUrl . (empty($queryParams) ? '' : '?' . $queryParams));
                }

                // Último fallback: rota local padrão
                $redirectUrl = $response['redirect_url'] ?? rtrim(config('app.url') ?? '', '/') . '/upsell/painel-das-garotas-card';
                Log::warning('sendCheckout: nenhum redirect_url disponível; usando fallback local', ['fallback' => $redirectUrl]);
                return redirect()->to($redirectUrl);
        } else {
            Log::error('PagePay: Payment failed via gateway.', [
                'gateway' => get_class($this->paymentGateway),
                'response' => $response
            ]);
            $errorMessage = $response['message'] ?? 'An unknown error occurred during payment.';
            if (!empty($response['errors'])) {
                $errorMessage .= ' Details: ' . implode(', ', (array)$response['errors']);
            }

            // Emit a Livewire event so the frontend can show a localized loader/failure message
            try {
                $redirect = $response['redirect_url'] ?? null;
                $this->dispatch('checkout-failed', message: $errorMessage, redirect_url: $redirect);
            } catch (\Throwable $_) {
                // ignore
            }

            $this->addError('payment', $errorMessage);
            // Potentially show a generic error modal or message on the page
            $this->showErrorModal = true;
            $this->showProcessingModal = false; // Ensure it's hidden on erro
        }
    }


    private function prepareCheckoutData()
    {
        $customerData = [
            'name' => $this->cardName,
            'email' => $this->email,
            'phone_number' => preg_replace('/[^0-9+]/', '', $this->phone),
        ];
        if ($this->selectedLanguage === 'br' && $this->cpf) {
            $customerData['document'] = preg_replace('/\D/', '', $this->cpf);
        }

        // Lógica existente para cartão de crédito
        $numeric_final_price = floatval(str_replace(',', '.', str_replace('.', '', $this->totals['final_price'])));

        $expMonth = null;
        $expYear = null;
        if ($this->cardExpiry) {
            $parts = explode('/', $this->cardExpiry);
            $expMonth = $parts[0] ?? null;
            if (!empty($parts[1])) {
                $expYear = (strlen($parts[1]) == 2) ? '20' . $parts[1] : $parts[1];
            }
        }

        $cartItems = [];
        $currentPlanDetails = $this->plans[$this->selectedPlan];
        $currentPlanPriceInfo = $currentPlanDetails['prices'][$this->selectedCurrency];

        $cartItems[] = [
            'product_hash' => $currentPlanDetails['hash'],
            'title' => $this->product['title'] . ' - ' . $currentPlanDetails['label'],
            'price' => (int)round(floatval($currentPlanPriceInfo['descont_price']) * 100),
            'price_id' => $this->product['price_id'] ?? null,
            'recurring' => $this->plans[$this->selectedPlan]['prices'][$this->selectedCurrency]['recurring'] ?? null,
            'quantity' => 1,
            'operation_type' => 1,
        ];

        foreach ($this->bumps as $bump) {
            if (!empty($bump['active'])) {
                $cartItems[] = [
                    'product_hash' => $bump['hash'],
                    'price_id' => $bump['price_id'] ?? null,
                    'title' => $bump['title'],
                    'price' => (int)round(floatval($bump['price']) * 100),
                    'recurring' => $bump['recurring'] ?? null,
                    'quantity' => 1,
                    'operation_type' => 2,
                ];
            }
        }

        $cardDetails = [
            'number' => $this->cardNumber,
            'holder_name' => $this->cardName,
            'exp_month' => $expMonth,
            'exp_year' => $expYear,
            'cvv' => $this->cardCvv,
        ];
        if ($this->selectedLanguage === 'br' && $this->cpf) {
            $cardDetails['document'] = preg_replace('/\D/', '', $this->cpf);
        }

        $baseData = [
            'amount' => (int)round($numeric_final_price * 100),
            'currency_code' => $this->selectedCurrency,
            'offer_hash' => $currentPlanDetails['hash'],
            'upsell_url' => $currentPlanDetails['upsell_url'] ?? null,
            // Explicit plan URLs for success/failure (used by backend to redirect)
            'upsell_success_url' => $currentPlanDetails['pages_upsell_succes_url'] ?? $currentPlanDetails['upsell_url'] ?? null,
            'upsell_failed_url' => $currentPlanDetails['pages_upsell_fail_url'] ?? null,
            'upsell_offer_refused_url' => $currentPlanDetails['pages_downsell_url'] ?? null,
            'payment_method' => $this->selectedPaymentMethod,
            'customer' => $customerData,
            'cart' => $cartItems,
            'installments' => 1,
            'selected_plan_key' => $this->selectedPlan,
            'language' => $this->selectedLanguage,
            'metadata' => [
                'product_main_hash' => $this->product['hash'],
                'bumps_selected' => collect($this->bumps)->where('active', true)->pluck('id')->implode(','),
                'utm_source' => $this->utm_source,
                'utm_medium' => $this->utm_medium,
                'utm_campaign' => $this->utm_campaign,
                'utm_id' => $this->utm_id,
                'utm_term' => $this->utm_term,
                'utm_content' => $this->utm_content,
                'src' => $this->src,
                'sck' => $this->sck,
            ]
        ];

        // Ensure we send the gateway product external id so server/gateway can resolve Stripe Price
        $productExternal = $currentPlanDetails['product_external_id'] ?? $currentPlanDetails['gateways']['stripe']['product_id'] ?? null;
        if (!empty($productExternal)) {
            $baseData['metadata']['product_external_id'] = $productExternal;
            Log::info('prepareCheckoutData: attaching product_external_id to metadata', ['product_external_id' => $productExternal]);
        } else {
            Log::warning('prepareCheckoutData: product_external_id not found in plan; gateway price resolution may fallback', ['plan' => $this->selectedPlan]);
        }

        // Attach upsell product external id when available so backend/gateway can
        // resolve the correct Stripe product for upsell flows.
        $upsellExternal = $currentPlanDetails['pages_upsell_product_external_id']
            ?? $currentPlanDetails['pages_upsell_product_external']
            ?? $currentPlanDetails['gateways']['stripe']['upsell_product_id']
            ?? null;
        if (!empty($upsellExternal)) {
            $baseData['metadata']['upsell_product_external_id'] = $upsellExternal;
            Log::info('prepareCheckoutData: attaching upsell_product_external_id to metadata', ['upsell_product_external_id' => $upsellExternal]);
        }

        $baseData['payment_method_id'] = $this->paymentMethodId;
        $baseData['card'] = $cardDetails;

        return $baseData;
    }


    public function closeModal()
    {
        $this->showProcessingModal = false;
        $this->showErrorModal = false;
        $this->showSuccessModal = false;
        $this->selectedPaymentMethod = 'credit_card';
        $this->showPixModal = false;
        $this->pixQrImage = null;
        $this->pixQrCodeText = null;
        $this->dispatch('pix-modal-closed');
    }

    public function decrementTimer()
    {
        if ($this->countdownSeconds > 0) {
            $this->countdownSeconds--;
        } elseif ($this->countdownMinutes > 0) {
            $this->countdownSeconds = 59;
            $this->countdownMinutes--;
        }
    }

    public function acceptDownsell()
    {
        $this->selectedPlan = 'quarterly'; // Assuming downsell is always to quarterly
        $this->updateProductDetails();
        $this->calculateTotals();
        $this->showDownsellModal = false;
        $this->sendCheckout();
    }

    public function rejectDownsell()
    {
        $this->showDownsellModal = false;
        $this->sendCheckout();
    }

    public function getListeners()
    {
        return [
            'updatePhone' => 'updatePhone',
            'checkPixPaymentStatus' => 'checkPixPaymentStatus',
            // Evento disparado pelo client-side para iniciar fluxo com loader
            'clientGeneratePix' => 'generatePixPayment',
        ];
    }

    /**
     * Gera um novo pagamento PIX
     * Valida dados, cria a transação e prepara modal de exibição
     */
    public function generatePix()
    {
        // PIX está disponível apenas em Português (Brasil)
        if ($this->selectedLanguage !== 'br') {
            $this->addError('pix', __('payment.pix_only_portuguese'));
            return;
        }

            // Validação de dados obrigatórios
            // Permitir que o usuário preencha os campos do modal PIX (`pixEmail`, `pixName`)
            // e mapear para os campos principais esperados pela lógica do checkout.
            if (empty($this->email) && !empty($this->pixEmail)) {
                $this->email = $this->pixEmail;
            }
            if (empty($this->cardName) && !empty($this->pixName)) {
                $this->cardName = $this->pixName;
            }

            $this->validate([
                'email' => 'required|email',
                'cardName' => 'required|string|max:255',
            ], [
                'email.required' => __('payment.email_required'),
                'email.email' => __('payment.email_invalid'),
                'cardName.required' => __('payment.card_name_required'),
            ]);

        try {
            // Limpa erros anteriores
            $this->pixError = null;
            $this->pixStatus = 'pending';

            // Carregar planos do backend (sem utilizar mock local)
            if (empty($this->plans) || !isset($this->plans[$this->selectedPlan])) {
                $this->plans = $this->getPlans();
            }

            if (!isset($this->plans[$this->selectedPlan])) {
                Log::error('generatePix: Plano não encontrado (backend)', [
                    'selectedPlan' => $this->selectedPlan,
                    'available_plans' => array_keys($this->plans ?? [])
                ]);
                $this->errorMessage = __('payment.plan_not_loaded') ?? 'Plano não disponível no momento. Tente novamente mais tarde.';
                $this->showErrorModal = true;
                $this->showProcessingModal = false;
                return;
            }

            $plan = $this->plans[$this->selectedPlan];

            // Determinar moeda disponível
            $availableCurrency = null;
            if (isset($plan['prices'][$this->selectedCurrency])) {
                $availableCurrency = $this->selectedCurrency;
            } elseif (isset($plan['prices']['BRL'])) {
                $availableCurrency = 'BRL';
            } elseif (isset($plan['prices']['USD'])) {
                $availableCurrency = 'USD';
            }

            if (!$availableCurrency) {
                Log::error('generatePix: Nenhuma moeda válida encontrada (backend)', [
                    'selectedPlan' => $this->selectedPlan,
                    'available_currencies' => array_keys($plan['prices'] ?? [])
                ]);
                $this->errorMessage = __('payment.plan_not_loaded') ?? 'Plano não disponível no momento. Tente novamente mais tarde.';
                $this->showErrorModal = true;
                $this->showProcessingModal = false;
                return;
            }

            // Always compute PIX amount from backend authoritative values:
            // base = PushinPay amount_override (if supported) OR plan backend price
            $pushAmount = $plan['gateways']['pushinpay']['amount_override'] ?? null;
            $pushSupported = (bool)($plan['gateways']['pushinpay']['supported'] ?? false);

            if ($pushAmount !== null && $pushSupported === true) {
                $basePrice = floatval($pushAmount);
            } else {
                $prices = $plan['prices'][$availableCurrency];
                $basePrice = floatval($prices['descont_price'] ?? $prices['origin_price'] ?? 0);
            }

            // Sum active bumps coming from backend for PIX flow
            $bumpsTotal = 0.0;
            foreach ($this->bumps as $bump) {
                $isActive = false;
                if (is_array($bump)) {
                    $isActive = !empty($bump['active']) || (isset($bump['active']) && $bump['active'] === true);
                } else {
                    $isActive = (bool)$bump;
                }

                if ($isActive) {
                    // Only include bumps intended for PIX if a payment_method is present
                    if (is_array($bump) && isset($bump['payment_method']) && $bump['payment_method'] !== 'pix') {
                        continue;
                    }

                    $bumpPrice = 0.0;
                    if (is_array($bump)) {
                        if (isset($bump['price'])) {
                            $bumpPrice = floatval($bump['price']);
                        } elseif (isset($bump['original_price'])) {
                            $bumpPrice = floatval($bump['original_price']);
                        } elseif (isset($bump['amount'])) {
                            $bumpPrice = floatval($bump['amount']);
                        } elseif (isset($bump['value'])) {
                            $bumpPrice = floatval($bump['value']);
                        }
                    } else {
                        $bumpPrice = floatval($bump);
                    }

                    $bumpsTotal += $bumpPrice;
                }
            }

            $finalPrice = round($basePrice + $bumpsTotal, 2);

            Log::info('generatePix: Valor calculado a partir do BACKEND (PushinPay + bumps)', [
                'plan' => $this->selectedPlan,
                'currency' => $availableCurrency,
                'base_price' => $basePrice,
                'bumps_total' => $bumpsTotal,
                'final_price' => $finalPrice,
            ]);

            // Calcula o valor final em centavos (usar como fonte única antes de enviar)
            $amountInCents = (int)round(floatval($finalPrice) * 100);

            Log::info('generatePix: finalPrice resolved for PushinPay', [
                'finalPrice' => $finalPrice,
                'amountInCents' => $amountInCents,
                'selectedPlan' => $this->selectedPlan,
                'totals_final_price' => $this->totals['final_price'] ?? null,
                'pushAmount' => $pushAmount,
            ]);

            Log::channel('payment_checkout')->info('PagePay: Iniciando geração de PIX', [
                'email' => $this->email,
                'amount' => $amountInCents,
                'currency' => $this->selectedCurrency,
            ]);

            // Chama o serviço PIX para criar o pagamento
            $response = $this->pixService->createPixPayment([
                'amount' => $amountInCents,
                'description' => 'Pagamento - ' . ($this->product['title'] ?? 'Produto'),
                'customerEmail' => $this->email,
                'customerName' => $this->cardName,
                'webhook_url' => url('/api/pix/webhook'),
            ]);

            // Trata erros da criação do PIX
            if ($response['status'] === 'error') {
                Log::error('PagePay: Erro ao gerar PIX', [
                    'message' => $response['message'],
                ]);
                $this->pixError = $response['message'];
                $this->addError('pix', $response['message']);
                return;
            }

            // Extrai dados do PIX da resposta bem-sucedida
            $pixData = $response['data'];
            
            // Tenta diferentes campos para o código PIX
            $qrCode = $pixData['qr_code'] 
                ?? $pixData['copyAndPaste'] 
                ?? $pixData['pix_code'] 
                ?? $pixData['code']
                ?? $pixData['copia_cola']
                ?? $pixData['copy_paste']
                ?? null;
            
            $qrCodeBase64 = $pixData['qr_code_base64'] 
                ?? $pixData['qrCodeBase64']
                ?? $pixData['qr_code']
                ?? null;
            
            $this->pixTransactionId = $pixData['payment_id'] ?? null;
            $this->pixQrImage = $qrCodeBase64;
            $this->pixQrCodeText = $qrCode;
            $this->pixAmount = $pixData['amount'] ?? $amountInCents;
            $this->pixExpiresAt = now()->addMinutes(30); // PIX expira em 30 minutos por padrão
            $this->showPixModal = true;

            Log::channel('payment_checkout')->info('PagePay: PIX gerado com sucesso', [
                'payment_id' => $this->pixTransactionId,
                'qr_code_found' => !empty($qrCode),
                'qr_code_preview' => substr($qrCode ?? '', 0, 50) . '...',
            ]);

            // Notifica o frontend para iniciar polling
            $this->dispatch('start-pix-polling');
            
        } catch (\Exception $e) {
            Log::error('PagePay: Erro ao gerar PIX', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            $this->pixError = 'Erro ao gerar PIX. Tente novamente.';
            $this->addError('pix', $this->pixError);
        }
    }

    /**
     * Consulta o status do pagamento PIX
     * Chamado via polling a cada 3-5 segundos
     * NOTA: A API Pushing Pay não fornece rota de consulta de status.
     * O status será atualizado via webhook quando o pagamento for confirmado.
     */
    public function checkPixPaymentStatus()
    {
        if (empty($this->pixTransactionId)) {
            Log::warning('PagePay: checkPixPaymentStatus chamado sem pixTransactionId');
            return;
        }

        try {
            // Prefer server-side pix service to retrieve authoritative status
            $result = null;

            try {
                $result = $this->pixService->getPaymentStatus($this->pixTransactionId);
            } catch (\Throwable $e) {
                Log::warning('PagePay: falha ao consultar PixService para status', ['error' => $e->getMessage(), 'payment_id' => $this->pixTransactionId]);
            }

            if (empty($result) || !is_array($result) || empty($result['data'])) {
                Log::debug('PagePay: getPaymentStatus retornou sem dado útil', ['payment_id' => $this->pixTransactionId, 'result' => $result]);
                return;
            }

            $status = strtolower($result['data']['status'] ?? ($result['data']['payment_status'] ?? ''));
            Log::info('PagePay: checkPixPaymentStatus fetched status', ['payment_id' => $this->pixTransactionId, 'status' => $status]);

            if (in_array($status, ['approved', 'paid', 'confirmed'], true)) {
                $this->pixStatus = 'approved';
                $this->handlePixApproved();
                return;
            }

            if (in_array($status, ['declined', 'refused'], true)) {
                $this->pixStatus = 'rejected';
                $this->handlePixRejected();
                return;
            }

            if (in_array($status, ['expired', 'expired_payment'], true)) {
                $this->pixStatus = 'expired';
                $this->handlePixExpired();
                return;
            }

            // otherwise keep waiting
            Log::debug('PagePay: checkPixPaymentStatus - status not terminal, keep waiting', ['payment_id' => $this->pixTransactionId, 'status' => $status]);

        } catch (\Exception $e) {
            Log::error('PagePay: Erro em checkPixPaymentStatus', ['error' => $e->getMessage(), 'payment_id' => $this->pixTransactionId]);
        }
    }

    

    /**
     * Processa PIX aprovado
     */
    private function handlePixApproved()
    {
        Log::info('PagePay: PIX aprovado - INICIANDO REDIRECIONAMENTO', [
            'payment_id' => $this->pixTransactionId,
            'timestamp' => now(),
        ]);

        // Para o polling
        $this->dispatch('stop-pix-polling');

        // Fecha o modal PIX (não mostra modal de sucesso, apenas redireciona)
        $this->showPixModal = false;
        $this->showSuccessModal = false;
        $this->showProcessingModal = false;

        // Dispatch evento de sucesso para tracking (Facebook Pixel, etc)
        $purchaseData = [
            'transaction_id' => $this->pixTransactionId,
            'value' => $this->pixAmount,
            'currency' => $this->selectedCurrency,
            'content_ids' => [$this->product['hash'] ?? null],
            'content_type' => 'product',
        ];

        try {
            session()->put('last_order_transaction', $purchaseData['transaction_id']);
            session()->put('last_order_amount', $purchaseData['value']);
            Log::info('PagePay: Session data saved', ['transaction_id' => $purchaseData['transaction_id']]);
        } catch (\Throwable $e) {
            Log::warning('PagePay: Failed to save session', ['error' => $e->getMessage()]);
        }

        // Dispatch Livewire event
        try {
            $this->dispatch('checkout-success', purchaseData: $purchaseData);
            Log::info('PagePay: checkout-success event dispatched');
        } catch (\Throwable $e) {
            Log::error('PagePay: Failed to dispatch checkout-success', ['error' => $e->getMessage()]);
        }

        // Dispatch browser event
        try {
            $this->dispatchBrowserEvent('checkout-success', $purchaseData);
            Log::info('PagePay: checkout-success browser event dispatched');
        } catch (\Exception $e) {
            Log::warning('PagePay: Could not dispatch browser event checkout-success (pix): ' . $e->getMessage());
        }

        // Salvar dados do cliente na sessão
        try {
            session()->put('show_upsell_after_purchase', true);
            session()->put('last_order_customer', [
                'name' => $this->pixName ?? $this->name ?? 'Cliente',
                'email' => $this->pixEmail ?? $this->email ?? null,
                'phone' => $this->pixPhone ?? $this->phone ?? null,
                'document' => $this->pixCpf ?? $this->cpf ?? null,
            ]);
            
            Log::info('PagePay: Dados do cliente salvos na sessão', [
                'has_name' => !empty($this->pixName ?? $this->name),
                'has_email' => !empty($this->pixEmail ?? $this->email),
            ]);
        } catch (\Exception $e) {
            Log::error('PagePay: Failed to save customer session data', ['error' => $e->getMessage()]);
        }

        // **REDIRECIONAMENTO - CRITICAL SECTION**
        try {
            // Prefer plan-level upsell success URL when available
            $redirectUrl = null;
            $plan = $this->plans[$this->selectedPlan] ?? null;
            if ($plan) {
                $redirectUrl = $plan['pages_upsell_succes_url'] ?? $plan['upsell_success_url'] ?? $plan['upsell_url'] ?? null;
            }

            // For PIX flows we must NOT redirect to card-only upsell pages.
            // Only redirect if the plan provides an explicit upsell/thank-you URL.
            if (!empty($redirectUrl)) {
                Log::info('PagePay: DISPATCHING REDIRECT (PIX)', [
                    'url' => $redirectUrl,
                    'payment_id' => $this->pixTransactionId,
                ]);

                $this->dispatch('redirect-success', url: $redirectUrl);

                Log::info('PagePay: REDIRECT DISPATCH SUCCESSFUL (PIX)', [
                    'url' => $redirectUrl,
                ]);
            } else {
                Log::info('PagePay: No plan-level upsell URL for PIX; skipping redirect (card-only upsell pages are not used for PIX)');
            }
        } catch (\Throwable $e) {
            Log::error('PagePay: REDIRECT DISPATCH FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Processa PIX rejeitado/cancelado
     */
    private function handlePixRejected()
    {
        Log::warning('PagePay: PIX rejeitado/cancelado', [
            'payment_id' => $this->pixTransactionId,
        ]);

        // Para o polling
        $this->dispatch('stop-pix-polling');

        // Mostra erro
        $this->pixError = __('payment.pix_rejected');
        $this->showPixModal = false;
        $this->showErrorModal = true;
        $this->addError('pix', $this->pixError);

        // Limpa dados do PIX
        $this->pixTransactionId = null;
        $this->pixQrImage = null;
        $this->pixQrCodeText = null;
    }

    /**
     * Processa PIX expirado
     */
    private function handlePixExpired()
    {
        Log::warning('PagePay: PIX expirou', [
            'payment_id' => $this->pixTransactionId,
        ]);

        // Para o polling
        $this->dispatch('stop-pix-polling');

        // Mostra mensagem de expiração
        $this->pixError = __('payment.pix_expired');
        $this->pixStatus = 'expired';

        // Notifica frontend para mostrar botão de gerar novo PIX
        $this->dispatch('pix-expired');
    }

    /**
     * Redefine modal de PIX para gerar novo
     */
    public function closePix()
    {
        $this->showPixModal = false;
        $this->pixTransactionId = null;
        $this->pixQrImage = null;
        $this->pixQrCodeText = null;
        $this->pixError = null;
        $this->pixStatus = 'pending';
        $this->dispatch('stop-pix-polling');
    }

    public function updatePhone($event = null)
    {
        if (isset($event['phone'])) {
            $this->phone = $event['phone'];
        }
    }

    public function updateActivityCount()
    {
        $this->activityCount = rand(1, 50);
    }

    public function changeLanguage($lang)
    {
        if (array_key_exists($lang, $this->availableLanguages)) {
            // Persistir preferência do usuário e aplicar imediatamente
            Session::put('user_language', $lang);
            app()->setLocale($lang);
            $this->selectedLanguage = $lang;
            $this->selectedCurrency = $lang === 'br' ? 'BRL'
                : ($lang === 'en' ? 'USD'
                    : ($lang === 'es' ? 'EUR' : 'BRL'));

            // Atualizar currency/session helper
            $this->syncCurrencyWithLanguage();
            
            // Sempre carregar planos da API Stripe para o cartão
            $this->plans = $this->getPlans();
            
            // Forçar método de pagamento padrão conforme idioma:
            // - Em PT-BR: manter PIX disponível e selecionado por padrão
            // - Em outros idiomas: apenas cartão (PIX não deve aparecer)
            if ($this->selectedLanguage === 'br') {
                $this->selectedPaymentMethod = 'pix';
            } else {
                $this->selectedPaymentMethod = 'credit_card';
            }
            
            $this->testimonials = trans('checkout.testimonials') ?? [];
            $this->calculateTotals();
            // Dispatch an event if JS needs to react to language change for UI elements not covered by Livewire re-render
            $this->dispatch('languageChanged');
        }
    }

    public function decrementSpotsLeft()
    {
        if (rand(1, 5) == 1) {
            if ($this->spotsLeft > 3) {
                $this->spotsLeft--;
                $this->dispatch('spots-updated');
            }
        }
    }

    public function updateLiveActivity()
    {
        $this->activityCount = rand(3, 25);
        $this->dispatch('activity-updated');
    }

    public function render()
    {
        // Diagnostic: log bumps state before rendering to help identify unexpected items
        try {
            Log::info('PagePay: rendering view - diagnostic bumps snapshot', [
                'selectedPaymentMethod' => $this->selectedPaymentMethod ?? null,
                'selectedPlan' => $this->selectedPlan ?? null,
                'plans_loaded' => is_array($this->plans) ? array_keys($this->plans) : null,
                'bumps_count' => is_array($this->bumps) ? count($this->bumps) : 0,
                'bumps_sample' => array_slice(is_array($this->bumps) ? $this->bumps : [], 0, 5),
            ]);
            // Extra diagnostic: write full bumps state to payment_checkout log for reproduction
            try {
                Log::channel('payment_checkout')->info('PagePay: render - full bumps state', [
                    'selectedPaymentMethod' => $this->selectedPaymentMethod ?? null,
                    'selectedPlan' => $this->selectedPlan ?? null,
                    'bumps' => is_array($this->bumps) ? $this->bumps : [],
                ]);
            } catch (\Throwable $_) {
                // non-fatal if channel not configured
            }
        } catch (\Throwable $e) {
            // non-fatal
            Log::warning('PagePay: failed to log bumps diagnostic', ['error' => $e->getMessage()]);
        }

        // Safety: if PIX selected but PushinPay is not supported for this plan, hide bumps
        try {
            if (($this->selectedPaymentMethod ?? '') === 'pix') {
                $supports = (bool) ($this->plans[$this->selectedPlan]['gateways']['pushinpay']['supported'] ?? false);
                if ($supports === false) {
                    $this->bumps = [];
                    $this->dataOrigin['bumps'] = 'filtered';
                    Log::info('PagePay: Hiding PIX bumps at render because PushinPay not supported for plan', ['selectedPlan' => $this->selectedPlan]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('PagePay: error checking pushinpay support at render', ['error' => $e->getMessage()]);
        }

        // Defensive: normalize bumps again at render time to avoid preselected UI state
        try {
            if (is_array($this->bumps) && count($this->bumps) > 0) {
                $onlyForPix = ($this->selectedPaymentMethod === 'pix');
                $this->bumps = $this->sanitizeBumps($this->bumps, $onlyForPix);
            }
        } catch (\Throwable $_) {
            // non-fatal
        }

        return view('livewire.page-pay')->with([
            'title' => 'CHECKOUT - SNAPHUBB',
            'canonical' => url()->current(),
        ]);
    }

    public function updatedEmail($email)
    {
        // removed email existence check - no-op to avoid external verification
        // Keeping method intentionally empty so Livewire updates don't trigger external calls
        return;
    }
    // Duplicate removed: consolidated implementation is the single definition

    /**
     * Carrega bumps por método de pagamento
     */
    public function loadBumpsByMethod($method = 'card')
    {
        try {
            // Preferir bumps vindos da API (quando o backend forneceu 'order_bumps' para o plano)
            // Isso evita sobrescrever bumps ricos da API com registros locais possivelmente incompletos.
            $usedSource = 'db';
            if (($this->dataOrigin['plans'] ?? null) === 'backend'
                && !empty($this->plans)
                && isset($this->plans[$this->selectedPlan]['order_bumps'])
                && $method !== 'pix') {
                $this->bumps = $this->plans[$this->selectedPlan]['order_bumps'];
                // Normalize to avoid preselected bumps from backend only when loading for PIX
                $this->bumps = $this->sanitizeBumps($this->bumps, ($method === 'pix' || ($this->selectedPaymentMethod === 'pix')));
                $this->dataOrigin['bumps'] = 'backend';
                $usedSource = 'backend';
            } else {
                // Usar Model ao invés de HTTP request para evitar timeout
                $this->bumps = \App\Models\OrderBump::getByPaymentMethod($method);
                $this->dataOrigin['bumps'] = 'db';
            }

            // Filtrar bumps sem preço válido para fluxos de cartão (evita mostrar bump R$0,00)
            try {
                if ($method !== 'pix' && is_array($this->bumps)) {
                    $this->bumps = array_values(array_filter($this->bumps, function ($b) {
                        $price = 0.0;
                        if (is_array($b)) {
                            if (isset($b['price'])) {
                                $price = floatval($b['price']);
                            } elseif (isset($b['original_price'])) {
                                $price = floatval($b['original_price']);
                            } elseif (isset($b['amount'])) {
                                $price = floatval($b['amount']);
                            }
                        } else {
                            $price = floatval($b);
                        }

                        // If bumps come from backend API, allow zero-priced bumps if they explicitly exist (the API may intend them)
                        if (($this->dataOrigin['bumps'] ?? null) === 'backend') {
                            return true;
                        }

                        return $price > 0.0;
                    }));
                }
            } catch (\Throwable $e) {
                Log::warning('PagePay: Falha ao filtrar bumps por método', ['method' => $method, 'error' => $e->getMessage()]);
            }

            // Se for PIX, verificar se o plano selecionado suporta PushinPay (evita mostrar bump quando backend desativou)
            if ($method === 'pix') {
                try {
                    $supports = null;
                    if (!empty($this->plans) && isset($this->plans[$this->selectedPlan])) {
                        $supports = (bool) ($this->plans[$this->selectedPlan]['gateways']['pushinpay']['supported'] ?? false);
                    } else {
                        // Tentar recarregar planos uma vez para checar suporte
                        $this->plans = $this->getPlans();
                        $supports = (bool) ($this->plans[$this->selectedPlan]['gateways']['pushinpay']['supported'] ?? false);
                    }

                    if ($supports === false) {
                        Log::info('PagePay: PushinPay não suportado para o plano selecionado — ocultando bumps de PIX', ['selectedPlan' => $this->selectedPlan]);
                        $this->bumps = [];
                        $this->dataOrigin['bumps'] = 'filtered';
                    }
                } catch (\Throwable $e) {
                    Log::warning('PagePay: Erro ao validar suporte PushinPay para bumps PIX', ['error' => $e->getMessage()]);
                }
            }
            
            Log::info('PagePay: Order bumps carregados para método', [
                'method' => $method,
                'count' => count($this->bumps),
                'source' => $usedSource,
            ]);
        } catch (\Exception $e) {
            Log::error('PagePay: Exceção ao carregar bumps por método', [
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
            $this->bumps = [];
        }
    }

    private function updateProductDetails()
    {
        // Ensure selectedPlan exists in loaded plans; otherwise pick the first available
        if (!empty($this->plans) && !isset($this->plans[$this->selectedPlan])) {
            $first = array_key_first($this->plans);
            if ($first) {
                Log::warning('updateProductDetails: selectedPlan not found, switching to first available', ['requested' => $this->selectedPlan, 'switched_to' => $first]);
                $this->selectedPlan = $first;
            }
        }

        if (isset($this->plans[$this->selectedPlan])) {
            $title = $this->plans[$this->selectedPlan]['label'] ?? '';
            $title = str_ireplace(['1/month', '1 mês', '1/mes'], '1x/mês', $title);

            $this->product = [
                'hash' => $this->plans[$this->selectedPlan]['hash'] ?? null,
                'title' => $title,
                'price_id' => $this->plans[$this->selectedPlan]['prices'][$this->selectedCurrency]['id'] ?? null,
                'image' => 'https://d2lr0gp42cdhn1.cloudfront.net/3057842714/products/hfb81x1mas92t9jwrav1fjldj',
            ];
        } else {
            $this->product = [
                'hash' => null,
                'title' => 'Streaming Snaphubb - 1x/mês',
                'price_id' => null,
                'image' => 'https://d2lr0gp42cdhn1.cloudfront.net/3057842714/products/hfb81x1mas92t9jwrav1fjldj',
            ];
            Log::warning('No plan found for the selected option, product details not set.', [
                'selectedPlan' => $this->selectedPlan,
            ]);
        }
    }

    /**
     * Normaliza a lista de bumps para evitar que venham pré-selecionados do backend.
     * Sempre garante que o campo 'active' esteja definido como boolean false por padrão.
     */
    private function sanitizeBumps(array $bumps, bool $onlyForPix = false): array
    {
        return array_values(array_map(function ($b) use ($onlyForPix) {
            if (!is_array($b)) {
                return [
                    'title' => (string)$b,
                    'price' => 0.0,
                    'active' => false,
                ];
            }

            // Ensure other common keys exist to avoid undefined index issues in the view
            if (!isset($b['price'])) $b['price'] = isset($b['original_price']) ? $b['original_price'] : 0.0;
            if (!isset($b['title'])) $b['title'] = $b['name'] ?? '';
            if (!isset($b['id'])) $b['id'] = $b['id'] ?? null;

            // If caller requested sanitize only for PIX, then only force active=false
            // for bumps that target PIX; otherwise preserve existing 'active' value.
            if ($onlyForPix) {
                $paymentMethod = $b['payment_method'] ?? 'card';
                if (strtolower($paymentMethod) === 'pix') {
                    $b['active'] = false;
                } else {
                    // keep whatever server or DB intended for non-pix bumps
                    $b['active'] = isset($b['active']) ? (bool)$b['active'] : false;
                }
            } else {
                // Default behaviour: do not alter 'active' state except ensure boolean
                $b['active'] = isset($b['active']) ? (bool)$b['active'] : false;
            }

            return $b;
        }, $bumps));
    }

    /**
     * Prepara dados do PIX sincronizados com o Stripe
     * Reutiliza o mesmo valor e estrutura de carrinho
     * PIX sempre usa $this->plans que já vem do MOCK no mount()
     */
    private function preparePIXData(): array
    {
        // PIX SEMPRE tem os planos do MOCK já carregados em $this->plans
        if (empty($this->plans) || !isset($this->plans[$this->selectedPlan])) {
            Log::error('preparePIXData: Plano não encontrado', [
                'selected_plan' => $this->selectedPlan,
                'available_plans' => array_keys($this->plans ?? [])
            ]);
            throw new \Exception('Plano selecionado não encontrado');
        }
        
        // 1. EXTRAIR VALOR DOS PLANOS MOCK
        $currentPlanDetails = $this->plans[$this->selectedPlan];
        
        // Se não houver preços definidos nem suporte PushinPay, falha
        if (!isset($currentPlanDetails['prices'][$this->selectedCurrency]) && !isset($currentPlanDetails['gateways']['pushinpay'])) {
            Log::error('preparePIXData: Moeda não encontrada nem gateway PushinPay configurado', [
                'plan' => $this->selectedPlan,
                'currency' => $this->selectedCurrency,
                'available_currencies' => array_keys($currentPlanDetails['prices'] ?? [])
            ]);
            throw new \Exception('Moeda não disponível para este plano');
        }

        // Determinar preço base do plano para PIX — priorizar valor do PushinPay (backend)
        $pushGateway = $currentPlanDetails['gateways']['pushinpay'] ?? null;
        $pushSupported = (bool)($pushGateway['supported'] ?? false);
        $pushAmountOverride = $pushGateway['amount_override'] ?? null;

        // Extra: garantir que o fluxo PIX sempre utilize explicitamente o preço
        // provido pelo BACKEND/PushinPay. Se não houver `amount_override` na
        // estrutura já formatada, tentamos buscar diretamente do endpoint
        // backend `/get-plans` para localizar qualquer `amount_override` que
        // o backend possa expor (evita usar valores vindos do Stripe).
        if ($pushSupported && $pushAmountOverride === null) {
            try {
                $url = rtrim($this->apiUrl, '/') . '/get-plans?lang=' . ($this->selectedLanguage ?? 'br');
                $resp = $this->httpClient->request('GET', $url, [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ]);
                $raw = json_decode($resp->getBody()->getContents(), true);

                $found = null;
                // backend may return either an array under 'data' or an associative map
                if (is_array($raw) && isset($raw['data']) && is_array($raw['data'])) {
                    foreach ($raw['data'] as $p) {
                        if ((isset($p['identifier']) && $p['identifier'] == $this->selectedPlan)
                            || (isset($p['pages_product_external_id']) && $p['pages_product_external_id'] == ($currentPlanDetails['hash'] ?? null))
                            || (isset($p['external_id']) && $p['external_id'] == ($currentPlanDetails['hash'] ?? null))) {
                            $found = $p;
                            break;
                        }
                    }
                } elseif (is_array($raw) && isset($raw[$this->selectedPlan])) {
                    $found = $raw[$this->selectedPlan];
                }

                if ($found) {
                    // Possíveis formatos: gateways.pushinpay.amount_override ou pushinpay_amount_override
                    $gw = $found['gateways']['pushinpay'] ?? ($found['gateways']['push'] ?? null);
                    $bp = null;
                    if (is_array($gw) && isset($gw['amount_override'])) {
                        $bp = $gw['amount_override'];
                    } elseif (isset($found['pushinpay_amount_override'])) {
                        $bp = $found['pushinpay_amount_override'];
                    }
                    if (!is_null($bp) && $bp !== '') {
                        $pushAmountOverride = floatval($bp);
                        Log::info('preparePIXData: Obtained pushinpay override from backend API', ['plan' => $this->selectedPlan, 'override' => $pushAmountOverride]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('preparePIXData: failed to fetch pushinpay override from backend', ['error' => $e->getMessage()]);
            }
        }

        if ($pushSupported && $pushAmountOverride !== null) {
            $basePrice = floatval($pushAmountOverride);
            Log::info('preparePIXData: usando amount_override do PushinPay como base', ['base_price' => $basePrice]);
        } else {
            $currentPlanPriceInfo = $currentPlanDetails['prices'][$this->selectedCurrency] ?? null;
            if ($currentPlanPriceInfo && isset($currentPlanPriceInfo['descont_price'])) {
                $basePrice = floatval($currentPlanPriceInfo['descont_price']);
                Log::info('preparePIXData: usando preço do plano como base', ['base_price' => $basePrice]);
            } else {
                Log::error('preparePIXData: Nenhum preço base encontrado para o plano', ['plan' => $this->selectedPlan]);
                throw new \Exception('Preço base do plano não encontrado');
            }
        }

        // Garantir que os bumps venham da fonte correta (carregados por loadBumpsByMethod('pix'))
        // Sanitizar bumps especificamente para o fluxo PIX para evitar inclusão indevida
        $bumpsToUse = [];
        try {
            $bumpsToUse = $this->sanitizeBumps(is_array($this->bumps) ? $this->bumps : [], true);
        } catch (\Throwable $_) {
            $bumpsToUse = is_array($this->bumps) ? $this->bumps : [];
        }

        $bumpsTotal = 0.0;
        foreach ($bumpsToUse as $bump) {
            $active = false;
            if (is_array($bump)) {
                $active = !empty($bump['active']) || (isset($bump['active']) && $bump['active'] === true) || ($bump['selected'] ?? false);
            } else {
                $active = (bool)$bump;
            }

            if ($active) {
                $bumpPrice = 0.0;
                if (is_array($bump)) {
                    if (isset($bump['price'])) {
                        $bumpPrice = floatval($bump['price']);
                    } elseif (isset($bump['original_price'])) {
                        $bumpPrice = floatval($bump['original_price']);
                    } elseif (isset($bump['amount'])) {
                        $bumpPrice = floatval($bump['amount']);
                    } elseif (isset($bump['value'])) {
                        $bumpPrice = floatval($bump['value']);
                    }
                } else {
                    $bumpPrice = floatval($bump);
                }
                $bumpsTotal += $bumpPrice;
            }
        }

        $numeric_final_price = round($basePrice + $bumpsTotal, 2);
        Log::info('preparePIXData: Valor calculado a partir do BACKEND (PushinPay + bumps)', [
            'plan' => $this->selectedPlan,
            'base_price' => $basePrice,
            'bumps_total' => $bumpsTotal,
            'final_price' => $numeric_final_price,
            'amount_cents' => (int)round($numeric_final_price * 100)
        ]);
        
        // 2. PREPARAR DADOS DO CLIENTE
        $customerData = [
            'name' => $this->pixName,
            'email' => $this->pixEmail,
            'phone_number' => preg_replace('/[^0-9+]/', '', $this->pixPhone),
        ];
        
        if ($this->selectedLanguage === 'br' && $this->pixCpf) {
            $customerData['document'] = preg_replace('/\D/', '', $this->pixCpf);
        }
        
        // 3. PREPARAR ITENS DO CARRINHO (COM PREÇOS DO MOCK)
        $cartItems = [];
        
        $cartItems[] = [
            'product_hash' => $currentPlanDetails['hash'],
            'title' => $this->product['title'] . ' - ' . $currentPlanDetails['label'],
            'price' => (int)round($basePrice * 100), // base item price (without bumps)
            'quantity' => 1,
            'operation_type' => 1,
        ];
        
        // 4. ADICIONAR ORDER BUMPS (se houver) - usar preços reais dos bumps
        foreach ($this->bumps as $bump) {
            if (!empty($bump['active'])) {
                $bumpPrice = (int)round(floatval($bump['price']) * 100);
                $cartItems[] = [
                    'product_hash' => $bump['hash'],
                    'title' => $bump['title'],
                    'price' => $bumpPrice,
                    'quantity' => 1,
                    'operation_type' => 2,
                ];
            }
        }
        
        // 5. ESTRUTURAR DADOS FINAIS
        return [
            'amount' => (int)round($numeric_final_price * 100),
            'currency_code' => $this->selectedCurrency,
            'plan_key' => $this->selectedPlan,
            'offer_hash' => $currentPlanDetails['hash'],
            'customer' => $customerData,
            'cart' => $cartItems,
            'metadata' => [
                'product_main_hash' => $this->product['hash'],
                'bumps_selected' => collect($this->bumps)->where('active', true)->pluck('id')->implode(','),
                'utm_source' => $this->utm_source,
                'utm_medium' => $this->utm_medium,
                'utm_campaign' => $this->utm_campaign,
                'utm_id' => $this->utm_id,
                'utm_term' => $this->utm_term,
                'utm_content' => $this->utm_content,
                'language' => $this->selectedLanguage,
                'payment_method' => 'pix',
            ]
        ];
    }

    public function generatePixPayment()
    {
        try {
            // Limpar mensagem de validação anterior
            $this->pixValidationError = null;
            // IMPORTANTE: Recarregar planos do BACKEND para PIX (mock disabled)
            $this->plans = $this->getPlans();
            Log::info('generatePixPayment: Recarregando planos do BACKEND para PIX');

            // Garantir que os bumps venham do BACKEND para o fluxo de PIX
            try {
                $this->loadBumpsByMethod('pix');
                Log::info('generatePixPayment: bumps carregados para PIX', ['source' => $this->dataOrigin['bumps'] ?? null, 'count' => count($this->bumps ?? [])]);
                // Sanitizar bumps para fluxo PIX (forçar active=false em bumps que não devem ser pré-selecionados)
                try {
                    $this->bumps = $this->sanitizeBumps(is_array($this->bumps) ? $this->bumps : [], true);
                } catch (\Throwable $_) {
                    // ignore sanitize failures, fall back to original bumps
                }

                // Log bumps state to payment_checkout for immediate inspection (sanitized)
                try {
                    Log::channel('payment_checkout')->info('generatePixPayment: bumps after loadBumpsByMethod (sanitized)', [
                        'selectedPlan' => $this->selectedPlan ?? null,
                        'selectedPaymentMethod' => $this->selectedPaymentMethod ?? null,
                        'bumps' => is_array($this->bumps) ? $this->bumps : [],
                    ]);
                } catch (\Throwable $_) {
                    // ignore if channel not present
                }
            } catch (\Exception $e) {
                Log::warning('generatePixPayment: falha ao carregar bumps para PIX', ['error' => $e->getMessage()]);
            }

            // Recalcular totals com os planos recarregados para garantir parity entre UI e payload
            try {
                $this->calculateTotals();
                Log::info('generatePixPayment: totals recalculados após reload de planos', ['totals' => $this->totals]);
            } catch (\Exception $e) {
                Log::warning('generatePixPayment: falha ao recalcular totals', ['error' => $e->getMessage()]);
            }
            
            // Validar dados obrigatórios do PIX
            $hasErrors = false;

            // Validar Nome (obrigatório)
            if (empty($this->pixName) || strlen(trim($this->pixName)) === 0) {
                $hasErrors = true;
            }

            // Validar Email (obrigatório)
            if (empty($this->pixEmail) || !filter_var($this->pixEmail, FILTER_VALIDATE_EMAIL)) {
                $hasErrors = true;
            }

            // Validar CPF (obrigatório)
            if (empty($this->pixCpf) || !$this->isValidCpf($this->pixCpf)) {
                $hasErrors = true;
            }

            // Se houver erros de validação, mostrar mensagem e disparar scroll
            if ($hasErrors) {
                $this->pixValidationError = 'Preencha os dados para receber seu acesso';
                $this->dispatch('scroll-to-pix-form');
                return;
            }

            // Mostrar modal de processamento
            $this->showProcessingModal = true;
            $this->loadingMessage = __('payment.loader_processing');

            // Preparar dados do PIX sincronizados com Stripe
            $pixData = $this->preparePIXData();

            // Diagnostic: log the pixData and bumps right before sending to backend
            try {
                Log::channel('payment_checkout')->info('generatePixPayment: prepared pixData (before send)', [
                    'pixData' => $pixData,
                    'bumps' => is_array($this->bumps) ? $this->bumps : [],
                ]);
            } catch (\Throwable $_) {
                // ignore if logging channel isn't configured
            }

            if ($pixData['amount'] <= 0) {
                $this->errorMessage = __('payment.invalid_amount');
                $this->showErrorModal = true;
                $this->showProcessingModal = false;
                return;
            }

            // Enviar para backend (que valida e chama Mercado Pago)
            $response = $this->sendPixToBackend($pixData);

            if ($response['status'] === 'success' && isset($response['data'])) {
                $pixData = $response['data'];
                
                
                // Tenta diferentes campos para o código PIX
                $qrCode = $pixData['qr_code'] 
                    ?? $pixData['copyAndPaste'] 
                    ?? $pixData['pix_code'] 
                    ?? $pixData['code']
                    ?? $pixData['copia_cola']
                    ?? $pixData['copy_paste']
                    ?? null;
                
                $qrCodeBase64 = $pixData['qr_code_base64'] 
                    ?? $pixData['qrCodeBase64']
                    ?? $pixData['qr_code']
                    ?? null;
                
                $this->pixTransactionId = $pixData['payment_id'] ?? null;
                $this->pixQrImage = $qrCodeBase64;
                $this->pixQrCodeText = $qrCode;
                $this->pixAmount = $pixData['amount'] ?? 0;
                $this->pixExpiresAt = $pixData['expiration_date'] ?? null;
                $this->pixStatus = $pixData['status'] ?? 'pending';
                $this->showPixModal = true;
                $this->showProcessingModal = false;

                Log::info('PIX Generated Successfully - Controller Path', [
                    'payment_id' => $this->pixTransactionId,
                    'qr_code_found' => !empty($qrCode),
                ]);

                // Iniciar polling para checar status
                if ($this->pixTransactionId) {
                    $this->dispatch('start-pix-polling', transactionId: $this->pixTransactionId);
                }
                // Notifica o front-end que o PIX está pronto (para esconder loader cliente)
                try {
                    $this->dispatchBrowserEvent('pix-ready', ['payment_id' => $this->pixTransactionId]);
                } catch (\Exception $_) {
                    // não-fatal
                }

                // Livewire event paralelo para listeners JS (garante fechamento do loader)
                try {
                    $this->dispatch('pix-ready', paymentId: $this->pixTransactionId);
                } catch (\Exception $_) {
                    // evitar quebra do fluxo caso dispatch falhe
                }

                // Iniciar polling para checar status
                if ($this->pixTransactionId) {
                    $this->dispatch('start-pix-polling', transactionId: $this->pixTransactionId);
                }
                // Notifica o front-end que o PIX está pronto (para esconder loader cliente)
                try {
                    $this->dispatchBrowserEvent('pix-ready', ['payment_id' => $this->pixTransactionId]);
                } catch (\Exception $_) {
                    // não-fatal
                }

                // Livewire event paralelo para listeners JS (garante fechamento do loader)
                try {
                    $this->dispatch('pix-ready', paymentId: $this->pixTransactionId);
                } catch (\Exception $_) {
                    // evitar quebra do fluxo caso dispatch falhe
                }
            } else {
                $this->errorMessage = $response['message'] ?? __('payment.pix_generation_failed');
                $this->showErrorModal = true;
                $this->showProcessingModal = false;
            }
        } catch (\Exception $e) {
            Log::error('Erro ao gerar PIX: ' . $e->getMessage(), [
                'exception' => $e,
                'pixName' => $this->pixName,
                'pixEmail' => $this->pixEmail,
            ]);
            $this->errorMessage = __('payment.pix_generation_error');
            $this->showErrorModal = true;
            $this->showProcessingModal = false;
        }
    }

    /**
     * Envia dados do PIX para o backend para processamento seguro
     */
    private function sendPixToBackend(array $pixData): array
    {
        try {
            // Ao invés de fazer HTTP request para si mesmo, chama o PixController diretamente
            // PixController resolve o provider via Factory
            $controller = new \App\Http\Controllers\PixController();
            
            // Simular um request para o controller
            $request = \Illuminate\Http\Request::create(
                '/api/pix/create',
                'POST',
                $pixData,
                [],
                [],
                [],
                json_encode($pixData)
            );
            $request->headers->set('Accept', 'application/json');
            $request->headers->set('Content-Type', 'application/json');
            
            // Chamar o controller
            $response = $controller->create($request);
            
            // Se for JsonResponse, pegar o conteúdo
            if ($response instanceof \Illuminate\Http\JsonResponse) {
                $jsonData = json_decode($response->getContent(), true);
                return $jsonData ?? ['status' => 'error', 'message' => __('payment.pix_generation_failed')];
            }
            
            return [
                'status' => 'error',
                'message' => __('payment.pix_generation_failed'),
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao enviar PIX para backend: ' . $e->getMessage(), [
                'exception' => $e,
                'pixData' => $pixData,
            ]);
            return [
                'status' => 'error',
                'message' => __('payment.pix_generation_error'),
            ];
        }
    }

    /**
     * Valida um CPF
     */
    private function isValidCpf($cpf): bool
    {
        // Remove pontuação
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // CPF deve ter 11 dígitos
        if (strlen($cpf) !== 11) {
            return false;
        }

        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // Valida primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($cpf[$i]) * (10 - $i);
        }
        $firstVerifier = 11 - ($sum % 11);
        $firstVerifier = $firstVerifier >= 10 ? 0 : $firstVerifier;

        if (intval($cpf[9]) !== $firstVerifier) {
            return false;
        }

        // Valida segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += intval($cpf[$i]) * (11 - $i);
        }
        $secondVerifier = 11 - ($sum % 11);
        $secondVerifier = $secondVerifier >= 10 ? 0 : $secondVerifier;

        if (intval($cpf[10]) !== $secondVerifier) {
            return false;
        }

        return true;
    }

    /**
     * Request from the frontend to copy a field value to the clipboard.
     * We cannot access the clipboard from PHP, so emit a browser event
     * with the text and let client-side JS perform the copy.
     */
    public function copyToClipboard(string $field)
    {
        try {
            $text = $this->{$field} ?? null;
            $this->dispatchBrowserEvent('copy-to-clipboard', ['text' => $text]);
        } catch (\Exception $e) {
            Log::error('copyToClipboard failed: ' . $e->getMessage(), ['field' => $field]);
            $this->dispatchBrowserEvent('copy-to-clipboard', ['text' => '']);
        }
    }

    private function getTotalPixAmount()
    {
        if (!isset($this->totals) || empty($this->totals)) {
            $this->calculateTotals();
        }

        $totalData = $this->totals[0] ?? [];
        $finalPrice = $totalData['final_price'] ?? '0,00';

        // Converter formato brasileiro (1.234,56) para decimal (1234.56)
        return (float) str_replace(['.', ','], ['', '.'], $finalPrice);
    }
}
