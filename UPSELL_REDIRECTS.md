# 🔄 Redirecionamentos do Upsell - SnapHubb

## 📍 Fluxo de Redirecionamento Completo

```
COMPRA INICIAL (Monthly - R$ 24,90)
         ↓
    PIX APROVADO
         ↓
Redireciona para: /upsell/painel-das-garotas
         ↓
   Exibe Oferta: Painel das Garotas (R$ 37,00)
         ↓
    ┌────────────────────────────────────┐
    │                                    │
    ✅ USUÁRIO APROVA    ❌ USUÁRIO RECUSA
    │                                    │
    ↓                                    ↓
Gera PIX R$ 37,00          Redireciona para:
    ↓                      /upsell/thank-you-recused
PIX APROVADO ✅                  ↓
    ↓                    Página de Obrigado
Redireciona para:              (sem upsell)
/upsell/thank-you
    ↓
Página de Sucesso
(com upsell)
```

---

## 📄 Páginas de Destino

### **1️⃣ CASO PAGUE UPSELL (PIX APROVADO)**

**URL**: `/upsell/thank-you`

**Arquivo**: `resources/views/upsell/thank.blade.php`

**O que mostra**:
- ✅ Checkmark animado com bounce
- Título: "Parabéns! 🎉"
- "Sua compra foi confirmada com sucesso"
- **Resumo da Compra com DOIS itens**:
  1. Streaming Snaphubb — 1x mês (R$ 24,90)
  2. Painel das Garotas (R$ 37,00)
- Informações de acesso
- Botões de ação (assistir agora, explorar conteúdo, etc)

**Design**: Fundo preto com gradiente vermelho, animações

---

### **2️⃣ CASO REJEITE UPSELL (RECUSOU OFERTA)**

**URL**: `/upsell/thank-you-recused`

**Arquivo**: `resources/views/upsell/thank-you-recused.blade.php`

**O que mostra**:
- ✅ Checkmark animado com bounce
- Título: "Parabéns! 🎉"
- "Sua compra foi confirmada com sucesso"
- **Resumo da Compra com UM item APENAS**:
  1. Streaming Snaphubb — 1x mês (R$ 24,90)
  - ❌ NÃO mostra o Painel das Garotas
- Informações de acesso ao produto básico
- Botões de ação (assistir agora, explorar conteúdo)
- Nota: "Prepare-se para descobrir um mundo de entretenimento latino"

**Design**: Idêntico ao thank.blade.php, mas com menos itens

---

## 🔌 Rotas Definidas

**Arquivo**: `routes/web.php`

```php
// Página de oferta do upsell
Route::get('/upsell/painel-das-garotas', function(){
    return view('upsell.painel');
})->name('upsell.painel');

// Página quando PAGA o upsell (sucesso completo)
Route::get('/upsell/thank-you', function(){
    return view('upsell.thank');
})->name('upsell.thank');

// Página quando RECUSA o upsell
Route::get('/upsell/thank-you-recused', function(){
    return view('upsell.thank-you-recused');
})->name('upsell.thank_recused');
```

---

## 📋 Comparação de Conteúdo

| Elemento | Paga Upsell (`thank`) | Recusa Upsell (`thank-you-recused`) |
|----------|-----|-----|
| Título | Parabéns! 🎉 | Parabéns! 🎉 |
| Checkmark | ✅ Sim, animado | ✅ Sim, animado |
| Streaming Snaphubb | ✅ R$ 24,90 | ✅ R$ 24,90 |
| Painel das Garotas | ✅ R$ 37,00 | ❌ NÃO mostra |
| Total Pago | R$ 61,90 | R$ 24,90 |
| Descrição | "entretenimento sem limites" | "entretenimento latino" |
| Benefícios extras | ✅ Sim (painel) | ❌ Não |

---

## 🎯 Decisão de Redirecionamento

**Código em**: `app/Livewire/UpsellOffer.php`

```php
// Quando paga o upsell (PIX aprovado)
// → Redireciona automático para /upsell/thank-you

// Quando recusa o upsell
public function declineOffer()
{
    return redirect('/upsell/thank-you-recused');
}
```

---

## 🔍 Fluxo JavaScript/Livewire

### **Após PIX Aprovado (Upsell)**

```javascript
// Detecta pagamento aprovado
wire:poll.5s="checkPixPaymentStatus"
    ↓
Status = 'approved'
    ↓
handlePixApproved()
    ↓
Salva dados sessão
    ↓
dispatch('redirect-success', 
  url: '/upsell/thank-you')
    ↓
JavaScript redireciona
```

---

## 📊 Dados Salvos em Sessão

Ambas páginas têm acesso a:

```php
session()->get('last_order_transaction')    // ID da transação
session()->get('last_order_amount')         // Valor pago
session()->get('last_order_customer')       // Dados do cliente
session()->get('show_upsell_after_purchase') // Flag de upsell
```

---

## 📱 Responsividade

Ambas páginas são **100% responsivas**:
- ✅ Mobile (< 640px)
- ✅ Tablet (640px - 1024px)
- ✅ Desktop (> 1024px)

---

## 🎨 Design Compartilhado

Ambas usar:
- Fundo preto animado com gradientes vermelhos
- Componentes reutilizáveis
- Mesmas cores e tipografia
- Ícones animados
- Resumo de compra em cards

A **única diferença**: quantidade e conteúdo dos itens no resumo

---

**Gerado**: 2025-11-24 21:40
