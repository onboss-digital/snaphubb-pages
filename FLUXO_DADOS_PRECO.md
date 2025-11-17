# 📊 FLUXO COMPLETO DE PREÇOS - ONDE VEM O VALOR?

## 🎯 Resposta Curta
**O valor vem de uma API EXTERNA** chamada **STREAMIT API** (`https://snaphubb.com/api`), que retorna os planos de assinatura com seus preços.

---

## 🔄 FLUXO DETALHADO DE DADOS

```
┌─────────────────────────────────────────────────────────────────┐
│                     USUARIO ACESSA A PÁGINA                      │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ▼
            ┌────────────────────────────────┐
            │  PagePay Livewire Component    │
            │  mount() é chamado             │
            └────────────┬───────────────────┘
                         │
                         ▼
        ┌─────────────────────────────────────────────┐
        │ this->getPlans() dispara requisição HTTP    │
        │ Alvo: https://snaphubb.com/api/get-plans    │
        │ Método: GET                                 │
        │ Aguarda resposta...                         │
        └──────────┬──────────────────────────────────┘
                   │
                   ▼ (Async com Promise/Wait)
    ┌──────────────────────────────────────────────────┐
    │    BACKEND STREAMIT RESPONDE COM JSON            │
    │  (Backend externo - não é este repositório)      │
    │                                                  │
    │  Exemplo de resposta:                            │
    │  {                                               │
    │    "monthly": {                                  │
    │      "label": "Streaming snaphubb - 1/month",   │
    │      "hash": "prod_SZ4hJ7Q5aDSvVP",            │
    │      "nunber_months": 1,                         │
    │      "prices": {                                │
    │        "BRL": {                                 │
    │          "id": "price_1SBMDCJNRVv3P4xYWPBmCdhe", │
    │          "origin_price": "24.90",               │
    │          "descont_price": 24.90                 │
    │        },                                       │
    │        "USD": { ... },                          │
    │        "EUR": { ... }                           │
    │      }                                          │
    │    },                                           │
    │    "semi-annual": { ... },                      │
    │    "quarterly": { ... }                         │
    │  }                                              │
    └──────────┬───────────────────────────────────────┘
               │
               ▼
    ┌──────────────────────────────────────┐
    │ formatPlans() (PaymentGateway)        │
    │ Formata resposta para o front         │
    └──────────┬───────────────────────────┘
               │
               ▼
    ┌──────────────────────────────────────┐
    │ this->plans = resultado formatado    │
    │ Armazenado em memory (JavaScript)    │
    └──────────┬───────────────────────────┘
               │
               ▼
    ┌────────────────────────────────────────┐
    │ calculateTotals()                      │
    │ ├─ Pega plano selecionado (monthly)    │
    │ ├─ Pega moeda (BRL, USD, EUR)         │
    │ ├─ Extrai: origin_price, descont_price│
    │ ├─ Calcula: final_price = descont_price│
    │ └─ Salva em: this->totals              │
    │   {                                    │
    │     "month_price": "24,90",           │
    │     "final_price": "24,90",           │
    │     ...                               │
    │   }                                    │
    └────────────┬─────────────────────────┘
                 │
                 ▼
    ┌────────────────────────────────────────┐
    │ PÁGINA RENDERIZA COM VALORES           │
    │ ├─ Mostra preço mensal                 │
    │ ├─ Mostra desconto                     │
    │ ├─ Mostra total final                  │
    │ └─ Usuario vê os valores               │
    └────────────┬─────────────────────────┘
                 │
     ┌───────────┴────────────┐
     │                        │
     ▼                        ▼
   CARTÃO                    PIX
     │                        │
     │                        ▼
     │         ┌──────────────────────────────┐
     │         │ generatePixPayment() chamado │
     │         │                              │
     │         ├─ Valida campos PIX           │
     │         ├─ Chama getTotalPixAmount()   │
     │         │   └─ Pega this->totals       │
     │         │   └─ Extrai final_price     │
     │         │   └─ Converte BR para US    │
     │         │   └─ Multiplica por 100     │
     │         │       (centavos)             │
     │         │                              │
     │         ▼ Envia para Mercado Pago      │
     │  ┌──────────────────────────────────┐  │
     │  │ MercadoPagoPixService             │  │
     │  │ ->createPixPayment([               │  │
     │  │   'amount' => 2490,  ✅ VALOR!    │  │
     │  │   'description' => ...,           │  │
     │  │   'customerName' => ...           │  │
     │  │ ])                                │  │
     │  └──────────┬───────────────────────┘  │
     │             │                           │
     │             ▼                           │
     │      API MERCADO PAGO RESPONDE         │
     │      ├─ Gera QR Code                   │
     │      ├─ Cria transação                 │
     │      └─ Retorna dados                  │
     │         {                              │
     │           "qr_code": "00020...",       │
     │           "qr_code_base64": "image..", │
     │           "payment_id": 12345...       │
     │         }                              │
     │                                        │
     │             ▼                           │
     │      MODAL COM QR CODE EXIBIDO        │
     │      Usuario escaneia e paga           │
     │                                        │
     ▼
FLUXO CONCLUÍDO
```

---

## 🔍 ONDE O VALOR VIRA CENTAVOS PARA O PIX?

```javascript
// Arquivo: app/Livewire/PagePay.php
// Método: generatePixPayment() - Linha ~962

// 1. TOTALS vem formatado em brasileiro (com . e ,)
$this->totals['final_price'] = "24,90"  // String formatada

// 2. getTotalPixAmount() converte para número
return (float) str_replace(['.', ','], ['', '.'], $finalPrice);
// Resultado: 24.90 (float)

// 3. Multiplica por 100 para centavos
$totalAmount = $this->getTotalPixAmount() * 100;
// Resultado: 2490 (centavos)

// 4. Envia para Mercado Pago
$response = $this->pixService->createPixPayment([
    'amount' => (int) $totalAmount,  // 2490 centavos = R$ 24,90
    ...
]);
```

---

## 📦 ESTRUTURA DOS DADOS

### Fluxo de Preços Completo:

```
Camada 1: API Remota (snaphubb.com)
  ↓
Camada 2: Livewire Component (Browser Memory)
  ├─ $this->plans (array com todos os planos)
  ├─ $this->selectedPlan (qual plano é o selecionado)
  ├─ $this->selectedCurrency (BRL, USD, EUR)
  └─ $this->totals (preços formatados para exibição)
  ↓
Camada 3: Processamento
  ├─ getTotalPixAmount() (converte formato)
  └─ generatePixPayment() (envia para gateway)
  ↓
Camada 4: Gateway (Stripe ou Mercado Pago)
  └─ Recebe valor em centavos
  └─ Processa transação
```

---

## ✅ RESPOSTA À PERGUNTA

**"Como ele pega um valor se não existe nenhum produto com o valor criado?"**

### Resposta Completa:

1. **O valor não está hardcoded no código** ❌
2. **O valor vem de uma API REMOTA**: `https://snaphubb.com/api/get-plans`
3. **Essa API retorna objetos de plano** com:
   - Nome (monthly, semi-annual, quarterly)
   - Hash do produto (prod_SZ4hJ7Q5aDSvVP)
   - Preços em 3 moedas (BRL, USD, EUR)
   - Preço original vs desconto

4. **O frontend armazena na memória** e reutiliza:
   ```javascript
   // Carregado uma única vez (mount)
   this.plans = {
     monthly: {
       prices: {
         BRL: { origin_price: 24.90, descont_price: 24.90 }
       }
     }
   }

   // Reutilizado em calculateTotals()
   // Reutilizado em generatePixPayment()
   // Reutilizado em sendCheckout()
   ```

5. **Cada plano tem um Product ID real** no Stripe/Mercado Pago:
   - `prod_SZ4hJ7Q5aDSvVP` (Streaming snaphubb - 1/month)
   - Esse produto já existe no Stripe
   - O valor é consultado via API ao carregador a página

---

## 🚀 RESUMO

| Aspecto | Resposta |
|---------|----------|
| Valor é hardcoded? | ❌ NÃO |
| Valor vem de Mock? | ❌ NÃO |
| Valor vem de API? | ✅ SIM (snaphubb.com/api) |
| Valor é real? | ✅ SIM (produtos no Stripe/MP) |
| Onde fica armazenado? | ✅ Memória do navegador ($this->plans) |
| Como é usado para PIX? | ✅ Extraído e convertido em centavos |
| Pronto para produção? | ✅ SIM |

