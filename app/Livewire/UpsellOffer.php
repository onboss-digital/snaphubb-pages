<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UpsellOffer extends Component
{
    public $product;
    public $usingMock = true;

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
    }

    public function render()
    {
        return view('livewire.upsell-offer');
    }

    public function aproveOffer()
    {
        // Create PIX for this upsell using existing backend controller flow
        try {
            $customer = $this->getCustomerData();
            if (!$customer || empty($customer['email'])) {
                $this->errorMessage = 'Dados do cliente não disponíveis. Faça login ou use o mesmo navegador da compra.';
                return;
            }

            $pixData = [
                'amount' => $this->product['price'],
                'currency_code' => $this->product['currency'] ?? 'BRL',
                'plan_key' => $this->product['hash'] ?? 'painel_das_garotas',
                'offer_hash' => $this->product['hash'] ?? 'painel_das_garotas',
                'customer' => [
                    'name' => $customer['name'] ?? 'Cliente',
                    'email' => $customer['email'],
                    'phone_number' => $customer['phone'] ?? null,
                    'document' => $customer['document'] ?? null,
                ],
                'cart' => [[
                    'product_hash' => $this->product['hash'] ?? null,
                    'title' => $this->product['label'],
                    'price' => $this->product['price'],
                    'quantity' => 1,
                    'operation_type' => 1,
                ]],
                'metadata' => [
                    'payment_method' => 'pix',
                    'is_upsell' => true,
                    'webhook_url' => url('/api/pix/webhook'),
                ],
            ];

            // Call PixController directly (same pattern as PagePay)
            $controller = new \App\Http\Controllers\PixController(app(\App\Services\MercadoPagoPixService::class));
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
