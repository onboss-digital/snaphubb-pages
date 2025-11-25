# ✅ Corrigido: Feature de Cartão de Crédito com API Stripe

## Mudanças Realizadas

### 1. **Backend (app/Livewire/PagePay.php)**

#### Novo Método: `updateSelectedPaymentMethod()`
```php
#[\Livewire\Attributes\On('updateSelectedPaymentMethod')]
public function updateSelectedPaymentMethod($method)
{
    $this->selectedPaymentMethod = $method;
    
    // Se mudou para cartão de crédito, carrega planos da API do Stripe
    if ($method === 'credit_card') {
        $this->plans = $this->getPlans();        // ← API Backend Stripe
        $this->updateProductDetails();
    } else if ($method === 'pix') {
        $this->plans = $this->getPlansFromMock(); // ← Mock Local
        $this->updateProductDetails();
    }
}
```

#### Melhorado: `closeModal()`
- Antes: Hardcoded para 'credit_card', mas não recarregava planos
- Depois: Verifica se estava em PIX e recarrega planos da API antes de voltar para Cartão

```php
public function closeModal()
{
    // ... (reset flags)
    
    // Se estava em PIX, volta para cartão com planos da API
    if ($this->selectedPaymentMethod === 'pix') {
        $this->selectedPaymentMethod = 'credit_card';
        $this->plans = $this->getPlans(); // ← Recarrega API Stripe
        $this->updateProductDetails();
    }
}
```

### 2. **Frontend (resources/views/livewire/page-pay.blade.php)**

#### Adicionado: `wire:change` nos Radio Buttons

**Cartão de Crédito:**
```blade
<input type="radio" id="method_credit_card" name="payment_method" value="credit_card"
    wire:change="updateSelectedPaymentMethod('credit_card')"
    x-on:change="..."
    ...
/>
```

**PIX:**
```blade
<input type="radio" id="method_pix" name="payment_method" value="pix"
    wire:change="updateSelectedPaymentMethod('pix')"
    x-on:change="..."
    ...
/>
```

## Fluxo Agora Correto

### Cenário 1: Usuário seleciona Cartão (padrão)
```
1. Mount() é executado
2. selectedPaymentMethod = 'credit_card' (padrão)
3. getPlans() é chamado → API Backend Stripe ✅
4. Planos reais aparecem
```

### Cenário 2: Usuário muda para PIX (apenas BR)
```
1. Clica no radio button "PIX"
2. wire:change dispara updateSelectedPaymentMethod('pix')
3. getPlansFromMock() é chamado → Mock local ✅
4. Planos do mock aparecem
```

### Cenário 3: Usuário fecha modal PIX e volta para Cartão
```
1. Clica em fechar modal PIX
2. closeModal() é executado
3. Detecta que estava em PIX
4. getPlans() é chamado → API Backend Stripe ✅
5. Planos reais aparecem novamente
```

### Cenário 4: Usuário muda idioma para EN/ES (sem PIX)
```
1. changeLanguage('en') é chamado
2. selectedPaymentMethod = 'credit_card'
3. getPlans() é chamado → API Backend Stripe ✅
4. selectedLanguage !== 'br' então PIX não aparece
```

## Arquivos Modificados

| Arquivo | Mudança |
|---------|---------|
| `app/Livewire/PagePay.php` | +27 linhas: novo método + melhoria closeModal() |
| `resources/views/livewire/page-pay.blade.php` | +2 linhas: wire:change em 2 inputs |

## Verificação

### ✅ PIX sempre usa MOCK
- `getPlansFromMock()` carrega de `resources/mock/get-plans.json`
- Apenas quando `selectedPaymentMethod === 'pix'`

### ✅ Cartão sempre usa API Stripe
- `getPlans()` chamado para Backend `/get-plans`
- Quando `selectedPaymentMethod === 'credit_card'`

### ✅ Transições funcionam
- Cartão → PIX: Planos mudam para mock
- PIX → Cartão: Planos voltam para API
- Mudança de idioma: Lógica respeitada
- Fechar modal: Planos recarregados

## Testes Recomendados

```javascript
// Console do navegador - selecionar PIX
document.getElementById('method_pix').click();

// Verificar console
// Log: "PagePay: Método de pagamento alterado para PIX - Carregando planos do Mock"

// Depois clicar em Cartão
document.getElementById('method_credit_card').click();

// Log: "PagePay: Método de pagamento alterado para Cartão - Carregando planos da API"
```

## Segurança

✅ PIX com mock: Seguro - dados locais, sem chamadas API desnecessárias
✅ Stripe com API: Seguro - dados reais do backend, sem expor credenciais
✅ Transição: Segura - cada método carrega seus próprios dados

## Performance

- Mock carrega instantaneamente (local)
- API carrega via HTTP (pode ter latência)
- Sem cache conflicts entre PIX/Stripe
- Sem problemas de estado compartilhado
