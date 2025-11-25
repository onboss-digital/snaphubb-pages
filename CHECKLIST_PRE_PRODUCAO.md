# 🚀 CHECKLIST PRÉ-PRODUÇÃO - PIX PUSHING PAY

**Data:** 25 de Novembro de 2025  
**Status:** ✅ PRONTO PARA DEPLOY  
**Responsável:** DevOps / Desenvolvedor

---

## 📋 ANTES DE FAZER DEPLOY

### ✅ Código

- [ ] Todos os testes locais passando
- [ ] Sem console errors
- [ ] Sem console warnings
- [ ] Logs limpos: `php artisan log:clear`
- [ ] Cache limpo: `php artisan cache:clear`
- [ ] Views compiladas: `php artisan view:clear`
- [ ] Config cache: `php artisan config:cache`

### ✅ Banco de Dados

- [ ] Migrations executadas: `php artisan migrate`
- [ ] Seeds inseridos (se necessário): `php artisan db:seed`
- [ ] Tabela `orders` existe com campos:
  - `id` (primary key)
  - `user_id` (foreign key)
  - `pix_id` (unique)
  - `status` (pending/paid/declined/canceled)
  - `amount` (decimal)
  - `paid_at` (timestamp nullable)
  - `external_payment_status` (string nullable)
  - `created_at`, `updated_at`

### ✅ Variáveis de Ambiente (.env)

```env
# ⚠️ CRÍTICO: Mudar para produção
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seu-dominio.com

# Pushing Pay PIX
PIX_PROVIDER=pushinpay
ENVIRONMENT=production
PP_ACCESS_TOKEN_PRODUCTION=seu_token_aqui

# Webhook (Será enviado automaticamente)
# Não precisa configurar no .env, é enviado na requisição ao Pushing Pay

# Facebook Analytics
FB_PIXEL_ID=seu_pixel_aqui
FB_CAPI_ACCESS_TOKEN=seu_token_aqui

# Session/Cache/Queue (para evitar MySQL issues)
SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync

# Logs
LOG_LEVEL=error
LOG_CHANNEL=stack
```

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` com HTTPS
- [ ] `PP_ACCESS_TOKEN_PRODUCTION` preenchido
- [ ] `FB_PIXEL_ID` configurado
- [ ] `SESSION_DRIVER=file`
- [ ] `CACHE_STORE=file`
- [ ] `QUEUE_CONNECTION=sync`
- [ ] `LOG_LEVEL=error`

### ✅ SSL/HTTPS

- [ ] Domínio tem certificado SSL válido
- [ ] HTTPS funciona: `https://seu-dominio.com`
- [ ] Certificado válido e não expirado
- [ ] Sem mixed content warnings
- [ ] Redirecionamento HTTP → HTTPS funciona

### ✅ Firewall / Rede

- [ ] Porta 443 (HTTPS) aberta e acessível
- [ ] Não está bloqueado por firewall
- [ ] IP pode fazer requisições saintes (para APIs)
- [ ] Teste: `curl https://api.pushinpay.com.br`

---

## 📊 CONFIGURAÇÃO PUSHING PAY

### ✅ Conta Pushing Pay

- [ ] Conta criada e ativa
- [ ] KYC completo
- [ ] Documentos verificados
- [ ] Acesso ao dashboard: https://app.pushinpay.com.br

### ✅ API Credentials

- [ ] Obter token: Dashboard → Configurações → API
- [ ] Token formato: `55321|JaTW9wbkkKohC1cgIEyOLj1LhbQDwGg2zD...`
- [ ] Salvar em `.env`: `PP_ACCESS_TOKEN_PRODUCTION=seu_token`
- [ ] Testar token:
```bash
curl -X GET https://api.pushinpay.com.br/api/me \
  -H "Authorization: Bearer SEU_TOKEN_AQUI" \
  -H "Accept: application/json"
```

### ✅ Webhook Configuration

**NO DASHBOARD PUSHING PAY:**

1. [ ] Acessar https://app.pushinpay.com.br
2. [ ] Ir em: Configurações → Webhooks (ou Integrações)
3. [ ] Criar novo webhook:
   - **URL**: `https://seu-dominio.com/api/pix/webhook`
   - **Método**: POST
   - **Eventos**: 
     - [x] payment.approved
     - [x] payment.declined
     - [x] payment.canceled
     - [ ] payment.expired (opcional)
4. [ ] Salvar webhook
5. [ ] Testar com botão "Send Test"
6. [ ] Verificar em logs: `grep "webhook received" storage/logs/laravel.log`

**OU via API (advanced):**
```bash
curl -X POST https://api.pushinpay.com.br/api/webhooks \
  -H "Authorization: Bearer SEU_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://seu-dominio.com/api/pix/webhook",
    "events": ["payment.approved", "payment.declined", "payment.canceled"],
    "active": true
  }'
```

---

## 🔑 FACEBOOK PIXEL

### ✅ Setup Pixel

- [ ] Pixel criado em Facebook Business
- [ ] Pixel ID obtido
- [ ] Conversions API token gerado
- [ ] Configurado em `.env`:
  ```env
  FB_PIXEL_ID=seu_pixel_aqui
  FB_CAPI_ACCESS_TOKEN=seu_token_aqui
  ```

### ✅ Eventos Configurados

- [ ] Evento InitiateCheckout (quando usuário vê checkout)
- [ ] Evento Purchase (quando PIX é aprovado)
- [ ] Conversions API endpoint configurado

### ✅ Teste do Pixel

1. [ ] Instalar Facebook Pixel Helper (Chrome Extension)
2. [ ] Visitar seu domínio
3. [ ] Gerar PIX
4. [ ] Simular pagamento com webhook
5. [ ] Verificar se Purchase event aparece no Pixel Helper

---

## 🗄️ BANCO DE DADOS

### ✅ Backup

- [ ] Fazer backup completo antes de deploy
- [ ] Backup armazenado em local seguro
- [ ] Plano de rollback definido

### ✅ Migrations

- [ ] Todas migrations executadas localmente
- [ ] Nenhuma migration pendente
- [ ] Tabelas criadas com sucesso
- [ ] Dados existentes não foram apagados

### ✅ Dados Sensíveis

- [ ] Tokens não estão em código (usar .env)
- [ ] Senhas não estão em logs
- [ ] PIDs sensíveis não expostos em browser
- [ ] Dados do cliente criptografados se necessário

---

## 🔐 SEGURANÇA

### ✅ Autenticação

- [ ] Webhook valida origem (se necessário)
- [ ] API tokens não expostos em código
- [ ] Sessions seguras configuradas

### ✅ Rate Limiting

- [ ] Rate limit configurado para API
- [ ] Protege contra brute force
- [ ] Webhooks não sofrem rate limit

### ✅ CORS

- [ ] CORS configurado corretamente
- [ ] Frontend pode fazer requisições
- [ ] Origens permitidas configuradas

### ✅ Validação

- [ ] Entrada validada (nome, email, CPF)
- [ ] Valores monetários validados
- [ ] Nenhuma injeção de SQL possível

---

## 📝 LOGGING & MONITORING

### ✅ Logs

- [ ] Logging ativado
- [ ] Rotação de logs configurada
- [ ] Armazenamento suficiente para logs
- [ ] Limpeza periódica de logs antigos

### ✅ Monitoramento

- [ ] Uptime monitoring ativo
- [ ] Alertas para erros configurados
- [ ] Email de notificação para falhas
- [ ] Dashboard de monitoramento acessível

### ✅ Error Tracking

- [ ] Sentry ou similar configurado (opcional)
- [ ] Erros reportados automaticamente
- [ ] Stack traces capturados
- [ ] Alertas para erros críticos

---

## 🧪 TESTES PRÉ-PRODUÇÃO

### ✅ Testes Locais

```bash
# 1. Limpar e cachear
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan optimize

# 2. Rodar testes
php artisan test

# 3. Verificar erros
php artisan tinker
Order::count()  # Deve retornar número

# 4. Verificar logs
tail -f storage/logs/laravel.log
```

- [ ] Todos testes passando
- [ ] Nenhum erro no log

### ✅ Testes em Staging

1. [ ] Deploy para servidor de staging
2. [ ] Testar fluxo completo:
   - [ ] Gerar PIX
   - [ ] Modal abre
   - [ ] Timer funciona
   - [ ] Botão aparece em 30s
   - [ ] Blur effect funciona
3. [ ] Testar webhook:
   - [ ] Webhook recebido
   - [ ] Status atualizado
   - [ ] Redirecionamento funciona
4. [ ] Testar integração Facebook:
   - [ ] Pixel recebe eventos
   - [ ] Conversions API funciona
5. [ ] Testar responsividade mobile
6. [ ] Teste de performance/carga

### ✅ Teste de Webhook Real

```bash
# Simular pagamento aprovado
curl -X POST https://seu-dominio.com/api/pix/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "event": "payment.approved",
    "data": {
      "id": "PIX_PROD_TEST_123",
      "amount": 24.90,
      "status": "approved"
    }
  }'

# Verificar resposta
# Esperado: 200 OK com {"success": true}

# Verificar logs
grep "Payment approved" storage/logs/laravel.log
```

---

## 🚀 PLANO DE DEPLOY

### Fase 1: Preparação (1-2 horas antes)

1. [ ] Comunicar a todas partes interessadas
2. [ ] Backup completo do banco
3. [ ] Backup completo dos arquivos
4. [ ] Janela de manutenção agendada (off-peak)

### Fase 2: Deployment (5-10 minutos)

```bash
# 1. SSH no servidor
ssh usuario@seu-dominio.com

# 2. Pulld o código
cd /var/www/snaphubb-pages
git pull origin pages

# 3. Instalar dependências
composer install --no-dev --optimize-autoloader
npm install --production

# 4. Build assets
npm run build

# 5. Limpar caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# 6. Executar migrations (se houver)
php artisan migrate --force

# 7. Otimizar autoload
php artisan optimize

# 8. Restart PHP-FPM (se necessário)
sudo systemctl restart php-fpm
# ou
sudo /etc/init.d/php-fpm restart
```

### Fase 3: Validação (5-10 minutos)

1. [ ] Acessar site via HTTPS
2. [ ] Verificar página carrega sem erros
3. [ ] Testar geração de PIX
4. [ ] Verificar logs por erros
5. [ ] Testar webhook manualmente
6. [ ] Verificar Facebook Pixel
7. [ ] Testar em mobile

### Fase 4: Rollback (Se necessário)

```bash
# Reverter para última versão boa
git revert HEAD
git push origin pages

# Reexecutar steps de deployment
```

---

## ✅ CHECKLIST FINAL (NO DIA DO DEPLOY)

```
ITEM                                  | Check | Responsável | Data/Hora
──────────────────────────────────────────────────────────────────────
.env produção configurado             | [ ]   |             |
Pushing Pay token testado             | [ ]   |             |
Webhook configurado no dashboard      | [ ]   |             |
Webhook testado manualmente           | [ ]   |             |
Facebook Pixel ID configurado         | [ ]   |             |
SSL/HTTPS funcionando                 | [ ]   |             |
Banco de dados backup feito           | [ ]   |             |
Arquivos backup feito                 | [ ]   |             |
Migrations executadas                 | [ ]   |             |
Cache limpo                           | [ ]   |             |
Assets compilados                     | [ ]   |             |
Logs limpos                           | [ ]   |             |
Página carrega sem erros              | [ ]   |             |
PIX modal funciona                    | [ ]   |             |
Timer funciona                        | [ ]   |             |
Blur effect funciona                  | [ ]   |             |
Botão fallback aparece                | [ ]   |             |
Webhook recebido                      | [ ]   |             |
Redirecionamento funciona             | [ ]   |             |
Upsell page carrega                   | [ ]   |             |
Pixel recebe eventos                  | [ ]   |             |
Mobile responsividade OK              | [ ]   |             |
Sem erros em DevTools                 | [ ]   |             |
Todos logs verificados                | [ ]   |             |
Teste completo passou                 | [ ]   |             |
```

---

## 📞 CONTATOS PARA EMERGÊNCIAS

### Pushing Pay
- **Suporte Técnico**: contato@pushinpay.com.br
- **WhatsApp**: +55 11 5557-8038
- **Dashboard**: https://app.pushinpay.com.br

### Facebook
- **Pixel Setup**: https://business.facebook.com/
- **Conversions API**: https://developers.facebook.com/docs/conversions-api/

### Laravel/PHP
- **Laravel Docs**: https://laravel.com/docs
- **Stack Overflow**: `[laravel] [pix]`

---

## 📊 MÉTRICAS PÓS-DEPLOY

Monitorar nos primeiros 24 horas:

| Métrica | Meta | Atual |
|---------|------|-------|
| Uptime | > 99.9% | |
| Response Time | < 500ms | |
| Error Rate | < 1% | |
| PIX Success Rate | > 95% | |
| Webhook Success | 100% | |
| Pixel Events | 100% | |

---

## 🎯 SUCESSO CRITERIA

Deploy é considerado **SUCESSO** quando:

✅ Site está online e acessível  
✅ Nenhum erro 500 nos primeiros 24h  
✅ Pelo menos 1 pagamento PIX processado com sucesso  
✅ Webhook recebeu e processou notificação  
✅ Usuário foi redirecionado para upsell  
✅ Facebook Pixel registrou evento de Purchase  
✅ Todos os 15 testes passaram  

---

## 🔒 PÓS-DEPLOY

1. [ ] Monitorar logs por 24h
2. [ ] Responder a qualquer suporte/dúvida
3. [ ] Documentar qualquer issue encontrado
4. [ ] Comunicar sucesso aos stakeholders
5. [ ] Planejar melhorias futuras

---

**Gerado:** 25 de Novembro de 2025  
**Status:** ✅ **PRONTO PARA DEPLOY EM PRODUÇÃO**  
**Aprovado por:** _________________  
**Data de Aprovação:** _________________
