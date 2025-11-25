# 🚀 QUICK START - REFERÊNCIA RÁPIDA PIX

**Para leitura rápida (5 min)**

---

## 1️⃣ STATUS DO SISTEMA

✅ **TUDO FUNCIONAL**

- ✅ QR Code gerado automaticamente
- ✅ Modal com blur effect
- ✅ Timer (5:00 countdown)
- ✅ Botão "Ou pagar com Cartão" (30s)
- ✅ Webhook integrado
- ✅ Polling automático
- ✅ Redirecionamento upsell
- ✅ Facebook Pixel tracking

---

## 2️⃣ FLUXO EM 3 PASSOS

```
USUÁRIO PAGA → SISTEMA DETECTA → REDIRECIONA UPSELL
(instântaneo    (5 segundos)    (automático)
via webhook)
```

---

## 3️⃣ ARQUIVOS PRINCIPAIS

| Arquivo | Função |
|---------|--------|
| `app/Livewire/PagePay.php` | Lógica PIX no backend |
| `resources/views/livewire/page-pay.blade.php` | Frontend modal PIX |
| `routes/api.php` | Webhook endpoint |
| `app/Http/Controllers/PushingPayWebhookController.php` | Webhook handler |
| `.env` | Configuração (tokens) |

---

## 4️⃣ CONFIGURAÇÃO MÍNIMA (.env)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com
PIX_PROVIDER=pushinpay
PP_ACCESS_TOKEN_PRODUCTION=seu_token
FB_PIXEL_ID=seu_pixel
SESSION_DRIVER=file
CACHE_STORE=file
```

---

## 5️⃣ TESTE RÁPIDO (Sandbox)

```bash
# 1. Simular pagamento
curl -X POST http://127.0.0.1:8000/api/pix/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "event": "payment.approved",
    "data": {"id": "PIX_TEST_123", "status": "approved"}
  }'

# 2. Esperar redirecionamento
# 3. Verificar logs
tail -f storage/logs/laravel.log | grep PIX
```

---

## 6️⃣ PRODUÇÃO - ANTES DE DEPLOY

- [ ] `.env` com valores de produção
- [ ] Webhook configurado em Pushing Pay dashboard
- [ ] HTTPS com certificado válido
- [ ] Backup do banco de dados
- [ ] Migrations executadas

---

## 7️⃣ MONITORAR

```bash
# Ver transações PIX
php artisan tinker
Order::where('pix_id', '!=', null)->get()

# Ver erros
grep -i error storage/logs/laravel.log

# Ver webhooks recebidos
grep "webhook received" storage/logs/laravel.log
```

---

## 8️⃣ ENDPOINT WEBHOOK

```
POST https://seu-dominio.com/api/pix/webhook
```

**Payload esperado:**
```json
{
  "event": "payment.approved",
  "data": {
    "id": "PIX_12345",
    "amount": 24.90,
    "status": "approved"
  }
}
```

**Resposta:**
```json
{
  "success": true
}
```

---

## 9️⃣ DOCUMENTAÇÃO COMPLETA

- `RESUMO_EXECUTIVO_PIX.md` - Visão geral
- `FLUXO_PAGAMENTO_COMPLETO.md` - Detalhado passo-a-passo
- `GUIA_TESTES_PIX.md` - Teste manual
- `CHECKLIST_PRE_PRODUCAO.md` - Deploy checklist

---

## 🔟 TROUBLESHOOTING

### ❌ QR Code não aparece
→ Verificar token em `.env`

### ❌ Webhook não recebido
→ Confirmar URL em Pushing Pay dashboard

### ❌ Não redireciona
→ Verificar logs: `grep "REDIRECT" storage/logs/laravel.log`

### ❌ Modal não abre
→ Verificar console do navegador (F12)

---

## ⚡ COMANDOS ÚTEIS

```bash
# Limpar tudo
php artisan optimize:clear

# Compilar assets
npm run build

# Ver model Order
php artisan tinker
Order::first()

# Logs real-time
tail -f storage/logs/laravel.log

# Testar curl webhook
curl -X POST http://127.0.0.1:8000/api/pix/webhook \
  -H "Content-Type: application/json" \
  -d '{"event":"payment.approved","data":{"id":"PIX_TEST"}}'
```

---

## 📱 TESTE MOBILE

1. Abrir DevTools (F12)
2. Toggle device toolbar (Ctrl+Shift+M)
3. Testar em iPhone SE (375px) e iPad (768px)
4. Verificar QR code responsivo
5. Verificar botão clicável

---

## 💰 VALORES

```
Plano padrão: R$ 49,90
Desconto PIX: -R$ 25,00
Total: R$ 24,90
```

---

## 🎯 PRÓXIMAS HORAS

1. [ ] (Agora) Ler este documento
2. [ ] (5 min) Testar webhook localmente
3. [ ] (30 min) Seguir GUIA_TESTES_PIX
4. [ ] (2h) Deploy em staging
5. [ ] (1h) Validar em staging
6. [ ] (1-2h) Deploy em produção

---

## ✅ SUCCESS CRITERIA

✅ PIX modal abre  
✅ QR code visível  
✅ Timer funciona  
✅ Botão aparece em 30s  
✅ Blur effect funciona  
✅ Webhook recebido  
✅ Order marcada paid  
✅ Redireciona upsell  
✅ Pixel recebe evento  
✅ Sem erros no log  

---

**Tudo pronto? 🚀 Vá para CHECKLIST_PRE_PRODUCAO.md**
