# Implementação do PIX no Backend - Guia Técnico

**Última Atualização:** 16 de Novembro de 2025  
**Status:** Pronto para Implementação  
**Autor:** GitHub Copilot

---

## 📋 Visão Geral

Atualmente, sua arquitetura funciona assim:

```
┌─────────────────────────────────────────────────────┐
│           Frontend (PagePay.php)                    │
├─────────────────────────────────────────────────────┤
│  1. getPlans() → GET https://snaphubb.com/api/get-plans
│  2. calculateTotals() → Calcula $totals['final_price']
│  3. Stripe: prepareCheckoutData() (já implementado)
│  4. PIX: preparePIXData() (já implementado)         │
└─────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────┐
│           Backend (PixController.php)               │
├─────────────────────────────────────────────────────┤
│  POST /api/pix/create                              │
│  └─ Valida: isValidAmountForPlan()                 │
│     └─ ❌ Hoje: Usa API externa                     │
│     └─ ✅ Objetivo: Usar banco de dados local       │
└─────────────────────────────────────────────────────┘
                        ↓
┌─────────────────────────────────────────────────────┐
│           Mercado Pago (Gateway)                    │
└─────────────────────────────────────────────────────┘
```

---

## 🎯 O Que Precisa Ser Feito no Backend

### 1️⃣ MODELO `Plan` (Eloquent)

Você precisa ter um modelo `Plan` que reflita a estrutura do banco.

**Onde deve estar:**  
`app/Models/Plan.php` (ou `Modules/Subscriptions/Models/Plan.php`)

**Estrutura esperada:**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $table = 'plans';
    
    protected $fillable = [
        'name',                          // "Streaming snaphubb - BR"
        'identifier',                    // "monthly", "quarterly", "annual"
        'key',                           // Chave única: "monthly", "quarterly", etc
        'price',                         // Preço base em centavos: 1990 (R$ 19,90)
        'currency',                      // "BRL", "USD", "EUR"
        'duration',                      // "month", "week", "year"
        'duration_value',                // 1, 3, 6, 12
        'level',                         // "Level 1", "Level 2", etc
        'status',                        // "active", "inactive"
        'description',                   // Descrição do plano
        'language',                      // "br", "en", "es"
        'pages_product_external_id',     // ID externo do Stripe
        'custom_gateway',                // "stripe", "tribopay", etc
        'external_product_id',           // ID do gateway externo
        'pages_upsell_url',              // URL do upsell
    ];

    protected $casts = [
        'price' => 'integer',
        'duration_value' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function orderBumps()
    {
        return $this->hasMany(OrderBump::class, 'plan_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Retorna o preço formatado em reais (centavos para decimal)
     * Exemplo: 1990 → 19.90
     */
    public function getPriceInDecimals(): float
    {
        return $this->price / 100;
    }

    /**
     * Buscar plano por identifier/key
     */
    public static function findByKey(string $key): ?self
    {
        return self::where('key', $key)
            ->orWhere('identifier', $key)
            ->first();
    }
}
```

---

### 2️⃣ MIGRATION (caso não tenha)

Se a tabela `plans` não existir, crie:

```bash
php artisan make:migration create_plans_table
```

**Conteúdo da migration:**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            
            // Identificação
            $table->string('name'); // "Streaming snaphubb - BR"
            $table->string('identifier')->unique(); // "monthly"
            $table->string('key')->unique(); // "monthly", "quarterly", "annual"
            
            // Preço e Moeda
            $table->bigInteger('price'); // Em centavos: 1990 = R$ 19,90
            $table->string('currency')->default('BRL'); // BRL, USD, EUR
            
            // Duração
            $table->enum('duration', ['week', 'month', 'year']);
            $table->integer('duration_value'); // 1, 3, 6, 12
            
            // Metadados
            $table->string('level')->nullable(); // "Level 1", "Level 2"
            $table->string('language')->default('br'); // br, en, es
            $table->text('description')->nullable();
            
            // Status
            $table->enum('status', ['active', 'inactive'])->default('active');
            
            // IDs Externos (Stripe, TriboPay, etc)
            $table->string('pages_product_external_id')->nullable();
            $table->string('custom_gateway')->nullable();
            $table->string('external_product_id')->nullable();
            $table->string('pages_upsell_url')->nullable();
            
            // Timestamps
            $table->timestamps();
            
            // Índices para busca rápida
            $table->index('key');
            $table->index('identifier');
            $table->index('currency');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
```

---

### 3️⃣ CONTROLLER DO BACKEND - Novo Endpoint

Crie um `PlanController` ou use o existente:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /**
     * GET /api/plans
     * Retorna todos os planos disponíveis
     * (Opcional: pode ser usado para sincronizar com frontend)
     */
    public function index(): JsonResponse
    {
        try {
            $plans = Plan::where('status', 'active')->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $plans,
                'count' => count($plans),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/plans/{key}
     * Retorna um plano específico pelo key
     * Exemplo: GET /api/plans/monthly
     */
    public function show(string $key): JsonResponse
    {
        try {
            $plan = Plan::findByKey($key);
            
            if (!$plan) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Plano não encontrado',
                ], 404);
            }
            
            return response()->json([
                'status' => 'success',
                'data' => $plan,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/plans/validate/{key}/{amount}/{currency}
     * Valida se um amount é válido para um plano
     * Exemplo: GET /api/plans/validate/monthly/19.90/BRL
     */
    public function validateAmount(string $key, float $amount, string $currency = 'BRL'): JsonResponse
    {
        try {
            $plan = Plan::findByKey($key);
            
            if (!$plan) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Plano não encontrado',
                ], 404);
            }
            
            if ($plan->currency !== $currency) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Moeda inválida. Esperado: {$plan->currency}",
                ], 422);
            }
            
            // Converter preço de centavos para decimal
            $expectedPrice = $plan->price / 100;
            
            // Permitir variação de até 5% (conversão de moeda, etc)
            $tolerance = $expectedPrice * 0.05;
            $minPrice = $expectedPrice - $tolerance;
            $maxPrice = $expectedPrice + $tolerance;
            
            $isValid = ($amount >= $minPrice && $amount <= $maxPrice);
            
            return response()->json([
                'status' => 'success',
                'valid' => $isValid,
                'plan' => [
                    'key' => $plan->key,
                    'name' => $plan->name,
                    'expected_price' => $expectedPrice,
                    'received_price' => $amount,
                    'tolerance_range' => [
                        'min' => round($minPrice, 2),
                        'max' => round($maxPrice, 2),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
```

---

### 4️⃣ ROTAS

Adicione ao `routes/api.php`:

```php
<?php

use App\Http\Controllers\PlanController;
use App\Http\Controllers\PixController;
use Illuminate\Support\Facades\Route;

// ✅ Planos (NOVO)
Route::prefix('api/plans')->group(function () {
    Route::get('/', [PlanController::class, 'index'])->name('plans.index');
    Route::get('{key}', [PlanController::class, 'show'])->name('plans.show');
    Route::get('validate/{key}/{amount}/{currency}', [PlanController::class, 'validateAmount'])->name('plans.validate');
});

// ✅ PIX
Route::prefix('api/pix')->group(function () {
    Route::post('/create', [PixController::class, 'create'])->name('pix.create');
    Route::get('/status/{paymentId}', [PixController::class, 'getPaymentStatus'])->name('pix.status');
});
```

---

### 5️⃣ ATUALIZAR `PixController`

Modifique o método `isValidAmountForPlan()`:

**Antes (usando API externa):**

```php
private function isValidAmountForPlan(float $amount, string $planKey, string $currency): bool
{
    // ❌ Usa API externa - lento, com risco de falha
    $apiUrl = env('PLANS_API_URL') ?? 'https://snaphubb.com/api/get-plans';
    // ... lógica aqui
}
```

**Depois (usando banco de dados):**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class PixController extends Controller
{
    /**
     * POST /api/pix/create
     * Criar pagamento PIX com validação de integridade
     */
    public function create(Request $request)
    {
        try {
            // 1️⃣ Validar schema
            $validated = $request->validate([
                'amount' => 'required|numeric|min:0.01',
                'currency_code' => 'required|string|in:BRL,USD,EUR',
                'plan_key' => 'required|string',
                'customer.name' => 'required|string|max:255',
                'customer.email' => 'required|email',
                'customer.phone' => 'required|string',
                'customer.cpf' => 'required|string',
                'cart' => 'array',
            ]);

            // 2️⃣ Validar integridade do valor contra banco de dados
            if (!$this->isValidAmountForPlan(
                $validated['amount'],
                $validated['plan_key'],
                $validated['currency_code']
            )) {
                Log::warning('PIX: Value tampering attempt detected', [
                    'amount' => $validated['amount'],
                    'plan_key' => $validated['plan_key'],
                    'ip' => $request->ip(),
                ]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Valor não corresponde ao plano selecionado',
                ], 422);
            }

            // 3️⃣ Validar CPF
            if (!$this->isValidCpf($validated['customer']['cpf'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'CPF inválido',
                ], 422);
            }

            // 4️⃣ Criar descrição do pagamento
            $description = $this->buildPaymentDescription($validated);

            // 5️⃣ Chamar Mercado Pago
            $pixResponse = $this->createPixPaymentMercadoPago(
                $validated['amount'],
                $validated['currency_code'],
                $description,
                $validated['customer']
            );

            if ($pixResponse['status'] !== 'success') {
                return response()->json([
                    'status' => 'error',
                    'message' => $pixResponse['message'] ?? 'Erro ao criar PIX',
                ], 500);
            }

            // 6️⃣ Log de auditoria
            Log::info('PIX Payment Created', [
                'plan_key' => $validated['plan_key'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency_code'],
                'transaction_id' => $pixResponse['transaction_id'] ?? null,
                'customer_email' => $validated['customer']['email'],
            ]);

            // 7️⃣ Retornar QR Code ao frontend
            return response()->json([
                'status' => 'success',
                'data' => [
                    'qr_code' => $pixResponse['qr_code'] ?? null,
                    'qr_code_text' => $pixResponse['qr_code_text'] ?? null,
                    'transaction_id' => $pixResponse['transaction_id'] ?? null,
                    'expires_at' => $pixResponse['expires_at'] ?? null,
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PIX Controller Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar pagamento PIX',
            ], 500);
        }
    }

    /**
     * ✅ NOVO: Validar valor contra banco de dados (não API externa)
     */
    private function isValidAmountForPlan(
        float $amount,
        string $planKey,
        string $currency
    ): bool {
        try {
            // 1. Buscar plano no banco de dados
            $plan = Plan::findByKey($planKey);
            
            if (!$plan || $plan->status !== 'active') {
                Log::warning('PIX: Invalid plan key', ['plan_key' => $planKey]);
                return false;
            }

            // 2. Verificar moeda
            if ($plan->currency !== $currency) {
                Log::warning('PIX: Currency mismatch', [
                    'expected' => $plan->currency,
                    'received' => $currency,
                ]);
                return false;
            }

            // 3. Converter preço de centavos para decimal
            $expectedPrice = $plan->price / 100;

            // 4. Permitir variação de até 5% (conversão moeda, bumps, etc)
            $tolerance = $expectedPrice * 0.05;
            $minPrice = $expectedPrice - $tolerance;
            $maxPrice = $expectedPrice + $tolerance;

            // 5. Validar valor
            $isValid = ($amount >= $minPrice && $amount <= $maxPrice);

            if (!$isValid) {
                Log::warning('PIX: Amount out of range', [
                    'received' => $amount,
                    'expected' => $expectedPrice,
                    'tolerance' => ['min' => $minPrice, 'max' => $maxPrice],
                ]);
            }

            return $isValid;

        } catch (\Exception $e) {
            Log::error('PIX: Error validating amount', [
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Validar CPF com dígitos verificadores
     */
    private function isValidCpf(string $cpf): bool
    {
        // Remove caracteres especiais
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // CPF deve ter 11 dígitos
        if (strlen($cpf) !== 11) {
            return false;
        }

        // Rejeitar se todos os dígitos são iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        // Verificar primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($cpf[$i]) * (10 - $i);
        }
        $digit1 = 11 - ($sum % 11);
        $digit1 = $digit1 > 9 ? 0 : $digit1;

        if (intval($cpf[9]) !== $digit1) {
            return false;
        }

        // Verificar segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += intval($cpf[$i]) * (11 - $i);
        }
        $digit2 = 11 - ($sum % 11);
        $digit2 = $digit2 > 9 ? 0 : $digit2;

        if (intval($cpf[10]) !== $digit2) {
            return false;
        }

        return true;
    }

    /**
     * Construir descrição do pagamento
     */
    private function buildPaymentDescription(array $data): string
    {
        $planKey = $data['plan_key'];
        $customerName = $data['customer']['name'];
        $amount = $data['amount'];
        $currency = $data['currency_code'];

        return "PIX - {$customerName} - Plano: {$planKey} - R$ {$amount} {$currency}";
    }

    /**
     * Criar PIX no Mercado Pago
     */
    private function createPixPaymentMercadoPago(
        float $amount,
        string $currency,
        string $description,
        array $customer
    ): array {
        try {
            $mpAccessToken = env('MERCADO_PAGO_ACCESS_TOKEN');
            
            $payload = [
                'transaction_amount' => $amount,
                'description' => $description,
                'payment_method_id' => 'pix',
                'payer' => [
                    'email' => $customer['email'],
                    'first_name' => explode(' ', $customer['name'])[0] ?? '',
                    'identification' => [
                        'type' => 'CPF',
                        'number' => preg_replace('/[^0-9]/', '', $customer['cpf']),
                    ],
                ],
            ];

            $response = Http::withToken($mpAccessToken)
                ->post('https://api.mercadopago.com/v1/payments', $payload);

            if (!$response->successful()) {
                return [
                    'status' => 'error',
                    'message' => 'Erro ao criar PIX no Mercado Pago',
                ];
            }

            $data = $response->json();

            return [
                'status' => 'success',
                'qr_code' => $data['point_of_interaction']['transaction_data']['qr_code'] ?? null,
                'qr_code_text' => $data['point_of_interaction']['transaction_data']['qr_code_text'] ?? null,
                'transaction_id' => $data['id'] ?? null,
                'expires_at' => $data['point_of_interaction']['transaction_data']['in_store_order_id'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Mercado Pago Error', ['message' => $e->getMessage()]);
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /api/pix/status/{paymentId}
     */
    public function getPaymentStatus(string $paymentId)
    {
        // Implementar depois...
    }
}
```

---

## 📊 Fluxo Sincronizado Resultante

```
┌────────────────────────────────────────────────────┐
│           Frontend (PagePay.php)                   │
├────────────────────────────────────────────────────┤
│ getPlans() → /get-plans (API externa)             │
│ calculateTotals() → $totals['final_price']        │
│ preparePIXData() → {amount, plan_key, cpf, ...}   │
│ sendPixToBackend() → POST /api/pix/create         │
└────────────────────────────────────────────────────┘
                    ↓
┌────────────────────────────────────────────────────┐
│      Backend - PixController (VALIDAÇÃO)          │
├────────────────────────────────────────────────────┤
│ POST /api/pix/create                              │
│ └─ Schema validation ✅                            │
│ └─ isValidAmountForPlan() ✅                       │
│    └─ Plan::findByKey() → Banco de dados LOCAL    │
│    └─ Valida: price, currency, tolerance         │
│ └─ isValidCpf() ✅                                │
│ └─ Cria PIX no Mercado Pago ✅                    │
│ └─ Log de auditoria ✅                            │
│ └─ Retorna QR Code                                │
└────────────────────────────────────────────────────┘
```

---

## ⚙️ Passo a Passo de Implementação

### Dia 1 - Estrutura do Banco

- [ ] Criar migration para tabela `plans` (se não existir)
- [ ] Executar: `php artisan migrate`
- [ ] Inserir dados de teste na tabela `plans`

### Dia 2 - Modelo e Controller

- [ ] Criar `app/Models/Plan.php`
- [ ] Criar `app/Http/Controllers/PlanController.php`
- [ ] Adicionar rotas em `routes/api.php`

### Dia 3 - Atualizar PixController

- [ ] Atualizar `isValidAmountForPlan()` para usar `Plan::findByKey()`
- [ ] Testar com valores válidos e inválidos
- [ ] Verificar logs de auditoria

### Dia 4 - QA e Testes

- [ ] Executar os 27 testes do checklist
- [ ] Testar value tampering (alterar amount no DevTools)
- [ ] Testar com diferentes moedas e planos

---

## 🧪 Exemplo de Teste

```bash
# Teste 1: Plano existe e é válido
curl -X GET "http://localhost:8000/api/plans/monthly"

# Teste 2: Validar valor (deve retornar true)
curl -X GET "http://localhost:8000/api/plans/validate/monthly/19.90/BRL"

# Teste 3: PIX com valor correto (deve criar PIX)
curl -X POST "http://localhost:8000/api/pix/create" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 19.90,
    "currency_code": "BRL",
    "plan_key": "monthly",
    "customer": {
      "name": "João Silva",
      "email": "joao@example.com",
      "phone": "+5511999999999",
      "cpf": "123.456.789-09"
    },
    "cart": []
  }'

# Teste 4: PIX com valor errado (deve rejeitar com 422)
curl -X POST "http://localhost:8000/api/pix/create" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 50.00,
    "currency_code": "BRL",
    "plan_key": "monthly",
    ...
  }'
# Resposta esperada: {"status": "error", "message": "Valor não corresponde ao plano selecionado"}
```

---

## 📝 Checklist Final

- [ ] Tabela `plans` criada e populada
- [ ] Modelo `Plan.php` implementado
- [ ] `PlanController.php` implementado com 3 endpoints
- [ ] Rotas adicionadas em `routes/api.php`
- [ ] `PixController.isValidAmountForPlan()` atualizado
- [ ] Logs funcionando corretamente
- [ ] Testes passando (27 total)
- [ ] Value tampering prevention funcionando
- [ ] Deploy em staging (antes de produção)

---

## ❓ Dúvidas Frequentes

**P: E se já tenho uma tabela Plan em `Modules/Subscriptions`?**  
R: Use a mesma! Só adapte o namespace no `PixController` para:
```php
use Modules\Subscriptions\Models\Plan;
```

**P: Como sincronizo os preços do dashboard com meu banco?**  
R: Crie um comando artisan que sincroniza:
```bash
php artisan plans:sync-from-api
```

**P: Preciso manter a API externa?**  
R: Não! Agora o backend valida direto do banco, mais rápido e seguro.

**P: E os bumps (upsells)?**  
R: A tolerância de 5% contempla bumps. Implemente depois se necessário.

---

**Status:** ✅ Pronto para implementação no seu backend!

