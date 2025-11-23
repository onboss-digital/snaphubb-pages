# DEBUG: PIX Redirecionamento não funcionando

## 📋 Checklist para Debugar

### 1. **Verificar se `handlePixApproved()` foi chamado**
```bash
tail -f storage/logs/laravel.log | grep "PIX aprovado - INICIANDO"
```

**Se você VER a mensagem:**
- ✅ O webhook foi recebido
- ✅ PIX foi marcado como aprovado
- ✅ Segue para o passo 2

**Se NÃO ver a mensagem:**
- ❌ Webhook não está chegando OU
- ❌ Status do pagamento não é "approved"
- ❌ Polling não está funcionando

---

### 2. **Verificar se `redirect-success` foi disparado**
```bash
tail -f storage/logs/laravel.log | grep "DISPATCHING REDIRECT"
```

**Se você VER a mensagem:**
- ✅ Livewire disparou o evento
- ✅ Segue para o passo 3 (verificar frontend)

**Se NÃO ver a mensagem:**
- ❌ `handlePixApproved()` não completou corretamente
- ❌ Procure por erros: `grep "REDIRECT DISPATCH FAILED"`

---

### 3. **Verificar o console do navegador (DevTools)**

1. Abra seu site em produção
2. Faça uma venda via PIX
3. Abra o **Console** (F12 → Console)
4. Procure por logs como:
   ```
   🔴 [PagePay] redirect-success event received: {...}
   🔴 [PagePay] REDIRECTING NOW to: https://...
   ```

**Se você VER estes logs:**
- ✅ JavaScript listener funcionou
- ✅ O problema está no `window.location.href`
- ➡️ Pode ser bloqueio de navegador ou erro de URL

**Se NÃO ver estes logs:**
- ❌ O evento `redirect-success` não chegou ao navegador
- ❌ Pode ser problema de Livewire connection

---

### 4. **Verificar se há erros nos logs**

```bash
# Procurar por erros relacionados a PIX
tail -50 storage/logs/laravel.log | grep -i "error\|failed\|exception" | grep -i "pix\|redirect"

# Procurar por avisos
tail -50 storage/logs/laravel.log | grep "warning" | grep -i "pix"
```

---

## 🔧 Possíveis Soluções

### **Problema 1: Webhook não chega (status não muda para approved)**
```bash
# Verificar se webhook está sendo recebido
grep "Received Mercado Pago webhook" storage/logs/laravel.log | tail -5

# Se não receber, verifique em production:
# 1. MERCADOPAGO_NOTIFICATION_URL está correto?
# 2. Seu servidor pode receber requisições externas?
# 3. Firewall/WAF está bloqueando?
```

### **Problema 2: Livewire dispatch não funciona**
```bash
# Verifique se há erro de Livewire
tail -f storage/logs/laravel.log | grep "Livewire\|dispatch"
```

### **Problema 3: URL de redirecionamento está errada**
- Verifique se `config('app.url')` está correto em produção
- Deverá ser igual ao domínio do seu site

---

## 📊 Fluxo Esperado de Logs

Você deverá ver **NESTA ORDEM**:

1. `"Received Mercado Pago webhook"`
2. `"MercadoPagoWebhook: payment status" ... "status":"approved"`
3. `"FB CAPI sending purchase event"` (seu Facebook CAPI)
4. `"PIX aprovado - INICIANDO REDIRECIONAMENTO"`
5. `"Session data saved"`
6. `"checkout-success event dispatched"`
7. `"DISPATCHING REDIRECT"`
8. `"REDIRECT DISPATCH SUCCESSFUL"`

---

## 🐛 Teste Local Rápido

Se você quer testar localmente:

1. Gere um PIX
2. Simule a aprovação via Postman:

```bash
curl -X POST http://localhost:8000/api/webhook/mercadopago \
  -H "Content-Type: application/json" \
  -d '{
    "id": "webhook_test",
    "data": {
      "id": PAYMENT_ID_AQUI
    }
  }'
```

3. Verifique os logs

---

## ❓ Dúvidas Comuns

**P: "Mas se não redireciona, como o usuário sabe que foi aprovado?"**
- R: O usuário vê a página com PIX expirado ou pode receber email do Mercado Pago

**P: "Pode ser que o navegador está bloqueando o redirect?"**
- R: Improvável, mas abra DevTools (F12) e veja a aba de Network e Console

**P: "Pode ser CORS?"**
- R: Não, porque é um redirect para o mesmo domínio

---

## 📞 Próximos Passos

Rode os comandos acima e me mostre:
1. Os logs encontrados (ou "não encontrado")
2. O que aparece no DevTools do navegador
3. A mensagem de erro (se houver)

Com isso consigo saber exatamente onde está o problema!
