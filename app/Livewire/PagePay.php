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
        $utm_source, $utm_medium, $utm_campaign, $utm_id, $utm_term, $utm_content, $src, $sck,
        $usingPixMock = false;

    public $selectedPaymentMethod = 'credit_card';

    // Modais
    public $showSuccessModal = false;
    public $showErrorModal = false;
    public $errorMessage = '';
    public $showSecure = false;
    public $showDownsellModal = false;
    public $showUpsellModal = false;
    public $showProcessingModal = false;
    public $showUserExistsModal = false;
    public $loadingMessage = '';

    public $selectedCurrency = 'BRL';
    public $selectedLanguage = 'br';
    public $selectedPlan = 'monthly';
    public $availableLanguages = [
        'br' => '🇧🇷 Português',
        'en' => '🇺🇸 English',
        'es' => '🇪🇸 Español',
    ];

    public $currencies = [
        'BRL' => ['symbol' => 'R$', 'name' => 'Real Brasileiro', 'code' => 'BRL', 'label' => "payment.brl"],
        'USD' => ['symbol' => '$', 'name' => 'Dólar Americano', 'code' => 'USD', 'label' => "payment.usd"],
        'EUR' => ['symbol' => '€', 'name' => 'Euro', 'code' => 'EUR', 'label' => "payment.eur"],
    ];

    public $bumpActive = false;
    public $bumps = [
        [
            'id' => 4,
            'title' => 'Criptografía anónima',
            'description' => 'Acesso a conteúdos ao vivo e eventos',
            'price' => 9.99,
            'hash' => '3nidg2uzc0',
            'active' => false,
        ],
        [
            'id' => 5,
            'title' => 'Guia Premium',
            'description' => 'Acesso ao guia completo de estratégias',
            'price' => 14.99,
            'hash' => '7fjk3ldw0',
            'active' => false,
        ],
    ];

    public $countdownMinutes = 14;
    public $countdownSeconds = 22;
    public $spotsLeft = 12;
    public $activityCount = 0;
    public $totals = [];

    private $lastBumpsState = [];

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
    protected $apiUrl;
    private $httpClient;
    private $pixService;
    public $cardValidationError = null;
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

    public function mount(PaymentGatewayInterface $paymentGateway = null) // Modified to allow injection, or resolve via factory
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

        $this->selectedLanguage = session('locale', config('app.locale'));
        app()->setLocale($this->selectedLanguage);

        $this->testimonials = trans('testimonials.testimonials');
        
        // Sempre carregar planos da API Stripe para o cartão de crédito
        $this->plans = $this->getPlans();
        Log::info('PagePay::mount - Carregando planos da API Stripe');
        
        // Se idioma for Português (BR), selecionar PIX como método padrão
        if ($this->selectedLanguage === 'br') {
            $this->selectedPaymentMethod = 'pix';
            Log::info('PagePay: PIX selecionado automaticamente (idioma BR)');
        }
        
        $this->selectedCurrency = Session::get('selectedCurrency', 'BRL');
        $this->selectedPlan = Session::get('selectedPlan', 'monthly');
        $this->activityCount = rand(1, 50);

        // Update product details based on the selected plan
        $this->updateProductDetails();
        $this->calculateTotals();
    }

    public function getPlans()
    {
        try {
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ];

            $request = new Request(
                'GET',
                rtrim($this->apiUrl, '/') . '/get-plans',
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
                $dataResponse = json_decode($responseBody, true);
                if (is_array($dataResponse) && !empty($dataResponse)) {
                    $this->paymentGateway = app(PaymentGatewayFactory::class)->create();
                    $result = $this->paymentGateway->formatPlans($dataResponse, $this->selectedCurrency);

                    if (isset($result[$this->selectedPlan]['order_bumps'])) {
                        $this->bumps = $result[$this->selectedPlan]['order_bumps'];
                    } else {
                        $this->bumps = [];
                    }

                    return $result;
                }
            }

            // If API returned nothing, return empty list
            Log::warning('PagePay: No plans available from API. Returning empty list.');
            return [];
        } catch (\Exception $e) {
            Log::error('PagePay: Critical error in getPlans.', [
                'gateway' => $this->gateway,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Carrega planos APENAS do arquivo MOCK para PIX
     * PIX nunca deve chamar a API de planos - usa MOCK local
     */
    public function getPlansFromMock(): array
    {
        try {
            $mockPath = resource_path('mock/get-plans.json');
            
            if (!file_exists($mockPath)) {
                Log::error('PagePay: Mock file não encontrado', ['path' => $mockPath]);
                return [];
            }
            
            $mockJson = file_get_contents($mockPath);
            $plansData = json_decode($mockJson, true);
            
            if (!is_array($plansData) || empty($plansData)) {
                Log::error('PagePay: Mock file vazio ou inválido', ['path' => $mockPath]);
                return [];
            }
            
            Log::info('PagePay: Planos carregados do MOCK com sucesso', [
                'count' => count($plansData),
                'plans' => implode(', ', array_keys($plansData))
            ]);
            
            return $plansData;
        } catch (\Exception $e) {
            Log::error('PagePay: Erro ao carregar MOCK', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

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
            Log::error('Plano selecionado não encontrado.', [
                'selected_plan' => $this->selectedPlan,
                'available_plans' => implode(', ', array_keys($this->plans))
            ]);
            return;
        }
        $plan = $this->plans[$this->selectedPlan];

        // 2. Verificamos se existe um array de preços para o plano
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

    // Daqui para baixo, o código original continua, pois agora temos certeza que a variável $prices existe
    $this->totals = [
        'month_price' => $prices['origin_price'] / $plan['nunber_months'],
        'month_price_discount' => $prices['descont_price'] / $plan['nunber_months'],
        'total_price' => $prices['origin_price'],
        'total_discount' => $prices['origin_price'] - $prices['descont_price'],
    ];

    $finalPrice = $prices['descont_price'];

    foreach ($this->bumps as $bump) {
        if (!empty($bump['active'])) {
            $finalPrice += floatval($bump['price']);
        }
    }

    $this->totals['final_price'] = $finalPrice;

    $this->totals = array_map(function ($value) {
        return number_format(round($value, 2), 2, ',', '.');
    }, $this->totals);
}


    public function startCheckout()
    {
        Log::debug('startCheckout called', ['selectedPaymentMethod' => $this->selectedPaymentMethod]);

        // Limpar mensagem de validação anterior (cartão)
        $this->cardValidationError = null;
        $this->isProcessingCard = false;

        // Validação rápida antes de exibir loader: nome, e-mail e CPF (quando BR)
        $hasErrors = false;
        if (empty($this->cardName) || strlen(trim($this->cardName)) === 0) {
            $hasErrors = true;
        }
        if (empty($this->email) || !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            $hasErrors = true;
        }
        if ($this->selectedLanguage === 'br') {
            if (empty($this->cpf)) {
                $hasErrors = true;
            }
        }

        if ($hasErrors) {
            // Mensagem genérica (traduzida) semelhante ao fluxo PIX
            $this->cardValidationError = __('payment.complete_to_generate_pix');
            // Disparar evento para scrollar até o formulário de cartão
            $this->dispatch('scroll-to-card-form');
            // Retornar imediatamente: evita execução longa e exibição de loader
            return;
        }

        // Proceed with credit card logic
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


            // --- FLUXO CARTÃO ---
            $rules = [
                'cardName' => 'required|string|max:255',
                'email' => 'required|email',
            ];

            if ($this->selectedLanguage === 'br') {
                $rules['cpf'] = ['required', 'string', 'regex:/^\d{3}\.\d{3}\.\d{3}\-\d{2}$|^\d{11}$/'];
            }

            if ($this->gateway !== 'stripe') {
                $rules['cardNumber'] = 'required|numeric|digits_between:13,19';
                $rules['cardExpiry'] = ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/?([0-9]{2})$/'];
                $rules['cardCvv'] = 'required|numeric|digits_between:3,4';
            }
            $this->validate($rules);

            // Lógica de Upsell/Downsell para cartão
            switch ($this->selectedPlan) {
                case 'monthly':
                case 'quarterly':
                    if (isset($this->plans['semi-annual'])) {
                        $this->showUpsellModal = true;
                        $offerValue = round(
                            $this->plans['semi-annual']['prices'][$this->selectedCurrency]['descont_price']
                                / $this->plans['semi-annual']['nunber_months'],
                            1
                        );

                        $offerDiscont = (
                            $this->plans[$this->selectedPlan]['prices'][$this->selectedCurrency]['origin_price']
                            * $this->plans['semi-annual']['nunber_months']
                        ) - ($offerValue * $this->plans['semi-annual']['nunber_months']);

                        $this->modalData = [
                            'actual_month_value'    => $this->totals['month_price_discount'],
                            'offer_month_value'     => number_format($offerValue, 2, ',', '.'),
                            'offer_total_discount'  => number_format($offerDiscont, 2, ',', '.'),
                            'offer_total_value'     => number_format(
                                $this->plans['semi-annual']['prices'][$this->selectedCurrency]['descont_price'],
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
            $offerValue = round($this->plans['quarterly']['prices'][$this->selectedCurrency]['descont_price'] / $this->plans['quarterly']['nunber_months'], 1);
            // Corrected discount calculation for downsell
            $basePriceForDiscountCalc = $this->plans['monthly']['prices'][$this->selectedCurrency]['origin_price']; // Price of the plan they *were* on
            $offerDiscont = ($basePriceForDiscountCalc * $this->plans['quarterly']['nunber_months']) - ($offerValue * $this->plans['quarterly']['nunber_months']);

            $this->modalData = [
                'actual_month_value' => $this->totals['month_price_discount'], // This should be from the current 'monthly' plan
                'offer_month_value' => number_format($offerValue, 2, ',', '.'),
                'offer_total_discount' => number_format(abs($offerDiscont), 2, ',', '.'), // Ensure positive discount
                'offer_total_value' => number_format($this->plans['quarterly']['prices'][$this->selectedCurrency]['descont_price'], 2, ',', '.'),
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
        $this->paymentGateway = app(PaymentGatewayFactory::class)->create();
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

            if (isset($response['data']['redirect_url']) && !empty($response['data']['redirect_url'])) {
                $customerId = $response['data']['customerId'] ?? null;
                $upsell_productId = $response['data']['upsell_productId'] ?? null;
                $redirectUrl = $response['data']['redirect_url'];

                // Construir a URL de redirecionamento com os parâmetros, se existirem
                $queryParams = http_build_query(array_filter([
                    'customerId' => $customerId,
                    'upsell_productId' => $upsell_productId,
                ]));

                return redirect()->to($redirectUrl . '?' . $queryParams);
            } else {
                // Fallback para o caso de a URL de redirecionamento não estar no data
                $redirectUrl = $response['redirect_url'] ?? "https://web.snaphubb.online/obg/";
                return redirect()->to($redirectUrl);
            }
        } else {
            Log::error('PagePay: Payment failed via gateway.', [
                'gateway' => get_class($this->paymentGateway),
                'response' => $response
            ]);
            $errorMessage = $response['message'] ?? 'An unknown error occurred during payment.';
            if (!empty($response['errors'])) {
                $errorMessage .= ' Details: ' . implode(', ', (array)$response['errors']);
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

            // Carregar dados do MOCK para calcular o preço do PIX
            $mockPath = resource_path('mock/get-plans.json');
            if (!file_exists($mockPath)) {
                Log::error('generatePix: Mock não encontrado em ' . $mockPath);
                $this->errorMessage = __('payment.plan_not_loaded') ?? 'Plano não disponível no momento. Tente novamente mais tarde.';
                $this->showErrorModal = true;
                $this->showProcessingModal = false;
                return;
            }

            $mockJson = file_get_contents($mockPath);
            $mockData = json_decode($mockJson, true);
            
            if (!is_array($mockData) || empty($mockData)) {
                Log::error('generatePix: Mock inválido (parse falhou ou vazio)');
                $this->errorMessage = __('payment.plan_not_loaded') ?? 'Plano não disponível no momento. Tente novamente mais tarde.';
                $this->showErrorModal = true;
                $this->showProcessingModal = false;
                return;
            }

            // Verificar se o plano selecionado existe no mock
            if (!isset($mockData[$this->selectedPlan])) {
                Log::error('generatePix: Plano não encontrado no mock', [
                    'selectedPlan' => $this->selectedPlan,
                    'available_plans' => array_keys($mockData)
                ]);
                $this->errorMessage = __('payment.plan_not_loaded') ?? 'Plano não disponível no momento. Tente novamente mais tarde.';
                $this->showErrorModal = true;
                $this->showProcessingModal = false;
                return;
            }

            // Extrair dados do plano do mock
            $plan = $mockData[$this->selectedPlan];
            
            // Procurar a moeda com preço disponível (prioridade: selecionada > BRL > USD)
            $availableCurrency = null;
            if (isset($plan['prices'][$this->selectedCurrency])) {
                $availableCurrency = $this->selectedCurrency;
            } elseif (isset($plan['prices']['BRL'])) {
                $availableCurrency = 'BRL';
            } elseif (isset($plan['prices']['USD'])) {
                $availableCurrency = 'USD';
            }

            if (!$availableCurrency) {
                Log::error('generatePix: Nenhuma moeda válida encontrada no mock', [
                    'selectedPlan' => $this->selectedPlan,
                    'available_currencies' => array_keys($plan['prices'] ?? [])
                ]);
                $this->errorMessage = __('payment.plan_not_loaded') ?? 'Plano não disponível no momento. Tente novamente mais tarde.';
                $this->showErrorModal = true;
                $this->showProcessingModal = false;
                return;
            }

            // Calcular preço final
            $prices = $plan['prices'][$availableCurrency];
            $finalPrice = $prices['descont_price'];

            // Adicionar bumps se ativos
            foreach ($this->bumps as $bump) {
                if (!empty($bump['active'])) {
                    $finalPrice += floatval($bump['price']);
                }
            }

            // Calcula o valor final em centavos
            $amountInCents = (int)round($finalPrice * 100);

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
            $this->pixAmount = $numeric_final_price;
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

        // Para Pushing Pay, confiamos no webhook para notificar o status
        // Não há rota pública para consultar status individual
        Log::debug('PagePay: Aguardando notificação via webhook do PIX', [
            'payment_id' => $this->pixTransactionId,
        ]);
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
            $redirectUrl = url('/upsell/painel-das-garotas');
            Log::info('PagePay: DISPATCHING REDIRECT', [
                'url' => $redirectUrl,
                'payment_id' => $this->pixTransactionId,
            ]);
            
            $this->dispatch('redirect-success', url: $redirectUrl);
            
            Log::info('PagePay: REDIRECT DISPATCH SUCCESSFUL', [
                'url' => $redirectUrl,
            ]);
        } catch (\Throwable $e) {
            Log::error('PagePay: REDIRECT DISPATCH FAILED', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Fallback: Try redirect via HTTP instead of Livewire
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
            session(['locale' => $lang]);
            app()->setLocale($lang);
            $this->selectedLanguage = $lang;
            $this->selectedCurrency = $lang === 'br' ? 'BRL'
                : ($lang === 'en' ? 'USD'
                    : ($lang === 'es' ? 'EUR' : 'BRL'));
            
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
            
            $this->testimonials = trans('checkout.testimonials');
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
        return view('livewire.page-pay')->layoutData([
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

    public function updatedSelectedPaymentMethod($value)
    {
        if ($value === 'pix') {
            // Salvar o estado atual dos bumps antes de desativá-los
            $this->lastBumpsState = collect($this->bumps)->pluck('active', 'id')->all();

            foreach ($this->bumps as $index => $bump) {
                $this->bumps[$index]['active'] = false;
            }
            $this->calculateTotals();
        } else {
            // Se voltar para cartão de crédito, restaurar o estado anterior dos bumps
            if (!empty($this->lastBumpsState)) {
                foreach ($this->bumps as $index => $bump) {
                    if (isset($this->lastBumpsState[$bump['id']])) {
                        $this->bumps[$index]['active'] = $this->lastBumpsState[$bump['id']];
                    }
                }
                $this->calculateTotals();
                // Limpar o estado salvo
                $this->lastBumpsState = [];
            }
        }
    }

    private function updateProductDetails()
    {
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
        
        if (!isset($currentPlanDetails['prices'][$this->selectedCurrency])) {
            Log::error('preparePIXData: Moeda não encontrada', [
                'plan' => $this->selectedPlan,
                'currency' => $this->selectedCurrency,
                'available_currencies' => array_keys($currentPlanDetails['prices'] ?? [])
            ]);
            throw new \Exception('Moeda não disponível para este plano');
        }
        
        $currentPlanPriceInfo = $currentPlanDetails['prices'][$this->selectedCurrency];
        $numeric_final_price = floatval($currentPlanPriceInfo['descont_price']);
        
        Log::info('preparePIXData: Valor calculado do MOCK', [
            'plan' => $this->selectedPlan,
            'currency' => $this->selectedCurrency,
            'price' => $numeric_final_price,
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
            'price' => (int)round(floatval($currentPlanPriceInfo['descont_price']) * 100),
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
            
            // IMPORTANTE: Recarregar planos do MOCK para PIX
            // (pois na blade o cartão usa API, mas PIX precisa do mock)
            $this->plans = $this->getPlansFromMock();
            Log::info('generatePixPayment: Recarregando planos do MOCK para PIX');
            
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
                $this->pixAmount = $pixData['amount'] ?? ($totalAmount / 100);
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
