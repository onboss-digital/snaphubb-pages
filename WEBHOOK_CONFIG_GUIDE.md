# 🔗 Como Ver e Configurar Webhooks na Pushing Pay

## 📍 Acessar o Dashboard

### **Passo 1: Login no Dashboard**

1. Acesse: **https://app.pushinpay.com.br/**
2. Faça login com seu usuário e senha
3. Você receberá um **código 2FA por email** → insira na plataforma

---

## 🔧 Encontrando Configurações de Webhook

A Pushing Pay pode não ter uma seção óbvia de "Webhooks" como outras plataformas.

### **Locais Possíveis:**

1. **Menu Lateral:**
   - Procure por: "Configurações" / "Settings"
   - "Integração" / "API"
   - "Webhooks" / "Notificações"
   - "Desenvolvedor" / "Developer"

2. **Se não encontrar webhook direto:**
   - Vá em: **Configurações → Integrações → API**
   - Ou: **Desenvolvedor → Webhooks**

---

## 🔑 Informações Importantes para Configurar

Se você precisar **CRIAR** um webhook, a Pushing Pay pedirá:

### **URL do Webhook:**
```
https://seu-dominio.com/api/pix/webhook
```

### **Método:**
- POST (padrão)

### **Eventos para Escutar:**
- `payment.received` ✅ (Pagamento Recebido)
- `payment.approved` ✅ (Pagamento Aprovado)
- `payment.failed` (Pagamento Falhou)
- `payment.expired` (Pagamento Expirou)

---

## ⚠️ IMPORTANTE: Seu Servidor Deve Estar Pronto

Antes de ativar webhooks, certifique-se:

1. **Seu servidor está online** (não local)
2. **A URL está acessível externamente**:
   ```bash
   curl https://seu-dominio.com/api/pix/webhook
   ```

3. **Seu código Laravel está preparado** para receber:

**Arquivo**: `app/Http/Controllers/PixController.php` (ou rota webhook)

```php
Route::post('/api/pix/webhook', [PixController::class, 'handleWebhook']);

public function handleWebhook(Request $request)
{
    $data = $request->json()->all();
    
    // Valida assinatura (se Pushing Pay enviar)
    $signature = $request->header('X-Signature');
    
    // Processa evento
    $eventType = $data['event'] ?? $data['type'];
    
    if ($eventType === 'payment.approved') {
        // Atualiza pagamento no banco de dados
        // Libera acesso ao usuário
    }
    
    return response()->json(['status' => 'received']);
}
```

---

## 🔍 Verificando se Webhooks Estão Ativos

### **No Dashboard Pushing Pay:**

1. Vá em **Configurações → Webhooks**
2. Procure por uma tabela com:
   - URL do webhook
   - Status (ativo/inativo)
   - Últimas tentativas/logs
   - Botões de teste

### **Testando um Webhook:**

Muitas plataformas têm botão **"Testar" ou "Send Test"**

Se vir:
- ✅ **Green/Sucesso** → Webhook está funcionando
- ❌ **Vermelho/Erro** → Verifique URL e certificado SSL

---

## 🚨 Possíveis Problemas

### **1. Webhook Não Recebe Eventos**

**Causas:**
- ❌ URL não acessível externamente
- ❌ Certificado SSL expirado
- ❌ Firewall bloqueando Pushing Pay
- ❌ Webhook não ativado

**Solução:**
```bash
# Teste se a URL é acessível
curl -v https://seu-dominio.com/api/pix/webhook

# Se retornar erro → problema na rede/SSL
```

### **2. Erro 401 ou 403**

**Causa**: Falta autenticação ou token inválido

**Solução**: Adicione autenticação no webhook:
```php
Route::post('/api/pix/webhook', function (Request $request) {
    // Valida token de Pushing Pay
    $token = $request->header('X-API-Token');
    if ($token !== env('PP_WEBHOOK_TOKEN')) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    
    // Processa webhook
})->middleware('api');
```

### **3. Webhook Retorna Erro 500**

**Causa**: Erro no código PHP

**Solução**: Verifique logs:
```bash
tail -f storage/logs/laravel.log
```

---

## 📊 Como Funciona o Webhook (Fluxo)

```
1. Usuário paga PIX
   ↓
2. Banco confirma
   ↓
3. Pushing Pay detecta
   ↓
4. Pushing Pay envia POST para seu webhook:
   {
     "event": "payment.approved",
     "payment_id": "12345",
     "amount": 2490,
     "status": "approved"
   }
   ↓
5. Seu servidor recebe e processa
   ↓
6. Retorna 200 OK
   ↓
7. Sistema sabe que recebeu
```

---

## ✅ Verificação Rápida

**Checklist do Webhook:**

- [ ] URL configurada em Pushing Pay
- [ ] URL está HTTPS (com certificado válido)
- [ ] URL é externamente acessível
- [ ] Endpoint PHP existe e retorna 200
- [ ] Webhook está ativado (não desativado)
- [ ] Eventos corretos selecionados
- [ ] Logs mostram tentativas de envio

---

## 🆘 Se Não Conseguir Encontrar

Entre em contato com **suporte Pushing Pay**:

- **WhatsApp**: +55 1555 7803830
- **Email**: contato@pushinpay.com.br
- **Site**: https://pushinpay.com.br/sac

Pergunte especificamente:
> "Como configurar webhooks para receber notificações de pagamento PIX aprovado? Qual é a URL correta e qual é o token de autenticação?"

---

## 📝 Alternativa: Polling (Sem Webhook)

Se não conseguir configurar webhook, o sistema **já está usando polling**:

```php
wire:poll.5s="checkPixPaymentStatus"
```

Isso consulta status a cada 5 segundos sem precisar de webhook.

**Vantagem**: Funciona sempre
**Desvantagem**: Mais requisições, menos real-time

---

## 🎯 Resumo

| Passo | Ação |
|-------|------|
| 1 | Login em https://app.pushinpay.com.br |
| 2 | Procure "Webhooks" ou "Integrações" |
| 3 | Configure URL: `https://seu-dominio.com/api/pix/webhook` |
| 4 | Selecione evento: `payment.approved` |
| 5 | Ative webhook |
| 6 | Teste com botão "Enviar Teste" |
| 7 | Verifique logs em seu servidor |

---

**Gerado**: 2025-11-24 21:50
