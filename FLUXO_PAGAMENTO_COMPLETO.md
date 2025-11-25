# ✅ FLUXO COMPLETO DE PAGAMENTO PIX - SNAPHUBB

**Data:** 25 de Novembro de 2025  
**Status:** ✅ **TOTALMENTE IMPLEMENTADO E FUNCIONANDO**  
**Branch:** `pages`

---

## 📋 RESUMO EXECUTIVO

O fluxo de pagamento PIX está **100% funcional** com:
- ✅ Geração de QR code pela Pushing Pay
- ✅ Modal interativo com timer de 5 minutos
- ✅ Botão de fallback "Ou pagar com Cartão" (30 segundos)
- ✅ Blur effect no background quando modal está aberto
- ✅ Polling automático (a cada 5 segundos)
- ✅ Webhook para notificações em tempo real
- ✅ Redirecionamento automático para upsell após aprovação
- ✅ Integração com Facebook Conversions API

---

## 🔄 FLUXO COMPLETO (Passo a Passo)

### **1️⃣ ETAPA: Cliente Seleciona PIX**
**Arquivo:** `resources/views/livewire/page-pay.blade.php`  
**Componente:** `app/Livewire/PagePay.php`

```
┌─────────────────────────────────────────────┐
│ Usuário clica em "Gerar PIX"                │
│ → Livewire dispara generatePixCode()        │
└─────────────────────────────────────────────┘
```

**Código:**
```php
// app/Livewire/PagePay.php (linha ~750)
public function generatePixCode()
{
    // 1. Valida dados do formulário
    // 2. Chama API da Pushing Pay
    // 3. Recebe QR code base64
    // 4. Armazena transaction_id
    // 5. Exibe modal PIX
}
```

---

### **2️⃣ ETAPA: API Pushing Pay Recebe webhook_url**
**Arquivo:** `app/Livewire/PagePay.php` (linha 796)

```php
$payload = [
    'value' => $this->finalPrice,
    'webhook_url' => url('/api/pix/webhook'),  // ✅ ENVIADO
    'split_rules' => [],
];

$response = Http::post('https://api.pushinpay.com.br/api/pix/cashIn', $payload);
```

**O que acontece:**
- Pushing Pay **REGISTRA** a URL de webhook: `https://seu-dominio.com/api/pix/webhook`
- Qualquer mudança de status enviará notificação para esta URL

---

### **3️⃣ ETAPA: Modal PIX Exibido**
**Arquivo:** `resources/views/livewire/page-pay.blade.php` (linha 1240)

```html
@if($showPixModal)
    <!-- Backdrop com blur effect -->
    <div id="pix-modal-backdrop" 
         class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm"
         style="backdrop-filter: blur(4px);">
    </div>

    <!-- Modal com QR code -->
    <div id="pix-modal" class="fixed inset-0 z-50 flex items-center">
        <!-- QR code image -->
        <img src="data:image/png;base64,{{ $pixQrImage }}" 
             alt="QR Code" 
             class="w-24 h-24 sm:w-28 sm:h-28 md:w-40 md:h-40 lg:w-44 lg:h-44" />
        
        <!-- PIX copy code -->
        <div>{{ $pixQrCodeText }}</div>
        
        <!-- Timer countdown -->
        <p id="pix-timer">5:00</p>
        
        <!-- Fallback button (aparece após 30s) -->
        <button id="pay-card-btn" 
                class="hidden mt-4 px-4 py-2">
            Ou pagar com Cartão
        </button>
    </div>
@endif
```

---

### **4️⃣ ETAPA: JavaScript Timer Iniciado**
**Arquivo:** `resources/views/livewire/page-pay.blade.php` (linha 1389)

```javascript
function startPixQRTimer() {
    pixQRTimer = 300; // 5 minutos em segundos
    
    pixQRTimerInterval = setInterval(() => {
        pixQRTimer--;
        
        // Atualiza display do timer
        document.getElementById('pix-timer').textContent = formatTime(pixQRTimer);
        
        // Mostra botão de cartão após 30 segundos
        if (pixQRTimer === 270 && !payCardButtonShown) {
            const cardBtn = document.getElementById('pay-card-btn');
            cardBtn.classList.remove('hidden');
            cardBtn.style.display = 'flex';
            payCardButtonShown = true;
            console.log('✅ Botão de cartão exibido após 30 segundos');
        }
        
        // Fecha modal quando timer chega a 0
        if (pixQRTimer <= 0) {
            closePixModal();
        }
    }, 1000);
}
```

---

### **5️⃣ ETAPA: Polling Automático (Fallback)**
**Arquivo:** `app/Livewire/PagePay.php` (linha 841)

```php
public function checkPixPaymentStatus()
{
    if (!$this->pixTransactionId) {
        return;
    }
    
    // Consulta status na API Pushing Pay
    $response = Http::get(
        "https://api.pushinpay.com.br/api/pix/cashIn/{$this->pixTransactionId}",
        ['headers' => $this->getHeaders()]
    );
    
    $payment = $response->json();
    
    // Se status é aprovado, processa pagamento
    if ($payment['status'] === 'approved') {
        $this->handlePixApproved();  // ✅ REDIRECIONAMENTO AQUI
    }
}
```

**Ativado via:** `wire:poll.5s="checkPixPaymentStatus"` no modal PIX

---

### **6️⃣ ETAPA: Webhook Recebido (REAL-TIME)**
**Arquivo:** `routes/api.php` (linha 25)

```php
Route::post('/pix/webhook', 
    [PushingPayWebhookController::class, 'handle']
)->name('webhook.pushinpay');
```

**Quando:** Usuário paga PIX no banco
**O que Pushing Pay envia:**
```json
{
    "event": "payment.approved",
    "data": {
        "id": "PIX_12345",
        "transaction_id": "TXN_67890",
        "amount": 24.90,
        "status": "approved",
        "timestamp": "2025-11-25T15:30:00Z"
    }
}
```

---

### **7️⃣ ETAPA: Webhook Processado**
**Arquivo:** `app/Http/Controllers/PushingPayWebhookController.php` (linha 85)

```php
private function handlePaymentApproved($paymentId, $data)
{
    // 1. Encontra Order pelo PIX payment ID
    $order = Order::where('pix_id', $paymentId)->first();
    
    if (!$order) {
        return response()->json(['success' => true], 200);
    }
    
    // 2. Atualiza status para "paid"
    $order->update([
        'status' => 'paid',
        'paid_at' => now(),
        'external_payment_status' => 'approved',
    ]);
    
    // 3. Envia evento de Purchase para Facebook Conversions API
    $this->fbService->sendPurchaseEvent($pixelId, [
        'email' => $order->user->email,
        'value' => $order->amount,
        'currency' => 'BRL',
    ]);
    
    // 4. Log tudo
    Log::info('Order marked as paid', ['orderId' => $order->id]);
    
    return response()->json(['success' => true], 200);
}
```

---

### **8️⃣ ETAPA: Frontend Detecta Aprovação**
**Arquivo:** `app/Livewire/PagePay.php` (linha 896)

Existem **3 formas** de detecção:

**Forma 1: Via Polling (5 segundos)**
```php
// checkPixPaymentStatus() detecta status === 'approved'
→ Chama handlePixApproved()
```

**Forma 2: Via Webhook (Real-time)**
Quando webhook marca Order como "paid", polling na próxima iteração vê mudança

**Forma 3: Via WebSocket (Futuro)**
Push notification em tempo real

---

### **9️⃣ ETAPA: Redirecionamento para Upsell**
**Arquivo:** `app/Livewire/PagePay.php` (linha 963)

```php
private function handlePixApproved()
{
    Log::info('PIX aprovado - INICIANDO REDIRECIONAMENTO');
    
    // Para polling
    $this->dispatch('stop-pix-polling');
    
    // Fecha modais
    $this->showPixModal = false;
    
    // Salva dados na sessão
    session()->put('show_upsell_after_purchase', true);
    session()->put('last_order_customer', [
        'name' => $this->pixName,
        'email' => $this->pixEmail,
        'phone' => $this->pixPhone,
    ]);
    
    // Dispatch evento de sucesso (Facebook Pixel)
    $this->dispatch('checkout-success', purchaseData: [
        'transaction_id' => $this->pixTransactionId,
        'value' => $this->pixAmount,
    ]);
    
    // 🔴 REDIRECIONAMENTO CRÍTICO
    $this->dispatch('redirect-success', url: url('/upsell/painel-das-garotas'));
}
```

---

### **🔟 ETAPA: Listener no Frontend Redireciona**
**Arquivo:** `resources/views/livewire/page-pay.blade.php` (linha ~1570)

```javascript
Livewire.on('redirect-success', (event) => {
    console.log('🔄 Redirecionando para:', event.url);
    setTimeout(() => {
        window.location.href = event.url;
    }, 300);
});
```

---

### **1️⃣1️⃣ ETAPA: Upsell Page Carregada**
**Rota:** `routes/web.php` (linha 14)

```php
Route::get('/upsell/painel-das-garotas', function(){
    return view('upsell.painel');
})->name('upsell.painel');
```

**View:** `resources/views/upsell/painel.blade.php`

```blade
<livewire:upsell-offer />
```

Componente Livewire que exibe:
- Oferta exclusiva ao cliente
- Opção de upgrade (semi-anual)
- Dados do cliente pré-preenchidos

---

## 📊 FLUXO VISUAL COMPLETO

```
┌──────────────────────────────────────────────────────────────┐
│ 1. CLIENTE SELECIONA PIX                                     │
└────────────────────────┬─────────────────────────────────────┘
                         ↓
┌──────────────────────────────────────────────────────────────┐
│ 2. API PUSHING PAY RECEBE WEBHOOK_URL                        │
│    URL: https://seu-dominio.com/api/pix/webhook              │
└────────────────────────┬─────────────────────────────────────┘
                         ↓
┌──────────────────────────────────────────────────────────────┐
│ 3. MODAL PIX EXIBIDO COM BLUR EFFECT                         │
│    • QR code                                                  │
│    • Código PIX (copy-paste)                                 │
│    • Timer (5:00 → 4:59 → ...)                              │
│    • Após 30s: Botão "Ou pagar com Cartão"                  │
└────────────────────────┬─────────────────────────────────────┘
                         ↓
         ╔═══════════════╬═══════════════╗
         ↓               ↓               ↓
    [WEBHOOK]      [POLLING]      [TIMEOUT]
   (Real-time)     (5 segundos)   (5 minutos)
         ↓               ↓               ↓
         │               │          Modal fecha
         └───────┬───────┘
                 ↓
┌──────────────────────────────────────────────────────────────┐
│ 4. PAGAMENTO DETECTADO COMO APROVADO                         │
│    • Via webhook (instantâneo)                               │
│    • Via polling (máx 5 segundos)                            │
└────────────────────────┬─────────────────────────────────────┘
                         ↓
┌──────────────────────────────────────────────────────────────┐
│ 5. ORDER MARCADA COMO "PAID"                                 │
│    • handlePixApproved() executado                           │
│    • Dados salvos em sessão                                  │
│    • Facebook Pixel disparado                                │
└────────────────────────┬─────────────────────────────────────┘
                         ↓
┌──────────────────────────────────────────────────────────────┐
│ 6. REDIRECIONAMENTO PARA UPSELL                              │
│    URL: /upsell/painel-das-garotas                          │
│    • Oferta exclusiva ao cliente                             │
│    • Dados pré-preenchidos                                   │
│    • Session: show_upsell_after_purchase = true              │
└────────────────────────┬─────────────────────────────────────┘
                         ↓
┌──────────────────────────────────────────────────────────────┐
│ 7. UPSELL COMPONENT RENDERIZADO                              │
│    <livewire:upsell-offer />                                 │
└──────────────────────────────────────────────────────────────┘
```

---

## ✅ CHECKLIST DE IMPLEMENTAÇÃO

### ✅ Backend (Servidor)
- [x] Rota API PIX criada: `/api/pix/create` e `/api/pix/status/{id}`
- [x] Controller `PixController` implementado
- [x] Webhook rota criada: `/api/pix/webhook`
- [x] Webhook controller `PushingPayWebhookController` implementado
- [x] Suporte para eventos: `payment.approved`, `payment.declined`, `payment.canceled`
- [x] Model `Order` com campos para PIX
- [x] Facebook Conversions API integrada
- [x] Logging completo de todas operações

### ✅ Frontend (Cliente)
- [x] Modal PIX com QR code responsivo
- [x] Timer de 5 minutos com countdown
- [x] Botão "Ou pagar com Cartão" (após 30s)
- [x] Blur effect no background
- [x] Copy button para código PIX
- [x] Polling automático (5 segundos)
- [x] Listener para evento `redirect-success`
- [x] Remoção de modal quando timer expira

### ✅ Configuração (.env)
```env
# Pushing Pay PIX
PIX_PROVIDER=pushinpay
PP_ACCESS_TOKEN_PRODUCTION=seu_token_aqui
ENVIRONMENT=production

# Webhook (automático)
WEBHOOK_URL=/api/pix/webhook

# Analytics
FB_PIXEL_ID=seu_pixel_aqui
FB_CAPI_ACCESS_TOKEN=seu_token_aqui

# Upsell
UPSELL_REDIRECT_URL=/upsell/painel-das-garotas
```

### ✅ Rotas
- [x] POST `/api/pix/create` - Criar PIX
- [x] GET `/api/pix/status/{paymentId}` - Consultar status
- [x] POST `/api/pix/webhook` - Receber notificações Pushing Pay
- [x] GET `/upsell/painel-das-garotas` - Página de upsell

---

## 🔍 COMO TESTAR EM PRODUÇÃO

### Teste Local (Sandbox)
```bash
# 1. Ter servidor rodando
php artisan serve

# 2. Gerar PIX
- Acesse http://127.0.0.1:8000
- Selecione PIX
- Clique em "Gerar PIX"

# 3. Ver modal com QR code
- Veja QR code
- Aguarde 30 segundos para ver botão de cartão
- Timer conta de 5:00 para 0:00

# 4. Testar webhook (curl)
curl -X POST http://127.0.0.1:8000/api/pix/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "event": "payment.approved",
    "data": {
      "id": "PIX_TEST_123",
      "amount": 24.90,
      "status": "approved"
    }
  }'

# 5. Verificar logs
tail -f storage/logs/laravel.log | grep -i "pix\|webhook"
```

### Teste em Produção
1. **Configurar Webhook em Pushing Pay Dashboard**
   - Acesse https://app.pushinpay.com.br
   - Vá em Configurações → Webhooks
   - Configure URL: `https://seu-dominio.com/api/pix/webhook`
   - Selecione evento: `payment.approved`
   - Teste com botão "Send Test"

2. **Monitorar Logs**
   ```bash
   tail -f storage/logs/laravel.log | grep "Pushing Pay"
   ```

3. **Fazer Pagamento Real**
   - Acesse seu domínio
   - Selecione PIX
   - Gere QR code
   - Pague via app bancário
   - Veja redirecionamento automático

---

## 🚨 POSSÍVEIS PROBLEMAS E SOLUÇÕES

### ❌ Problema: Modal não abre
**Solução:**
- Verificar se `$showPixModal` está `true`
- Conferir console do navegador para erros
- Logs: `grep "generatePixCode" storage/logs/laravel.log`

### ❌ Problema: QR code não aparece
**Solução:**
- Verificar token de Pushing Pay em `.env`
- Confirmar conexão à API: `PP_ACCESS_TOKEN_PRODUCTION`
- Logs: `grep "pixQrImage" storage/logs/laravel.log`

### ❌ Problema: Timer não conta
**Solução:**
- Verificar JavaScript no browser console
- Confirmar que `startPixQRTimer()` foi chamado
- Verificar intervalo não foi limpo: `clearInterval(pixQRTimerInterval)`

### ❌ Problema: Botão de cartão não aparece
**Solução:**
- Aguardar 30 segundos exatos (não menos)
- Verificar console para log: `✅ Botão de cartão exibido após 30 segundos`
- CSS: confirmar classe `hidden` está funcionando

### ❌ Problema: Webhook não recebe
**Solução:**
- Verificar URL está acessível (HTTPS com certificado válido)
- Testar webhook manualmente com curl
- Confirmar logs: `grep "webhook received" storage/logs/laravel.log`
- Verificar firewall não está bloqueando

### ❌ Problema: Não redireciona para upsell
**Solução:**
- Verificar se `handlePixApproved()` foi chamado
- Confirmar listener JavaScript para `redirect-success`
- Logs: `grep "REDIRECT" storage/logs/laravel.log`
- Browser console: verificar se evento foi disparado

### ❌ Problema: Dados do cliente não pré-preenchem
**Solução:**
- Verificar session: `session()->put('last_order_customer', [...])`
- Confirmar dados foram salvos antes do redirect
- Verificar página upsell está lendo da session

---

## 📈 PRÓXIMOS PASSOS (MELHORIAS FUTURAS)

1. **WebSocket em tempo real**
   - Substituir polling por WebSocket para mais instantaneidade
   - Usar Laravel Broadcasting

2. **Notificação por email**
   - Enviar confirmação de pagamento
   - Enviar código de acesso

3. **Dashboard administrativo**
   - Ver todas as transações PIX
   - Filtrar por status, data, valor
   - Exportar relatórios

4. **Suporte a múltiplos gateways**
   - Stripe
   - Mercado Pago
   - 2Checkout

5. **Webhook retry automático**
   - Se falhar, retry em 1min, 5min, 10min
   - Log de todas tentativas

---

## 📞 CONTATOS ÚTEIS

### Pushing Pay
- **WhatsApp**: +55 11 5557-8038
- **Email**: contato@pushinpay.com.br
- **Site**: https://pushinpay.com.br

### Suporte
- **Dashboard**: https://app.pushinpay.com.br
- **Documentação API**: https://api.pushinpay.com.br/docs

---

## 📝 CHANGELOG

| Data | Versão | O Quê |
|------|--------|-------|
| 25/11/2025 | 1.0 | ✅ Fluxo completo implementado e testado |
| 25/11/2025 | 0.9 | Blur effect adicionado |
| 25/11/2025 | 0.8 | Botão "Ou pagar com Cartão" implementado |
| 25/11/2025 | 0.7 | Webhook Pushing Pay integrado |
| 25/11/2025 | 0.6 | Timer com countdown adicionado |
| 25/11/2025 | 0.5 | Modal PIX responsivo implementado |

---

**Gerado:** 25 de Novembro de 2025  
**Status:** ✅ **PRONTO PARA PRODUÇÃO**
