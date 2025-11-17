# Resumo da Implementação PIX com Sincronização Stripe

## 🎯 Objetivo Alcançado

Integrar pagamento PIX (Mercado Pago) sincronizado com o Stripe, garantindo que **o mesmo valor** seja utilizado em ambas as formas de pagamento, vindo da **mesma fonte de verdade** (API externa).

---

## ✅ O Que Foi Implementado

### 1. **Frontend - Livewire Component** (`app/Livewire/PagePay.php`)

#### Nova Função: `preparePIXData()`
- **Propósito:** Prepara dados do PIX espelhando a estrutura do Stripe
- **Sincronização:** Extrai valor de `$this->totals['final_price']` (FONTE DE VERDADE)
- **Conteúdo:**
  - Valor em centavos (conversão de formato brasileiro)
  - Dados do cliente (nome, email, telefone, CPF)
  - Itens do carrinho (plano principal + bumps)
  - Metadados (UTM, idioma, etc.)

#### Função: `sendPixToBackend()`
- **Propósito:** Comunicação segura com endpoint backend
- **Método:** POST com headers HTTP
- **Rota:** `/api/pix/create`
- **Segurança:** Validação de integridade no backend

#### Modificação: `generatePixPayment()`
- Antes: Chamava Mercado Pago diretamente no frontend
- Depois: Chama `preparePIXData()` → `sendPixToBackend()` → Backend

#### Import Adicionado
```php
use Illuminate\Support\Facades\Http;
```

---

### 2. **Backend - PixController** (`app/Http/Controllers/PixController.php`)

#### Método Principal: `create(Request $request)`

**Validação em 7 Etapas:**

1. **Validação de Dados** - Valida todos os campos obrigatórios
   - Amount, currency_code, plan_key, customer, cart

2. **Validação de Integridade** - `isValidAmountForPlan()`
   - Busca o preço esperado da MESMA API que o frontend
   - Compara: valor recebido ≈ (preço_plano + bumps)
   - Tolerância: 5% para erros de conversão
   - **Segurança:** Previne tentativas de alterar valor no frontend

3. **Validação de CPF** - `isValidCpf()`
   - Verifica 11 dígitos
   - Valida dígitos verificadores (algoritmo oficial)

4. **Geração de Descrição** - `buildPaymentDescription()`
   - Formato: "Assinatura SnapHubb - Plano {X} - {Nome} - {Data}"

5. **Criação no Mercado Pago** - `$pixService->createPixPayment()`
   - Envia dados validados ao serviço de PIX

6. **Log para Auditoria**
   - Registra: payment_id, amount, email, plan_key

7. **Retorno de Dados**
   - QR Code (base64)
   - QR Code em texto
   - Payment ID
   - Status
   - Data de expiração

#### Métodos de Segurança

**`isValidAmountForPlan()`**
```
Entrada: plan_key, amount, currency_code, cart
Lógica:
  1. Busca plans API: https://snaphubb.com/api/get-plans
  2. Encontra plan_key no JSON
  3. Extrai preço da moeda solicitada
  4. Soma valores dos bumps (operation_type = 2)
  5. Valida: |amount_recebido - total_esperado| ≤ 5%
Retorna: boolean
```

**`isValidCpf()`**
```
Validação:
  - Exatamente 11 dígitos
  - Não todos iguais
  - Primeiro dígito verificador correto
  - Segundo dígito verificador correto
```

---

### 3. **Rota API** (`routes/api.php`)

```php
POST /api/pix/create → PixController@create
GET /api/pix/status/{paymentId} → PixController@getPaymentStatus
```

---

## 🔄 Fluxo de Dados

### Antes (PIX direto no frontend)
```
Frontend (generatePixPayment)
    ↓ (calcula valor aqui)
    ↓
Mercado Pago API (cria QR code)
    ↓
Frontend (mostra QR code)
```

❌ **Problema:** Valor não sincronizado com Stripe

### Depois (PIX via backend)
```
Frontend (generatePixPayment)
    ↓
Frontend (preparePIXData)
    ├─ Extrai: $this->totals['final_price'] ← FONTE DE VERDADE
    ├─ Estrutura: cliente + carrinho + metadados
    ↓
Frontend (sendPixToBackend)
    ↓
Backend (PixController@create)
    ├─ Valida: amount ≈ plan_key + bumps
    ├─ Valida: CPF
    ├─ Cria: Mercado Pago
    ↓
Backend (retorna QR code + dados)
    ↓
Frontend (mostra modal PIX)
```

✅ **Vantagem:** Mesmo valor que Stripe + Validação Backend

---

## 🛡️ Segurança Implementada

### 1. **Validação de Integridade de Valor**
- Frontend envia valor
- Backend compara com preço esperado da API
- Rejeita se diferença > 5%
- Log de tentativas suspeitas

### 2. **Validação de CPF**
- Algoritmo oficial com dígitos verificadores
- Rejeita padrões inválidos (ex: 11111111111)

### 3. **Logs de Auditoria**
```
Log SUCCESS: payment_id, amount, email, plan_key
Log WARNING: tentativa de valor inválido + IP
Log ERROR: falha na criação
```

### 4. **Tratamento de Exceções**
- Validação: Retorna 422 com mensagens específicas
- Erro no Mercado Pago: Retorna 500 com log
- Erro na API de plans: Rejeita (por segurança)

---

## 📊 Dados Sincronizados

Ambas as formas de pagamento (Stripe e PIX) agora recebem:

| Item | Stripe | PIX | Fonte |
|------|--------|-----|-------|
| **Valor** | `$this->totals['final_price']` | Via `preparePIXData()` | API Externa |
| **Moeda** | `$this->selectedCurrency` | Via `currency_code` | Frontend |
| **Cliente** | Via `prepareCheckoutData()` | Via `customer` | Formulário |
| **Plano** | Via `selectedPlan` | Via `plan_key` | Seleção |
| **Bumps** | Em `cart` array | Em `cart` array | Checkboxes |

---

## 🧪 Como Testar

### Teste 1: Valor Sincronizado
1. Selecionar plano (ex: Mensal = R$ 19,90)
2. Adicionar bump (ex: +R$ 9,90)
3. Stripe: total deve ser R$ 29,80
4. PIX: gerar → backend deve validar R$ 29,80

### Teste 2: Segurança (Value Tampering)
1. Abrir DevTools (F12)
2. Interceptar requisição POST /api/pix/create
3. Alterar amount para 1 (centavo)
4. Backend retorna 422: "Valor não corresponde"

### Teste 3: CPF Inválido
1. Preencher: "11111111111"
2. Clicar "Gerar PIX"
3. Erro: "CPF inválido"

### Teste 4: Email Inválido
1. Preencher: "email_invalido"
2. Clicar "Gerar PIX"
3. Erro: "E-mail é obrigatório"

---

## 📝 Variáveis de Ambiente Necessárias

```bash
# .env
MERCADO_PAGO_PUBLIC_KEY=seu_public_key
MERCADO_PAGO_ACCESS_TOKEN=seu_access_token
MP_ACCESS_TOKEN_SANDBOX=seu_token_sandbox (desenvolvimento)
PLANS_API_URL=https://snaphubb.com/api/get-plans (padrão)
```

---

## 🚀 Próximos Passos (Prioridade)

### 1. **Webhook PIX** (URGENTE)
- Endpoint: POST `/api/pix/webhook`
- Mercado Pago notifica quando PIX é pago
- Atualiza status do pedido em database

### 2. **Armazenar Transações**
- Tabela: `pix_transactions`
- Campos: payment_id, amount, email, plan_key, status, created_at
- Permite: rastreamento e reconciliação

### 3. **Polling Status** (Frontend)
- Script JS: pooling a cada 3s
- Verifica: GET `/api/pix/status/{payment_id}`
- Atualiza modal quando pago

### 4. **Testes Automatizados**
- Unit: validaçãoCPF, valor, plano
- Feature: fluxo completo PIX
- E2E: Playwright (já existe script)

### 5. **Documentação de Webhooks**
- Como Mercado Pago notifica
- Assinatura de requisição
- Response esperado

---

## 📁 Arquivos Modificados

| Arquivo | Tipo | Mudança |
|---------|------|---------|
| `app/Livewire/PagePay.php` | Modificado | +50 linhas (preparePIXData, sendPixToBackend) |
| `app/Http/Controllers/PixController.php` | Modificado | +150 linhas (validações, segurança) |
| `routes/api.php` | Modificado | Atualizado método route: `create` |

---

## 📋 Checklist de Implementação

- [x] Criar função `preparePIXData()` no frontend
- [x] Criar função `sendPixToBackend()` no frontend
- [x] Criar método `PixController@create()` no backend
- [x] Implementar `isValidAmountForPlan()` (validação de segurança)
- [x] Implementar `isValidCpf()` (validação de CPF)
- [x] Implementar `buildPaymentDescription()` (descrição)
- [x] Atualizar rota `/api/pix/create`
- [x] Adicionar import `Http` facade
- [ ] **Próximo:** Criar webhook para confirmação de pagamento
- [ ] **Próximo:** Criar tabela de transações PIX
- [ ] **Próximo:** Implementar polling no frontend

---

## 💡 Diferenças Stripe vs PIX

| Aspecto | Stripe | PIX |
|--------|--------|-----|
| **Processamento** | Card token no frontend | QR Code no backend |
| **Confirmação** | Imediata | Via webhook + polling |
| **Valor** | Via `prepareCheckoutData()` | Via `preparePIXData()` |
| **Validação** | Stripe valida card | Backend valida amount |
| **Segurança** | PCI compliance | Value tampering check |

---

## 🔗 Relação com Sistema Existente

**Factory Pattern Mantido:**
- `PaymentGatewayFactory` continua funcionando
- PIX agora é segunda opção alongside Stripe
- Mesma estrutura de `PaymentGatewayInterface`

**API de Planos Reusada:**
```
Frontend:
  - getPlans() → https://snaphubb.com/api/get-plans
  - Armazena em $plans[]
  - Usa para calculateTotals()

Backend:
  - isValidAmountForPlan() → https://snaphubb.com/api/get-plans
  - Valida valor recebido
  - Mesma fonte de verdade ✅
```

---

## 🎓 Conceitos Aplicados

1. **Source of Truth**
   - `$this->totals['final_price']` é a única fonte de valor

2. **Defense in Depth**
   - Frontend: validação de formato
   - Backend: validação de integridade + CPF + API

3. **Audit Trail**
   - Todos os passos são logados
   - IP registrado em tentativas suspeitas

4. **Graceful Degradation**
   - Se API de plans falha: rejeita payment (seguro)
   - Se Mercado Pago falha: log + retorna erro

5. **DRY (Don't Repeat Yourself)**
   - Mesma API (`getPlans()`) usada frontend e backend
   - Mesmo CPF validator em frontend e backend

---

**Status:** ✅ IMPLEMENTAÇÃO COMPLETA
**Data:** 16 de Novembro de 2025
**Branch:** bkp-local
**Próxima Review:** Implementação de Webhooks
