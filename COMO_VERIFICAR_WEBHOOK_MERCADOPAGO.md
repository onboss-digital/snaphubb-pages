# 📋 Como Verificar a URL de Webhook no Mercado Pago

## 🔍 Passo a Passo

### **1. Acesse o Mercado Pago Business**
- Vá para: https://www.mercadopago.com.br/business
- Faça login com sua conta

### **2. Abra as Configurações da Aplicação**
```
Menu → Ferramentas → Aplicações → Sua App
```
OU
```
https://www.mercadopago.com.br/developers/pt/docs
```

### **3. Procure por "Webhooks" ou "Notificações"**

Dependendo da interface do Mercado Pago, pode estar em:
- **Configurações** → **Webhooks**
- **Developer** → **Webhooks**
- **API** → **Webhooks**
- **Notificações de pagamento**

### **4. Procure por estas informações:**

Você verá algo assim:

```
┌─────────────────────────────────────────┐
│ URL de Notificação (Webhook)            │
├─────────────────────────────────────────┤
│ https://snaphubb.com/api/pix/webhook    │
│        ou                               │
│ https://snaphubb.com/api/webhook/mp     │
│        ou                               │
│ https://seu-dominio.com/webhook         │
└─────────────────────────────────────────┘

Eventos Configurados:
☑ payment.created
☑ payment.updated
☐ charge.created
☐ refund.created
```

---

## 🎯 O que você precisa fazer:

### **Se a URL for:** `https://snaphubb.com/api/pix/webhook`
```bash
# Adicione esta rota no seu routes/api.php:
Route::post('/pix/webhook', [\App\Http\Controllers\MercadoPagoWebhookController::class, 'handle']);

# Depois faça deploy
```

### **Se a URL for:** `https://snaphubb.com/api/webhook/mercadopago`
```bash
# Mude seu .env para:
MERCADOPAGO_NOTIFICATION_URL=https://snaphubb.com/api/webhook/mercadopago

# Depois faça deploy
```

### **Se for outra URL:**
```bash
# Atualize para uma delas acima no Mercado Pago
```

---

## 📱 Interface do Mercado Pago (Screenshots dos passos)

### Passo 1-2: Menu Principal
```
┌─────────────────────────────────────┐
│ Mercado Pago Business               │
├─────────────────────────────────────┤
│ Início                              │
│ Ferramentas                    ►    │
│ ├─ Integrações                      │
│ ├─ Aplicações                  ►    │
│ │  └─ [Sua App]                     │
│ ├─ Webhooks                    ◄◄◄  │
│ └─ Notificações                     │
│ Conta                               │
└─────────────────────────────────────┘
```

### Passo 3-4: Webhooks
```
┌──────────────────────────────────────┐
│ Webhooks                             │
├──────────────────────────────────────┤
│                                      │
│ URL de Notificação:                  │
│ ┌────────────────────────────────┐  │
│ │ https://snaphubb.com/api/...   │  │
│ └────────────────────────────────┘  │
│                                      │
│ Eventos Ativos:                      │
│ ☑ payment.created                   │
│ ☑ payment.updated                   │
│ ☑ payment.approved                  │
│                                      │
│ [Editar]  [Testar]  [Deletar]       │
└──────────────────────────────────────┘
```

---

## 🧪 Como Testar a URL do Webhook

### **Opção 1: Teste direto no Mercado Pago**

1. Vá em **Webhooks**
2. Clique em **[Testar]** ou **[Test]**
3. Você deve ver resposta `200 OK`

Se receber **404** ou **não conecta**, a URL está errada.

### **Opção 2: Teste via terminal**

```bash
# Teste se sua URL está respondendo
curl -v https://snaphubb.com/api/webhook/mercadopago \
  -X POST \
  -H "Content-Type: application/json" \
  -d '{"id":"test"}'

# Deve retornar 200 OK ou JSON response
```

### **Opção 3: Verifique os logs**

```bash
# Se webhook foi recebido, deve ter logs:
tail -f storage/logs/laravel.log | grep "Received Mercado Pago webhook"

# Se não vê nada, webhook não está chegando
```

---

## ⚠️ Checklist Final

- [ ] Acessei https://www.mercadopago.com.br/business
- [ ] Fiz login
- [ ] Encontrei a seção de Webhooks
- [ ] Copiei a URL configurada
- [ ] Comparei com meu `.env`
- [ ] Se diferentes, atualizei no Mercado Pago ou no `.env`
- [ ] Fiz deploy das mudanças

---

## 🆘 Se Ainda Não Conseguir Encontrar

1. **Procure por:** "Configurar Webhooks" ou "IPN Settings"
2. **Ou clique em seu avatar** → **Configurações** → **Integrações**
3. **Ou vá direto:** https://www.mercadopago.com.br/business/settings/integrations

Se ainda tiver dúvida, me mostre a **URL exata** que você encontrou!
