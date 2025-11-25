# 🔍 DIAGNÓSTICO: CÓDIGO PIX EM PRODUÇÃO

**Data:** 25 de Novembro de 2025  
**Problema:** Código PIX (copy/paste) mostrado é do Mercado Pago, não do Pushing Pay

---

## 🎯 SOLUÇÃO

Adicionei logging detalhado na API da Pushing Pay. Agora, quando alguém gerar um PIX em produção, aparecerá um log completo da resposta da API.

### 📍 Passos para Identificar o Problema:

**1. Gerar um PIX em Produção**
   - Acesse https://pay.snaphubb.com
   - Preencha o formulário
   - Selecione PIX
   - Clique em "Gerar PIX"

**2. Verifique os Logs**
   ```bash
   tail -f storage/logs/laravel.log | grep "Pushing Pay API Response"
   ```

**3. Você verá um log como:**
   ```
   [2025-11-25 XX:XX:XX] local.INFO: Pushing Pay API Response (Full) {
     "status_code": 200,
     "response": {
       "id": "PIX_xxxxx",
       "qr_code": "00020126...",  ← Aqui está o código!!!
       "qr_code_base64": "iVBORw0KG...",
       "value": 2490,
       "status": "created",
       "something_else": "..."
     }
   }
   ```

---

## 🔧 O QUE FAZER COM O LOG:

### Se o campo é `qr_code`:
✅ Está funcionando! O código PIX que aparece **IS** do Pushing Pay.

### Se o campo é DIFERENTE (ex: `copyAndPaste`, `pix_code`, `code`, etc):
❌ Precisa de ajuste. Faça:

1. **Anote o nome correto do campo** (ex: `copyAndPaste`)

2. **Me avise qual é** (ex: "O campo é copyAndPaste")

3. **Eu vou ajustar o código** na linha 110 do `PushingPayPixService.php`:

```php
// Tenta diferentes nomes de campo para o código PIX
$qrCode = $responseData['qr_code'] 
    ?? $responseData['copyAndPaste']  ← Será adicionado aqui
    ?? $responseData['pix_code']
    ?? $responseData['code'] 
    ?? null;
```

---

## 📊 O QUE FOI MUDADO

**Arquivo:** `app/Services/PushingPayPixService.php` (linha ~100-140)

**Antes:**
```php
'qr_code' => $responseData['qr_code'] ?? null,
```

**Depois:**
```php
$qrCode = $responseData['qr_code'] 
    ?? $responseData['copyAndPaste'] 
    ?? $responseData['pix_code'] 
    ?? $responseData['code'] 
    ?? null;

'qr_code' => $qrCode,
```

Agora tenta múltiplos nomes de campo!

---

## 🎯 PRÓXIMAS AÇÕES

1. ✅ Deploy do código atualizado em produção
2. ⏳ Gerar um PIX em produção
3. ⏳ Verificar logs com:
   ```bash
   grep "Pushing Pay API Response" storage/logs/laravel.log
   ```
4. ⏳ Me enviar o log completo (ou o nome do campo correto)
5. ⏳ Faço ajuste final se necessário

---

## 📝 CHECKLIST

- [ ] Code foi feito push para GitHub (branch pages)
- [ ] Code foi deployado em produção
- [ ] Alguém gerou um PIX em produção
- [ ] Verifiquei os logs
- [ ] Achei o campo correto do Pushing Pay
- [ ] Confirmei se está funcionando agora

---

## 💡 DICA

Se o código que aparece é do Mercado Pago é porque **em local está funcionando** mas **em produção está usando o Mercado Pago gateway**.

Verifique em produção:
```bash
# Em servidor de produção, execute:
php artisan tinker
env('PIX_PROVIDER')  # Deve retornar 'pushinpay'
env('PP_ACCESS_TOKEN_PROD')  # Deve estar preenchido
```

Se retornar 'mercadopago' ou vazio, é porque o .env em produção está diferente do local.

---

**Gerado:** 25 de Novembro de 2025
