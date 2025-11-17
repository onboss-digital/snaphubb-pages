# 🚀 GUIA DE IMPLANTAÇÃO RÁPIDA - PIX SNAPHUBB

## ⚡ Setup em 5 Minutos

### Passo 1: Configurar Variáveis de Ambiente (1 min)

Editar `.env`:

```bash
# PIX - Mercado Pago
ENVIRONMENT=sandbox
MP_ACCESS_TOKEN_SANDBOX=APP_USR-XXXXXXXXXXXXX
MP_ACCESS_TOKEN_PROD=XXXXXXXXXXXXX
```

Substituir `XXXXXXXXXXXXX` pelos tokens reais do Mercado Pago.

### Passo 2: Limpar Cache (30 segundos)

```bash
php artisan config:clear
php artisan cache:clear
```

### Passo 3: Testar Conexão (1 min)

Acessar checkout em Português (Brasil) e clicar botão "🏦 PIX".

Se aparecer o modal, sucesso! ✅

### Passo 4: Validar Logs (1 min)

```bash
tail -f storage/logs/payment_checkout.log
```

Procurar por `MercadoPagoPixService` para confirmar funcionamento.

### Passo 5: Fazer Teste de Pagamento (30 segundos)

1. Gerar PIX
2. Simular pagamento
3. Confirmar redirecionamento

---

## 📋 Checklist de Produção

- [ ] ENVIRONMENT=production
- [ ] MP_ACCESS_TOKEN_PROD configurado
- [ ] HTTPS habilitado
- [ ] SSL verificação ativa
- [ ] Logs configurados
- [ ] Monitoramento ativo
- [ ] Backup database
- [ ] Rate limiting configurado

---

## 🔍 Validação Rápida

### Verificar Serviço PIX
```php
php artisan tinker
$service = app(App\Services\MercadoPagoPixService::class);
$service->getEnvironment(); // Deve retornar 'sandbox' ou 'production'
```

### Testar Endpoint
```bash
curl -X POST http://127.0.0.1:8000/api/pix/create \
  -H "Content-Type: application/json" \
  -d '{"amount":1000,"customer_email":"test@example.com","customer_name":"Test"}'
```

### Verificar Modal
```javascript
// Console do navegador
Livewire.dispatch('start-pix-polling'); // Simula início do polling
```

---

## 🆘 Troubleshooting Rápido

| Problema | Solução |
|----------|---------|
| "Token não configurado" | Verificar `.env` com `echo $MP_ACCESS_TOKEN_SANDBOX` |
| PIX não aparece | Verificar idioma = português em seletor |
| Modal não funciona | F12 → Console procurar por erros |
| Polling não inicia | Verificar `payment_id` não está vazio |
| Redirecionamento errado | Ajustar URL em `handlePixApproved()` |

---

## 📞 Contatos Úteis

- **Mercado Pago Suporte**: https://www.mercadopago.com.br/developers
- **Docs Oficial**: https://www.mercadopago.com.br/developers/pt/docs
- **Status API**: https://status.mercadopago.com

---

## ✅ Após Implantar

1. Testar com cartões de teste
2. Verificar logs regularmente
3. Monitorar taxa de sucesso/falha
4. Documentar credenciais de forma segura
5. Configurar alertas para erros
6. Fazer backup automático

---

**Tempo Total**: ~5 minutos  
**Complexidade**: Baixa (configuração de variáveis)  
**Risco**: Muito Baixo (código isolado)

🎉 Feature PIX está pronta para usar!
