# 🏦 Documentação - Feature PIX Mercado Pago

## 📋 Visão Geral

Feature completa de pagamento via **PIX Mercado Pago** integrada ao checkout SNAPHUBB. Suporta automáticamente ambientes **sandbox** e **production**.

**Status**: ✅ Implementado e pronto para produção

---

## 🎯 Features Implementadas

✅ Geração de pagamento PIX via Mercado Pago  
✅ QR Code estático e dinâmico (copia e cola)  
✅ Polling automático de status (a cada 4 segundos)  
✅ Timer de expiração em tempo real (30 minutos)  
✅ Tratamento de erros robusto  
✅ Suporte a ambientes sandbox e produção  
✅ Interface responsiva e moderna  
✅ Apenas disponível em Português (Brasil)  
✅ Não interfere com pagamento por cartão  

---

## 🔧 Configuração de Ambiente

### Variáveis .env Obrigatórias

```dotenv
# Ambiente (sandbox ou production)
ENVIRONMENT=sandbox

# Token de acesso Mercado Pago - Sandbox
MP_ACCESS_TOKEN_SANDBOX=APP_USR-XXXXXXXXXXXXX

# Token de acesso Mercado Pago - Production
MP_ACCESS_TOKEN_PROD=APP_USR-XXXXXXXXXXXXX
```

### Como obter os tokens

1. Acesse [Mercado Pago Developers](https://www.mercadopago.com.br/developers)
2. Faça login com sua conta Mercado Pago
3. Navegue até **Credenciais > Produção/Sandbox**
4. Copie o **Access Token**

---

## 📁 Estrutura de Arquivos

```
app/
├── Services/
│   └── MercadoPagoPixService.php    # Serviço PIX (criação e status)
├── Http/Controllers/
│   └── PixController.php             # Endpoints da API
└── Livewire/
    └── PagePay.php                   # Componente (métodos PIX)

routes/
└── api.php                           # Rotas PIX

resources/views/livewire/
└── page-pay.blade.php                # Modal e UI PIX

lang/br/
└── payment.php                       # Traduções PIX (português)
```

---

## 🚀 Como Usar

### 1. Backend - Serviço PIX

#### Criar Pagamento PIX

```php
use App\Services\MercadoPagoPixService;

$pixService = app(MercadoPagoPixService::class);

$response = $pixService->createPixPayment([
    'amount' => 10000,              // Valor em centavos (R$ 100,00)
    'description' => 'Pagamento - Plano Premium',
    'customerEmail' => 'usuario@email.com',
    'customerName' => 'João Silva',
]);

// Resposta de sucesso:
[
    'status' => 'success',
    'data' => [
        'payment_id' => 1234567890,
        'qr_code_base64' => 'data:image/png;base64,...',
        'qr_code' => '00020126360014br.gov.bcb.pix...',
        'expiration_date' => '2025-11-16T14:30:00Z',
        'amount' => 100.00,
        'status' => 'pending',
    ]
]

// Resposta de erro:
[
    'status' => 'error',
    'message' => 'Descrição do erro'
]
```

#### Consultar Status do Pagamento

```php
$response = $pixService->getPaymentStatus(1234567890);

// Resposta de sucesso:
[
    'status' => 'success',
    'data' => [
        'payment_id' => 1234567890,
        'payment_status' => 'approved',  // pending, approved, rejected, etc
        'status_detail' => null,
        'amount' => 100.00,
    ]
]
```

---

### 2. Frontend - Componente Livewire

#### Gerar PIX

```php
// No controller/componente
$this->generatePix();
```

Automaticamente:
- Valida email e nome
- Cria o pagamento via API
- Exibe modal com QR Code
- Inicia polling automático

#### Verificar Status

```php
// Chamado via polling a cada 4 segundos
$this->checkPixPaymentStatus();
```

Gerencia automaticamente:
- Status pendente (continua polling)
- Status aprovado (redireciona para sucesso)
- Status expirado (mostra mensagem)

---

## 📡 Endpoints da API

### POST `/api/pix/create`

Cria um novo pagamento PIX

**Headers:**
```
Content-Type: application/json
```

**Body:**
```json
{
    "amount": 10000,
    "description": "Pagamento - Plano Premium",
    "customer_email": "usuario@email.com",
    "customer_name": "João Silva"
}
```

**Response 201 (Success):**
```json
{
    "status": "success",
    "data": {
        "payment_id": 1234567890,
        "qr_code_base64": "data:image/png;base64,...",
        "qr_code": "00020126360014br.gov.bcb.pix...",
        "expiration_date": "2025-11-16T14:30:00Z",
        "amount": 100.00,
        "status": "pending"
    }
}
```

**Response 400 (Error):**
```json
{
    "status": "error",
    "message": "Descrição do erro",
    "errors": {
        "amount": ["O valor deve ser maior que zero"]
    }
}
```

---

### GET `/api/pix/status/:payment_id`

Consulta o status de um pagamento PIX

**Parameters:**
- `payment_id` (int): ID do pagamento

**Response 200 (Success):**
```json
{
    "status": "success",
    "data": {
        "payment_id": 1234567890,
        "payment_status": "approved",
        "status_detail": null,
        "amount": 100.00
    }
}
```

**Response 400 (Error):**
```json
{
    "status": "error",
    "message": "Pagamento não encontrado"
}
```

---

## 🔄 Fluxo Completo - Passo a Passo

1. **Usuário clica "🏦 PIX"** no checkout
2. **Frontend valida** dados obrigatórios (email, nome)
3. **Backend cria pagamento** via API Mercado Pago
4. **API retorna** QR Code, código copia e cola
5. **Modal PIX aparece** com:
   - QR Code (imagem)
   - Código PIX (copia e cola)
   - Valor e Timer
6. **Frontend inicia polling** (a cada 4 segundos)
7. **Backend consulta status** no Mercado Pago
8. **Ao receber aprovação**:
   - Para polling
   - Mostra sucesso
   - Redireciona após 2s

---

## ⏰ Estados de Pagamento

| Status | Ação | Tempo |
|--------|------|-------|
| `pending` | Continua polling | ∞ |
| `approved` | Redireciona sucesso | 2s |
| `rejected` | Mostra erro | - |
| `cancelled` | Mostra erro | - |
| `expired` | Oferece novo PIX | - |

---

## 🛡️ Tratamento de Erros

### Erros Comuns

| Erro | Causa | Solução |
|------|-------|---------|
| Token não configurado | `MP_ACCESS_TOKEN_*` vazio | Verificar `.env` |
| Valor inválido | amount <= 0 | Validar dados frontend |
| Email inválido | Formato incorreto | Validar email |
| Conexão falhou | Mercado Pago offline | Tentar novamente |
| 403 Forbidden | Token inválido | Renovar token no Mercado Pago |

### Logs

Todos os erros são registrados em:
```
storage/logs/payment_checkout.log
```

---

## 🧪 Testes

### Teste em Sandbox

1. Configure `.env`:
```dotenv
ENVIRONMENT=sandbox
MP_ACCESS_TOKEN_SANDBOX=SEU_TOKEN_AQUI
```

2. Monitore logs em tempo real:
```bash
tail -f storage/logs/payment_checkout.log
```

---

### Teste Manual - PIX Pago

1. Clique "🏦 PIX"
2. Preencha email e nome
3. Escaneie QR Code com seu app PIX
4. Realize a transferência
5. Status mudará para "Pagamento aprovado"
6. Será redirecionado automaticamente

---

### Teste Manual - PIX Expirado

1. Clique "🏦 PIX"
2. **Aguarde 30 minutos** (ou simule localmente)
3. PIX expirar e mostrar mensagem
4. Clique "Gerar novo PIX"

---

## 🔐 Segurança

✅ Tokens armazenados em variáveis de ambiente  
✅ Validação de dados em ambos frontend e backend  
✅ Verificação SSL automática em produção  
✅ Logs de todas as transações  
✅ Proteção contra CSRF (CSRF token no form)  

---

## 📞 Suporte

Para erros ou dúvidas:

1. Verificar `storage/logs/payment_checkout.log`
2. Inspecionar Console do Navegador (F12)
3. Verificar credenciais Mercado Pago
4. Consultar docs oficiais: https://www.mercadopago.com.br/developers

---

**Versão**: 1.0  
**Última atualização**: Novembro 2025  
**Status**: ✅ Production Ready

