<?php

namespace App\Livewire;

use App\Factories\PaymentGatewayFactory;
use App\Rules\ValidPhoneNumber;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class PixPayment extends Component
{
    // Properties passed from PagePay
    public $product;
    public $totals;
    public $plans;
    public $selectedPlan;
    public $selectedCurrency;
    public $utm_source, $utm_medium, $utm_campaign, $utm_id, $utm_term, $utm_content, $src, $sck;


    // PIX Properties
    public $pix_name, $pix_email, $pix_phone, $pix_cpf;
    public $showPixModal = false;
    public $pixQrCode;
    public $pixQrCodeBase64;
    public $pixTransactionId;

    // Modal Controls
    public $showLodingModal = false;
    public $loadingMessage = '';
    public $showErrorModal = false;
    public $errorMessage = '';
    public $showSuccessModal = false;


    protected function rules()
    {
        return [
            'pix_name' => 'required|string|max:255',
            'pix_email' => 'required|email',
            'pix_cpf' => ['required', 'string', 'regex:/^\d{3}\.\d{3}\.\d{3}\-\d{2}$/'],
            'pix_phone' => ['nullable', 'string'],
        ];
    }

    public function mount($product, $totals, $plans, $selectedPlan, $selectedCurrency, $utm_source, $utm_medium, $utm_campaign, $utm_id, $utm_term, $utm_content, $src, $sck)
    {
        $this->product = $product;
        $this->totals = $totals;
        $this->plans = $plans;
        $this->selectedPlan = $selectedPlan;
        $this->selectedCurrency = $selectedCurrency;
        $this->utm_source = $utm_source;
        $this->utm_medium = $utm_medium;
        $this->utm_campaign = $utm_campaign;
        $this->utm_id = $utm_id;
        $this->utm_term = $utm_term;
        $this->utm_content = $utm_content;
        $this->src = $src;
        $this->sck = $sck;
    }

    public function startPixCheckout()
    {
        Log::info('startPixCheckout: Method initiated.');

        try {
            $this->validate();
            Log::info('startPixCheckout: Validation successful.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('startPixCheckout: Validation failed.', ['errors' => $e->errors()]);
            $this->dispatch('validation:failed');
            throw $e;
        }

        $this->showLodingModal = true;
        $this->loadingMessage = __('payment.generating_pix');

        try {
            $checkoutData = $this->preparePixCheckoutData();
            $paymentGateway = app(PaymentGatewayFactory::class)->create('mercadopago');
            $response = $paymentGateway->processPayment($checkoutData);

            if ($response['status'] === 'success') {
                $this->pixQrCode = $response['data']['qr_code'];
                $this->pixQrCodeBase64 = $response['data']['qr_code_base64'];
                $this->pixTransactionId = $response['data']['transaction_id'];
                $this->dispatch('pix-generated');
                Log::info('startPixCheckout: PIX generated successfully.');
            } else {
                $this->errorMessage = $response['message'] ?? __('payment.pix_generation_error');
                $this->showErrorModal = true;
                Log::error('startPixCheckout: PIX generation failed.', ['response' => $response]);
            }
        } catch (\Exception $e) {
            Log::channel('payment_checkout')->error('PIX Checkout Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->errorMessage = __('payment.provider_connection_error');
            $this->showErrorModal = true;
        } finally {
            $this->showLodingModal = false;
        }
    }

    private function preparePixCheckoutData()
    {
        $customerData = [
            'name' => $this->pix_name,
            'email' => $this->pix_email,
            'phone_number' => preg_replace('/[^0-9+]/', '', $this->pix_phone),
            'document' => preg_replace('/\D/', '', $this->pix_cpf),
        ];

        $numeric_final_price = floatval(str_replace(',', '.', str_replace('.', '', $this->totals['final_price'])));

        $currentPlanDetails = $this->plans[$this->selectedPlan];
        $currentPlanPriceInfo = $currentPlanDetails['prices'][$this->selectedCurrency];

        $cartItems[] = [
            'product_hash' => $currentPlanDetails['hash'],
            'title' => 'SNAPHUBB PREMIUM (PIX)',
            'price' => (int)round(floatval($currentPlanPriceInfo['descont_price']) * 100),
            'price_id' => $this->product['price_id'] ?? null,
            'recurring' => $this->plans[$this->selectedPlan]['prices'][$this->selectedCurrency]['recurring'] ?? null,
            'quantity' => 1,
            'operation_type' => 1,
        ];

        return [
            'amount' => (int)round($numeric_final_price * 100),
            'currency_code' => $this->selectedCurrency,
            'offer_hash' => $currentPlanDetails['hash'],
            'payment_method' => 'pix',
            'customer' => $customerData,
            'cart' => $cartItems,
            'installments' => 1,
            'selected_plan_key' => $this->selectedPlan,
            'language' => 'br',
            'metadata' => [
                'product_main_hash' => $this->product['hash'],
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
    }

    public function checkPixPaymentStatus()
    {
        if ($this->pixTransactionId) {
            $paymentGateway = app(PaymentGatewayFactory::class)->create('mercadopago');
            $response = $paymentGateway->getPaymentStatus($this->pixTransactionId);

            if ($response['status'] === 'success') {
                $status = $response['data']['status'];
                if ($status === 'approved') {
                    $this->showPixModal = false;
                    $this->showSuccessModal = true;

                    $purchaseData = [
                        'transaction_id' => $this->pixTransactionId,
                        'value' => floatval(str_replace(',', '.', str_replace('.', '', $this->totals['final_price']))),
                        'currency' => 'BRL',
                        'content_ids' => [$this->product['hash']],
                        'content_type' => 'product',
                    ];

                    $this->dispatch('pix-paid', pixData: $purchaseData);

                    return redirect()->to('https://web.snaphubb.online/obg-br');
                } elseif (in_array($status, ['rejected', 'cancelled', 'expired'])) {
                    $this->showPixModal = false;
                    $this->errorMessage = __('payment.pix_expired');
                    $this->showErrorModal = true;
                    $this->dispatch('stop-polling');
                    return redirect()->to('https://web.snaphubb.online/fail-br');
                }
            }
        }
    }

    public function openPixModal()
    {
        $this->resetPixModal();
        $this->showPixModal = true;
    }

    public function closeModal()
    {
        $this->showPixModal = false;
        $this->showErrorModal = false;
        $this->showSuccessModal = false;
        $this->pixQrCodeBase64 = null;
        $this->pixQrCode = null;
        $this->dispatch('pix-modal-closed');
    }

    public function resetPixModal()
    {
        $this->reset(['pix_name', 'pix_email', 'pix_phone', 'pix_cpf', 'pixQrCode', 'pixQrCodeBase64', 'pixTransactionId']);
        $this->resetErrorBag();
    }

    public function updatePixPhone($event)
    {
        $this->pix_phone = $event['phone'];
    }

    public function getListeners()
    {
        return [
            'updatePixPhone' => 'updatePixPhone',
            'open-pix-modal' => 'openPixModal'
        ];
    }

    public function render()
    {
        return view('livewire.pix-payment');
    }
}
