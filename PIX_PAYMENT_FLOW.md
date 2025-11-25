# 📊 Fluxo Completo de Pagamento PIX - SnapHubb

## 🔄 Fluxo de Funcionamento

### 1. **Geração do QR Code PIX**
```
Usuário clica em "PIX" → Livewire dispara generatePixCode() 
→ PushingPayPixService::createPayment() 
→ API Pushing Pay cria transação 
→ Retorna QR code + Código PIX + Transaction ID
→ Armazena em: $pixTransactionId, $pixQrImage, $pixQrCodeText
```

**Arquivo**: `app/Livewire/PagePay.php` (linhas ~550-620)

---

### 2. **Modal PIX Exibido com Timer**
```
Modal exibido com:
✓ QR Code (redimensionado: w-28 mobile, w-40 tablet, w-44 desktop)
✓ Código PIX para copiar
✓ Preço (R$ 49,90 → R$ 24,90 com desconto)
✓ Informações de segurança
✓ Timer regressivo em VERDE: 5:00 → 0:00
✓ Botão "Pagar com Cartão" (aparece após 30 segundos)
```

**Arquivo**: `resources/views/livewire/page-pay.blade.php` (linhas ~1223-1310)

**Estilos**: Timer verde em `#pix-timer` com classe `text-green-400 font-bold font-mono`

---

### 3. **Polling de Status - Detecção de Pagamento**

**Como funciona:**
```
wire:poll.5s="checkPixPaymentStatus"
↓
A cada 5 SEGUNDOS, executa checkPixPaymentStatus()
↓
Consulta API Pushing Pay com transaction ID
↓
Analisa status retornado
```

**Arquivo**: `app/Livewire/PagePay.php` linha 840-890

**Código**:
```php
public function checkPixPaymentStatus()
{
    if (empty($this->pixTransactionId)) {
        Log::warning('Sem pixTransactionId');
        return;
    }

    $response = $this->pixService->getPaymentStatus($this->pixTransactionId);
    
    if ($response['status'] === 'error') {
        Log::warning('Erro ao consultar', $response);
        return;
    }

    $paymentStatus = $response['data']['payment_status'] ?? 'pending';
    
    // Status possíveis: pending, approved, rejected, cancelled, expired
    if ($paymentStatus === 'approved') {
        $this->handlePixApproved();  // ✅ PAGAMENTO APROVADO!
    } elseif ($paymentStatus === 'rejected' || $paymentStatus === 'cancelled') {
        $this->handlePixRejected();  // ❌ PAGAMENTO REJEITADO
    } elseif ($paymentStatus === 'expired') {
        $this->handlePixExpired();   // ⏳ PAGAMENTO EXPIROU
    }
    // else: continua no status 'pending'
}
```

---

### 4. **Quando PIX é Aprovado - handlePixApproved()**

**O que acontece:**

```
✅ PIX APROVADO DETECTADO
    ↓
1. Para o polling (wire:poll)
2. Fecha modal PIX
3. Salva dados na sessão:
   - transaction_id
   - customer info (name, email, phone, cpf)
   - show_upsell_after_purchase = true
4. Dispara eventos para tracking:
   - checkout-success (Livewire event)
   - Facebook Pixel event
5. REDIRECIONA para: /upsell/painel-das-garotas
```

**Arquivo**: `app/Livewire/PagePay.php` linhas 895-970

**Código do redirecionamento**:
```php
private function handlePixApproved()
{
    // Para polling
    $this->dispatch('stop-pix-polling');
    
    // Fecha modals
    $this->showPixModal = false;
    $this->showSuccessModal = false;
    $this->showProcessingModal = false;
    
    // Salva dados na sessão
    session()->put('last_order_transaction', $this->pixTransactionId);
    session()->put('last_order_amount', $this->pixAmount);
    session()->put('show_upsell_after_purchase', true);
    session()->put('last_order_customer', [
        'name' => $this->pixName ?? $this->name,
        'email' => $this->pixEmail ?? $this->email,
        'phone' => $this->pixPhone ?? $this->phone,
        'document' => $this->pixCpf ?? $this->cpf,
    ]);
    
    // Tracking (Facebook Pixel, etc)
    $this->dispatch('checkout-success', purchaseData: [...]);
    
    // ⚡ REDIRECIONAMENTO CRÍTICO
    $redirectUrl = url('/upsell/painel-das-garotas');
    $this->dispatch('redirect-success', url: $redirectUrl);
}
```

---

### 5. **Redirecionamento no Frontend**

**Arquivo**: `resources/views/livewire/page-pay.blade.php` (procurar por `redirect-success`)

O evento `redirect-success` é capturado pelo JavaScript Livewire e redireciona para a página de upsell.

---

## 🔑 Variáveis de Ambiente Necessárias

```env
# .env - PRODUCÃO

# Pushing Pay PIX
PIX_PROVIDER=pushinpay
ENVIRONMENT=production
PP_ACCESS_TOKEN_PROD=55321|JaTW9wbkkKohC1cgIEyOLj1LhbQDwGg2zDAs3Iov67688d1b

# URLs de webhook (opcional, para notificações)
MERCADOPAGO_NOTIFICATION_URL=https://seu-dominio.com/api/pix/webhook

# Analytics e Tracking
GA_MEASUREMENT_ID=G-G6FBHCNW8X
FB_PIXEL_ID=YOUR_FACEBOOK_PIXEL_ID
FB_CAPI_ACCESS_TOKEN=seu_token_aqui
```

---

## 📱 Fluxo de Interface

### Desktop (lg - 1024px+)
```
┌─────────────────────────────────────────────┐
│  ✓ PIX                              ×       │ (Header - verde)
├──────────────────┬──────────────────────────┤
│                  │ CÓDIGO PIX               │
│   [QR CODE]      │ [00020101021...]         │
│   176x176px      │ [📋 Copiar código]       │
│   5:00 ⏱        │                          │
│ [Pagar Cartão]   │ Streaming - R$ 49,90     │
│ (após 30s)       │ Desconto PIX - R$ 25,00  │
│                  │ Total: R$ 24,90          │
│                  │                          │
│                  │ ✓ Confirmação em segs    │
│                  │ ✓ Acesso imediato        │
│                  │ 🔒 100% seguro           │
└──────────────────┴──────────────────────────┘
```

### Mobile (< 640px)
```
┌──────────────────┐
│ ✓ PIX         × │ (Header - verde)
├──────────────────┤
│ CÓDIGO PIX       │
│ [00020101021...] │
│ [📋 Copiar]      │
│                  │
│ Streaming - ...  │
│ Desconto - ...   │
│ Total: R$ 24,90  │
│                  │
│ ✓ Confirmação    │
│ ✓ Acesso         │
│ 🔒 Seguro        │
│                  │
│  ESCANEAR QR     │
│   [QR 112x112]   │
│   5:00 ⏱        │
│ [Pagar Cartão]   │
│ (após 30s)       │
└──────────────────┘
```

---

## 🎯 Checkpoints de Detecção

### ✅ Pagamento Aprovado
- **Condição**: `paymentStatus === 'approved'`
- **Onde é consultado**: A cada 5 segundos via `wire:poll.5s`
- **Redirecionamento**: `/upsell/painel-das-garotas`
- **Tempo de espera**: Até 5 minutos (timer do QR)

### ❌ Pagamento Rejeitado/Cancelado
- **Condição**: `paymentStatus === 'rejected' || 'cancelled'`
- **Ação**: `handlePixRejected()` - mostra erro

### ⏳ Pagamento Expirado
- **Condição**: `paymentStatus === 'expired'` OU timer chega a 0:00
- **Ação**: `handlePixExpired()` - propõe gerar novo QR

---

## 🔗 Fluxo Alternativo: Cartão de Crédito

```
Usuário clica em "Pagar com Cartão" (após 30s)
↓
closePixModal() + dispatch('switchToCardPayment')
↓
Modal PIX fecha
Blur remove
Modal de cartão abre
Usuário segue fluxo de cartão
```

---

## 📊 Estados Possíveis

| Estado | Ação | Próximo Estado |
|--------|------|---|
| **pending** | Aguarda confirmação | approved, rejected, expired |
| **approved** | ✅ Redireciona | /upsell/painel-das-garotas |
| **rejected** | ❌ Mostra erro | Opção gerar novo |
| **cancelled** | ❌ Mostra erro | Opção gerar novo |
| **expired** | ⏳ Expirou | Opção gerar novo |

---

## 🛠️ Para Testar em Sandbox

1. Use **MERCADOPAGO_ENV=sandbox** no .env
2. QR codes gerados serão para teste
3. Use app de banco testador (Mercado Pago oferece)
4. Status aprovado vem automático em sandbox em alguns casos

---

## 📝 Logs Relevantes

Verifique em `storage/logs/`:

```
laravel.log:
- "PagePay: Status do PIX consultado" → status atual
- "PagePay: PIX aprovado - INICIANDO REDIRECIONAMENTO" → ✅ Aprovado
- "PagePay: DISPATCHING REDIRECT" → Redirecionando

payment_checkout.log:
- Detalhes de cada consulta ao status
- Valores, IDs de transação
```

---

## 🚀 Resumo Executivo

**O que acontece quando PIX é pago:**

1. ✅ Pushing Pay detecta pagamento
2. 📱 Livewire polling (5s) consulta status
3. ✓ Status retorna `approved`
4. 🎯 `handlePixApproved()` dispara
5. 📊 Dados salvos na sessão
6. 📡 Eventos de tracking acionados
7. ↗️ **REDIRECIONA para `/upsell/painel-das-garotas`**

**Tempo típico**: 5-15 segundos após o pagamento ser confirmado no banco

---

Generated: 2025-11-24 21:30
