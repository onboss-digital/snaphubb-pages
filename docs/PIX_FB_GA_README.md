# Configuração de Pixel (Facebook) e GA4 para produção

Este documento descreve o passo-a-passo para configurar o Pixel do Facebook (client + CAPI) e o GA4 no ambiente de produção para o projeto `snaphubb-pages`.

Objetivo
- Garantir que eventos de compra (`Purchase`) sejam enviados ao Facebook e GA4 somente quando a transação for aprovada.
- Configurar client-side (fbq / gtag) e server-side (Facebook Conversions API) com deduplicação por `event_id`.

Arquitetura resumida
- Client-side: o código do checkout já injeta `fbq('Purchase', params, {eventID})` quando o Livewire emite `checkout-success`.
- Server-side (CAPI): a aplicação envia `Purchase` via `FacebookConversionsService` nas seguintes ocasiões:
  - Síncrono: quando o gateway retorna `success` dentro de `PagePay::sendCheckout()` (cartões tratados sincronamente)
  - Assíncrono: via webhooks quando o gateway confirma o pagamento (implementado para Mercado Pago e Stripe)
- Deduplicação: ambos (client e server) usam o mesmo `event_id` (ex.: `transaction_id` do Mercado Pago ou `payment_intent.id` do Stripe). O Facebook deduplica automaticamente.

Variáveis de ambiente necessárias (production .env)
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://pay.snaphubb.com`
- `FB_CAPI_ACCESS_TOKEN` = Token do Facebook Conversions API (obrigatório)
- `FB_PIXEL_IDS` = Lista de Pixel IDs separados por vírgula (ou `FB_PIXEL_ID` para 1 pixel)
- `GA_MEASUREMENT_ID` = ID do GA4 (ex.: `G-XXXXXXXXXX`)
- `MERCADOPAGO_ACCESS_TOKEN` = token do Mercado Pago (sandbox ou produção)
- `MERCADOPAGO_NOTIFICATION_URL` = `https://SEU_DOMINIO/api/webhook/mercadopago`
- `STRIPE_API_SECRET_KEY` = secret da Stripe
- `STRIPE_WEBHOOK_SECRET` = (opcional, recomendado) secret do endpoint webhook Stripe

Rotas e controllers que foram adicionados
- `POST /api/webhook/mercadopago` → `MercadoPagoWebhookController@handle` (dispara CAPI quando o PIX for aprovado)
- `POST /api/webhook/stripe` → `StripeWebhookController@handle` (dispara CAPI quando `payment_intent.succeeded` ou `charge.succeeded`)

Recomendações de segurança
- Configure webhook URLs com HTTPS e, sempre que possível, habilite a verificação de assinatura:
  - Mercado Pago: use as configurações do painel (e valide payload/assinatura se disponível).
  - Stripe: configure `STRIPE_WEBHOOK_SECRET` no `.env` e o controller fará a verificação (se presente).

Passo-a-passo de deploy rápido (servidor)
1. Atualize o código no servidor (git pull):
```bash
cd /var/www/snaphubb-pages
git pull origin master
```
2. instalar dependências e build de assets:
```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```
3. Atualize `.env` com as variáveis listadas acima.
4. Otimize/limpe caches e reinicie filas:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```
5. Reinicie processos (php-fpm, supervisor) se aplicável.

Testes (local / staging) antes de produção
- Mercado Pago (sandbox): use `ngrok` para expor sua URL local e configure `MERCADOPAGO_NOTIFICATION_URL` apontando para `https://<ngrok-id>.ngrok.io/api/webhook/mercadopago`.
- Stripe (local): use o `stripe-cli` para encaminhar eventos:
```bash
stripe login
stripe listen --forward-to localhost:8000/api/webhook/stripe
# Simule um evento
stripe trigger payment_intent.succeeded
```
- No browser: abra a página de checkout e conclua um pagamento de teste (cartão/sandbox). Verifique em Events Manager → Test Events.

Verificação no Facebook Events Manager
- Vá em Events Manager → Test Events. Você deve ver dois sinais (dependendo do fluxo):
  - Sinal client-side (Browser) — `fbq` emitido pelo Livewire quando `checkout-success` ocorrer.
  - Sinal server-side (CAPI) — enviado pelo backend quando o pagamento for confirmado (webhook ou resposta síncrona).
- Ambos devem ter o mesmo `event_id` (ex.: `payment_intent.id` ou `mp_payment_id`) para que o Facebook deduplique.

Como a "página Obrigado" é tratada
- O evento server-side é enviado independentemente de qual página o cliente esteja (por webhook).
- A página “Obrigado” por si só não dispara automaticamente um evento se o sistema já enviou o evento via CAPI. Se quiser garantir que o cliente também envie `fbq('Purchase')` ao carregar a página, implemente a leitura de sessão/DB para obter `transaction_id` e dispare `fbq` apenas se o client-side não tiver sido executado (recomendado evitar dupla contagem).

Logs e troubleshooting
- Verifique `storage/logs/laravel.log` para entradas do `FacebookConversionsService` e para logs dos webhooks.
- Erros comuns: falta de `FB_CAPI_ACCESS_TOKEN`, `FB_PIXEL_IDS` vazios, webhook sem HTTPS, Stripe webhook sem secret configurado.

Perguntas frequentes
- "O evento será disparado somente quando a transação for aprovada?" — Sim. O servidor envia CAPI apenas após confirmação (webhook para Mercado Pago/Stripe ou resposta síncrona do gateway).
- "Preciso colocar o Pixel manualmente em cada página?" — Não. O Pixel é injetado centralmente via `resources/views/partials/analytics.blade.php` e os eventos server-side são enviados pelo backend.

Se quiser, eu posso:
- Adicionar persistência no banco (`fb_capi_sent_at`) para auditoria e controle mais robusto de idempotência.
- Atualizar a página “Obrigado” para disparar `fbq('Purchase')` somente quando apropriado (leitura de sessão/DB).

---
Arquivo gerado automaticamente pelo assistente — se precisar de ajustes no texto (nome de domínios, tokens, exemplos) eu adapto.
