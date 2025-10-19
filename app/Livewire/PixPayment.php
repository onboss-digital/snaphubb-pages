<?php

namespace App\Livewire;

use App\Factories\PaymentGatewayFactory;
use App\Interfaces\PaymentGatewayInterface;
use App\Rules\ValidPhoneNumber;
use Livewire\Component;

class PixPayment extends Component
{
    public $showPixModal = false;
    public $pixModalStep = 'form';
    public $pixQrCode = null;
    public $pixCopyPaste = null;
    public $pixTransactionId = null;

    public $cardName, $email, $phone, $cpf;

    protected $listeners = ['showPixModal' => 'showModal'];

    public function showModal()
    {
        $this->resetPixModal();
        $this->showPixModal = true;
    }

    public function startPixCheckout()
    {
        $this->validate([
            'cardName' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => ['required', 'string', new ValidPhoneNumber],
            'cpf' => ['required', 'string', 'regex:/^\d{3}\.\d{3}\.\d{3}\-\d{2}$|^\d{11}$/'],
        ]);

        $checkoutData = $this->prepareCheckoutData();
        $paymentGateway = app(PaymentGatewayFactory::class)->create('mercado_pago');
        $response = $paymentGateway->processPayment($checkoutData);

        if ($response['status'] === 'success' && isset($response['data']['qr_code_base64'])) {
            $this->pixQrCode = $response['data']['qr_code_base64'];
            $this->pixCopyPaste = $response['data']['qr_code'];
            $this->pixTransactionId = $response['data']['transaction_id'];
            $this->pixModalStep = 'qr_code';
            $this->dispatch('start-pix-polling');
        } else {
            $errorMessage = $response['message'] ?? 'An unknown error occurred while generating the PIX.';
            $this->addError('pix', $errorMessage);
        }
    }

    public function checkPixPaymentStatus()
    {
        if (!$this->pixTransactionId) {
            return;
        }

        $paymentGateway = app(PaymentGatewayFactory::class)->create('mercado_pago');
        $status = $paymentGateway->getPaymentStatus($this->pixTransactionId);

        if ($status === 'approved') {
            $this->dispatch('stop-pix-polling');
            return redirect()->to('https://web.snaphubb.online/obg-br');
        } elseif (in_array($status, ['cancelled', 'failed'])) {
            $this->dispatch('stop-pix-polling');
            $this->showPixModal = false;
        }
    }

    private function prepareCheckoutData()
    {
        $customerData = [
            'name' => $this->cardName,
            'email' => $this->email,
            'phone_number' => preg_replace('/[^0-9+]/', '', $this->phone),
            'document' => preg_replace('/\D/', '', $this->cpf),
        ];

        $pixAmount = 2490; // R$ 24,90 in cents

        return [
            'amount' => $pixAmount,
            'currency_code' => 'BRL',
            'offer_hash' => 'pix_offer_hash_placeholder',
            'payment_method' => 'pix',
            'customer' => $customerData,
            'cart' => [
                [
                    'product_hash' => 'pix_product_hash_placeholder',
                    'title' => 'SNAPHUBB PREMIUM (PIX)',
                    'price' => $pixAmount,
                    'quantity' => 1,
                    'operation_type' => 1,
                ]
            ],
            'installments' => 1,
            'selected_plan_key' => 'pix_plan',
            'language' => 'br',
            'metadata' => [
                'product_main_hash' => 'pix_product_hash_placeholder',
            ]
        ];
    }

    public function resetPixModal()
    {
        $this->reset(['pixModalStep', 'pixQrCode', 'pixCopyPaste', 'pixTransactionId']);
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.pix-payment');
    }
}
