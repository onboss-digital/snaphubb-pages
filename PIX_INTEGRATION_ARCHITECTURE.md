# 🏗️ ARQUITETURA DE INTEGRAÇÃO PIX COM STRIPE

## 📋 ÍNDICE
1. [Conceito](#conceito)
2. [Fluxo Atual (Stripe)](#fluxo-atual-stripe)
3. [Nova Solução (PIX + Stripe)](#nova-solução-pix--stripe)
4. [Arquitetura Recomendada](#arquitetura-recomendada)
5. [Implementação Detalhada](#implementação-detalhada)
6. [Segurança](#segurança)

---

## 🎯 CONCEITO

**Objetivo:** Reutilizar o valor e plano que já vem da API Stripe para processar pagamentos PIX, mantendo sincronização total.

**Regra de Ouro:** Um único valor, dois gateways possíveis.

---

## 🔄 FLUXO ATUAL (STRIPE)

```
1. User acessa página
   ↓
2. mount() carrega planos da API
   ↓
3. calculateTotals() formata preço
   └─ $this->totals['final_price'] = "24,90"
   ↓
4. User seleciona plano
   ↓
5. User clica "Processar Pagamento"
   ↓
6. startCheckout() valida dados
   ↓
7. prepareCheckoutData() estrutura dados
   └─ amount: 2490 (centavos)
   └─ currency_code: "BRL"
   └─ offer_hash: "prod_SZ4hJ7Q5aDSvVP"
   └─ selected_plan_key: "monthly"
   ↓
8. sendCheckout() envia para Stripe
   ↓
9. StripeGateway->processPayment()
   ↓
10. Stripe processa e retorna resultado
```

**Problema Identificado:**
- ❌ Valor é calculado no front-end apenas quando Stripe é acionado
- ❌ PIX recalcula o valor independentemente (duplicação)
- ❌ Não há sincronização de estado

---

## ✅ NOVA SOLUÇÃO (PIX + STRIPE)

### **Princípio: Sincronizar Fonte de Verdade**

```
FONTE DE VERDADE: $this->totals (sempre sincronizado)
        ↓
        ├─→ Stripe (via prepareCheckoutData)
        │   └─ amount = $this->totals['final_price'] * 100
        │
        └─→ PIX (via preparePIXData)
            └─ amount = $this->totals['final_price'] * 100
            └ MESMO VALOR!
```

### **Novo Fluxo PIX**

```
1. User seleciona PIX card
   └─ selectedPaymentMethod = 'pix'
   ↓
2. User preenche dados PIX
   └─ pixName, pixEmail, pixCpf, pixPhone
   ↓
3. User clica "Gerar PIX"
   ↓
4. generatePixPayment() é chamado
   ↓
5. preparePIXData() executa:
   ├─ Valida campos obrigatórios
   ├─ Extrai valor de $this->totals (NÃO recalcula)
   ├─ Extrai plano de $this->selectedPlan
   ├─ Estrutura dados para PIX
   └─ Retorna objeto preparado
   ↓
6. MercadoPagoPixService->createPixPayment()
   ├─ Recebe objeto com:
   │  └─ amount: valor exato da Stripe
   │  └─ plan_key: mesmo plano selecionado
   │  └─ customer_data: mesmo cliente
   │
   └─ Envia para Mercado Pago
   ↓
7. Mercado Pago gera QR Code
   ↓
8. Frontend exibe QR Code
```

---

## 🏛️ ARQUITETURA RECOMENDADA

### **Frontend (Livewire)**

```
PagePay Component
├─ $this->totals (Fonte de Verdade)
│  └─ Sincronizado ao selecionar plano
│
├─ preparePIXData() [NOVO]
│  └─ Reutiliza $this->totals
│  └─ Estrutura dados para PIX
│  └─ Retorna array com:
│     ├─ amount (centavos)
│     ├─ currency_code
│     ├─ plan_key
│     ├─ customer_data
│     └─ metadata (mesmo Stripe)
│
├─ generatePixPayment() [MODIFICADO]
│  ├─ Valida campos PIX
│  ├─ Chama preparePIXData()
│  ├─ Envia para backend OU Mercado Pago
│  └─ Exibe QR Code
│
└─ syncTotals() [NOVO - opcional]
   └─ Garante sincronização ao trocar plano
```

### **Backend (Laravel)**

```
Routes
├─ POST /api/pix/create [NOVO]
│  └─ Recebe dados do PIX preparados
│  └─ Valida valor vs plano (segurança)
│  └─ Chama MercadoPagoService
│  └─ Retorna QR Code
│
└─ POST /checkout (EXISTENTE - Stripe)
   └─ Recebe dados estruturados
   └─ Processa com Stripe
```

### **Estrutura de Dados**

```php
// Objeto preparado para ambos gateways
$paymentData = [
    'amount' => 2490,                    // centavos
    'currency_code' => 'BRL',
    'plan_key' => 'monthly',             // monthly, semi-annual, quarterly
    'offer_hash' => 'prod_SZ4hJ7Q5aDSvVP',
    'customer' => [
        'name' => 'João Silva',
        'email' => 'joao@example.com',
        'phone' => '+5511999999999',
        'cpf' => '12345678901',          // Apenas para PIX (opcional BR)
    ],
    'cart' => [...],                     // items inclusos no plano
    'metadata' => [
        'utm_source' => '...',
        'utm_medium' => '...',
        // ... outros UTM params
    ],
    'gateway' => 'stripe' | 'mercadopago' // Identifica qual usar
];
```

---

## 🔧 IMPLEMENTAÇÃO DETALHADA

### **PASSO 1: Função preparePIXData() no Frontend**

```php
// app/Livewire/PagePay.php

private function preparePIXData(): array
{
    // 1. EXTRAIR VALOR DA FONTE DE VERDADE
    $numeric_final_price = floatval(
        str_replace(',', '.', 
            str_replace('.', '', $this->totals['final_price'])
        )
    );
    
    // 2. PREPARAR DADOS DO CLIENTE
    $customerData = [
        'name' => $this->pixName,
        'email' => $this->pixEmail,
        'phone_number' => preg_replace('/[^0-9+]/', '', $this->pixPhone),
    ];
    
    if ($this->selectedLanguage === 'br' && $this->pixCpf) {
        $customerData['document'] = preg_replace('/\D/', '', $this->pixCpf);
    }
    
    // 3. PREPARAR ITENS DO CARRINHO (MESMO QUE STRIPE)
    $cartItems = [];
    $currentPlanDetails = $this->plans[$this->selectedPlan];
    $currentPlanPriceInfo = $currentPlanDetails['prices'][$this->selectedCurrency];
    
    $cartItems[] = [
        'product_hash' => $currentPlanDetails['hash'],
        'title' => $this->product['title'] . ' - ' . $currentPlanDetails['label'],
        'price' => (int)round(floatval($currentPlanPriceInfo['descont_price']) * 100),
        'quantity' => 1,
        'operation_type' => 1,
    ];
    
    // 4. ADICIONAR ORDER BUMPS (se houver)
    foreach ($this->bumps as $bump) {
        if (!empty($bump['active'])) {
            $cartItems[] = [
                'product_hash' => $bump['hash'],
                'title' => $bump['title'],
                'price' => (int)round(floatval($bump['price']) * 100),
                'quantity' => 1,
                'operation_type' => 2,
            ];
        }
    }
    
    // 5. ESTRUTURAR DADOS FINAIS
    return [
        'amount' => (int)round($numeric_final_price * 100),
        'currency_code' => $this->selectedCurrency,
        'offer_hash' => $currentPlanDetails['hash'],
        'plan_key' => $this->selectedPlan,
        'customer' => $customerData,
        'cart' => $cartItems,
        'gateway' => 'mercadopago',
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
```

### **PASSO 2: Modificar generatePixPayment()**

```php
public function generatePixPayment()
{
    try {
        // VALIDAR CAMPOS
        $errors = [];
        if (empty($this->pixName) || strlen(trim($this->pixName)) === 0) {
            $errors[] = __('payment.pix_field_name_label') . ' é obrigatório';
        }
        if (empty($this->pixEmail) || !filter_var($this->pixEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = __('payment.pix_field_email_label') . ' é obrigatório';
        }
        if (empty($this->pixCpf) || !$this->isValidCpf($this->pixCpf)) {
            $errors[] = __('payment.pix_field_cpf_label') . ' é obrigatório';
        }
        
        if (!empty($errors)) {
            $this->errorMessage = implode("\n", $errors);
            $this->showErrorModal = true;
            return;
        }
        
        // PREPARAR DADOS (nova função)
        $pixData = $this->preparePIXData();
        
        // MOSTRAR PROCESSAMENTO
        $this->showProcessingModal = true;
        $this->loadingMessage = __('payment.loader_processing');
        
        // ENVIAR PARA BACKEND OU MERCADO PAGO DIRETO
        // Opção 1: Enviar para seu backend (RECOMENDADO para segurança)
        $response = $this->sendPixToBackend($pixData);
        
        // Opção 2: Enviar direto para Mercado Pago (menos seguro)
        // $response = $this->pixService->createPixPayment($pixData);
        
        if ($response['status'] === 'success' && isset($response['data'])) {
            $pixDataResponse = $response['data'];
            $this->pixTransactionId = $pixDataResponse['payment_id'] ?? null;
            $this->pixQrImage = $pixDataResponse['qr_code_base64'] ?? null;
            $this->pixQrCodeText = $pixDataResponse['qr_code'] ?? null;
            $this->pixAmount = $pixDataResponse['amount'] ?? null;
            $this->pixExpiresAt = $pixDataResponse['expiration_date'] ?? null;
            $this->pixStatus = 'pending';
            $this->showPixModal = true;
            $this->showProcessingModal = false;
        } else {
            $this->errorMessage = $response['message'] ?? __('payment.pix_generation_failed');
            $this->showErrorModal = true;
            $this->showProcessingModal = false;
        }
    } catch (\Exception $e) {
        Log::error('Erro ao gerar PIX: ' . $e->getMessage());
        $this->errorMessage = __('payment.pix_generation_error');
        $this->showErrorModal = true;
        $this->showProcessingModal = false;
    }
}

private function sendPixToBackend(array $pixData): array
{
    try {
        $response = $this->httpClient->post(
            config('app.url') . '/api/pix/create',
            [
                'json' => $pixData,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            ]
        );
        
        return json_decode($response->getBody()->getContents(), true);
    } catch (\Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Erro ao conectar com servidor de pagamento'
        ];
    }
}
```

### **PASSO 3: Nova Rota no Backend**

```php
// routes/api.php

Route::post('/pix/create', [PixController::class, 'create'])->name('pix.create');
```

### **PASSO 4: Novo Controller PIX**

```php
// app/Http/Controllers/PixController.php

<?php

namespace App\Http\Controllers;

use App\Services\MercadoPagoPixService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PixController extends Controller
{
    private MercadoPagoPixService $pixService;
    
    public function __construct(MercadoPagoPixService $pixService)
    {
        $this->pixService = $pixService;
    }
    
    public function create(Request request)
    {
        try {
            // 1. VALIDAR DADOS RECEBIDOS
            $validated = $request->validate([
                'amount' => 'required|integer|min:100',
                'currency_code' => 'required|string|in:BRL,USD,EUR',
                'plan_key' => 'required|string|in:monthly,semi-annual,quarterly',
                'customer' => 'required|array',
                'customer.name' => 'required|string',
                'customer.email' => 'required|email',
                'customer.document' => 'required|string',
                'metadata' => 'nullable|array',
            ]);
            
            // 2. VALIDAR SEGURANÇA: Checar se o valor é válido para o plano
            // (IMPLEMENTAR: Buscar valor esperado da API Stripe e comparar)
            if (!$this->isValidAmountForPlan($validated['amount'], $validated['plan_key'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Valor não corresponde ao plano selecionado'
                ], 422);
            }
            
            // 3. CHAMAR SERVIÇO PIX
            $pixData = [
                'amount' => $validated['amount'],
                'description' => 'Assinatura SnapHubb - ' . $validated['plan_key'],
                'customerName' => $validated['customer']['name'],
                'customerEmail' => $validated['customer']['email'],
                'customerDocument' => $validated['customer']['document'] ?? null,
            ];
            
            $response = $this->pixService->createPixPayment($pixData);
            
            // 4. RETORNAR RESPOSTA
            if ($response['status'] === 'success') {
                return response()->json([
                    'status' => 'success',
                    'data' => $response['data']
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $response['message'] ?? 'Erro ao criar PIX'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('PIX Controller Error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar pagamento PIX'
            ], 500);
        }
    }
    
    /**
     * Validar se o valor é correto para o plano
     * (Implementar conforme sua lógica de preços)
     */
    private function isValidAmountForPlan(int $amount, string $planKey): bool
    {
        // IMPLEMENTAÇÃO: Buscar plano da API Stripe
        // e validar se o amount corresponde ao descont_price * 100
        
        // Placeholder:
        $validAmounts = [
            'monthly' => 2490,      // R$ 24,90
            'semi-annual' => 11940, // R$ 119,40 (6 meses)
            'quarterly' => 5980,    // R$ 59,80 (3 meses)
        ];
        
        return ($validAmounts[$planKey] ?? 0) === $amount;
    }
}
```

---

## 🔒 SEGURANÇA

### **1. Validação no Backend**
```php
// Sempre validar no servidor
- Amount corresponde ao plan_key
- Customer email é válido
- CPF é válido (algoritmo)
- Nenhum trust no cliente
```

### **2. Tokens e Credenciais**
```env
# .env - NUNCA expor tokens no frontend
MP_ACCESS_TOKEN_SANDBOX=...
MP_ACCESS_TOKEN_PROD=...

# Comunicação backend-to-backend apenas
```

### **3. Logs Estruturados**
```php
Log::info('PIX Payment Created', [
    'transaction_id' => $transactionId,
    'amount' => $amount,
    'customer_email' => $email,
    'plan_key' => $planKey,
    'timestamp' => now(),
]);
```

---

## 📊 COMPARAÇÃO: ANTES vs DEPOIS

### **ANTES (Apenas Stripe)**
```
Só funciona com Stripe
Não há sincronização PIX
Valor duplicado em cálculos
Sem validação servidor-side para PIX
```

### **DEPOIS (Stripe + PIX Sincronizado)**
```
✅ Mesmo valor para ambos gateways
✅ Sincronizado automaticamente ao trocar plano
✅ Validação forte no backend
✅ Estrutura preparada para múltiplos gateways
✅ Sem duplicação de lógica
✅ Seguro e auditável
```

---

## 📝 CHECKLIST DE IMPLEMENTAÇÃO

- [ ] Criar função `preparePIXData()`
- [ ] Modificar `generatePixPayment()`
- [ ] Adicionar método `sendPixToBackend()`
- [ ] Criar rota `/api/pix/create`
- [ ] Criar `PixController.php`
- [ ] Implementar validação de amount
- [ ] Testar sincronização de valores
- [ ] Testar com diferentes planos
- [ ] Testar com order bumps
- [ ] Validar segurança end-to-end
- [ ] Deploy em produção

---

**Última Atualização:** Novembro 16, 2025
**Status:** Pronto para implementação
