# 🧪 GUIA DE TESTES - FLUXO DE PAGAMENTO PIX

**Data:** 25 de Novembro de 2025  
**Objetivo:** Validar todos os cenários de pagamento PIX  
**Responsável:** QA / Desenvolvedor

---

## ✅ TESTE 1: Geração de QR Code

### Pré-requisitos
- [ ] Servidor rodando: `php artisan serve`
- [ ] Banco de dados conectado
- [ ] Token Pushing Pay configurado em `.env`

### Passos
1. Acesse http://127.0.0.1:8000
2. Preencha formulário:
   - Nome: "Teste User"
   - Email: "teste@test.com"
   - Celular: "11999999999"
   - CPF: "12345678901"
3. Selecione **PIX** como método de pagamento
4. Clique em **"Gerar PIX"**

### Resultado Esperado
- [ ] Modal PIX abre com animação
- [ ] QR code está visível e legível
- [ ] Campo de código PIX (copy-paste) aparece
- [ ] Botão "Copiar código" funciona
- [ ] Timer começa em 5:00
- [ ] Background tem blur effect
- [ ] Sem erros no console do navegador

### Logs para Verificar
```bash
tail -f storage/logs/laravel.log | grep -i "pix\|qr"
```

Esperado:
```
[INFO] PagePay: generatePixCode executado
[INFO] Pushing Pay response received
[INFO] PIX transaction created: PIX_XXXXX
```

---

## ✅ TESTE 2: Timer e Botão de Fallback

### Pré-requisitos
- [ ] Modal PIX aberto (veja Teste 1)

### Passos
1. Observe o timer começar em 5:00
2. Aguarde 30 segundos
3. Observe se botão "Ou pagar com Cartão" aparece

### Resultado Esperado
- [ ] Timer decrementa a cada segundo
- [ ] Formato correto (M:SS)
- [ ] Após 30 segundos, botão aparece **abaixo do QR code**
- [ ] Botão tem hover effect
- [ ] Clicando botão, modal fecha e formulário de cartão aparece

### Verificações Adicionais
- [ ] Timer continua contando corretamente
- [ ] Botão pode ser clicado
- [ ] Blur effect permanece até fechar modal

### Console
Esperado:
```
✅ Botão de cartão exibido após 30 segundos
Tempo restante: 4:30
```

---

## ✅ TESTE 3: Blur Effect no Background

### Pré-requisitos
- [ ] Modal PIX aberto

### Passos
1. Observe o fundo da página
2. Verifique se está desfocado/borrado
3. Clique no botão de fechar (×)
4. Verifique se blur desaparece

### Resultado Esperado
- [ ] Background está com blur visível quando modal aberto
- [ ] Blur desaparece quando modal fecha
- [ ] Modal em si fica nítido (sem blur)
- [ ] Efeito suave (não muito intenso)

### Browser DevTools
Verificar elemento:
```javascript
// Abrir console F12
document.body.classList.contains('pix-modal-open')  // true quando modal aberto
// Inspecionar elemento
#pix-modal-backdrop
// Deve ter: backdrop-filter: blur(4px)
```

---

## ✅ TESTE 4: Polling (Detecção de Pagamento - Fallback)

### Pré-requisitos
- [ ] Modal PIX aberto
- [ ] Webhook configurado (ou não, polling é fallback)

### Passos
1. Simular pagamento com curl:
```bash
curl -X POST http://127.0.0.1:8000/api/pix/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "event": "payment.approved",
    "data": {
      "id": "PIX_TEST_' $(date +%s) '",
      "amount": 24.90,
      "status": "approved"
    }
  }'
```

2. Aguarde até 5 segundos
3. Verifique redirecionamento

### Resultado Esperado
- [ ] Webhook retorna 200 OK
- [ ] Modal PIX fecha
- [ ] Redirecionamento para `/upsell/painel-das-garotas`
- [ ] Página de upsell carrega com sucesso

### Logs
```bash
grep "webhook received" storage/logs/laravel.log
grep "Payment approved" storage/logs/laravel.log
grep "REDIRECT" storage/logs/laravel.log
```

---

## ✅ TESTE 5: Timeout (Modal Fecha Após 5 Minutos)

### Pré-requisitos
- [ ] Modal PIX aberto
- [ ] Tempo disponível para aguardar (~5 min)

### Passos
1. Abra modal PIX
2. **Não pague** e deixe timer rodar
3. Aguarde timer chegar a 0:00

### Resultado Esperado
- [ ] Timer decrementa até 0:00
- [ ] Modal fecha automaticamente
- [ ] Background blur desaparece
- [ ] Usuário pode tentar novamente

### Alternativa (Teste Rápido)
Modificar `pixQRTimer = 300` para `pixQRTimer = 10` temporariamente para testar em 10 segundos.

---

## ✅ TESTE 6: Botão Fechar Modal

### Pré-requisitos
- [ ] Modal PIX aberto

### Passos
1. Clique no botão **×** (canto superior direito)
2. Verifique se modal fecha
3. Verifique se blur desaparece

### Resultado Esperado
- [ ] Modal fecha com animação
- [ ] Blur effect desaparece
- [ ] Página volta ao normal
- [ ] Usuário pode clicar em "Gerar PIX" novamente

---

## ✅ TESTE 7: Copy Button (Copiar Código PIX)

### Pré-requisitos
- [ ] Modal PIX aberto
- [ ] Código PIX visível

### Passos
1. Clique em botão **"Copiar código"** (ícone de cópia)
2. Cole em text editor (Ctrl+V)
3. Verifique se código foi copiado

### Resultado Esperado
- [ ] Botão muda de cor (feedback)
- [ ] Código PIX copiado para clipboard
- [ ] Código é válido (começa com 00020126)
- [ ] Comprimento correto (~150 caracteres)

### Verificação
```bash
# Cole em algum lugar para ver
# Código PIX válido do Banco Central tem este formato:
# 00020126580014br.gov.bcb.pix...
```

---

## ✅ TESTE 8: Responsividade em Mobile

### Pré-requisitos
- [ ] Servidor rodando
- [ ] Browser DevTools aberto (F12)

### Passos
1. Abra DevTools (F12)
2. Clique em "Toggle Device Toolbar" (Ctrl+Shift+M)
3. Teste em diferentes tamanhos:
   - [ ] iPhone SE (375px)
   - [ ] iPhone 12 (390px)
   - [ ] iPad (768px)
   - [ ] Desktop (1920px)

### Resultado Esperado para cada tamanho
- [ ] QR code redimensiona corretamente
- [ ] Modal está centralizado
- [ ] Texto legível
- [ ] Botões clicáveis (>44px height)
- [ ] Timer visível
- [ ] Sem scroll horizontal

### Tamanhos Responsivos Esperados
```css
QR Code:
- Mobile (< 640px): 24x24 px
- Tablet (640-768px): 28x28 px
- Medium (768-1024px): 40x40 px
- Desktop (> 1024px): 44x44 px
```

---

## ✅ TESTE 9: Falha no Pagamento (Webhook Declined)

### Pré-requisitos
- [ ] Modal PIX aberto
- [ ] Servidor rodando

### Passos
1. Simular pagamento recusado:
```bash
curl -X POST http://127.0.0.1:8000/api/pix/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "event": "payment.declined",
    "data": {
      "id": "PIX_DECLINED_' $(date +%s) '",
      "amount": 24.90,
      "status": "declined",
      "decline_reason": "Insufficient funds"
    }
  }'
```

2. Verifique comportamento

### Resultado Esperado
- [ ] Webhook retorna 200 OK
- [ ] Modal **NÃO fecha** (apenas recusou)
- [ ] Usuário pode:
  - [ ] Gerar novo PIX
  - [ ] Tentar novamente
  - [ ] Usar cartão
- [ ] Log registra evento

---

## ✅ TESTE 10: Integração Facebook Pixel

### Pré-requisitos
- [ ] Pixel ID configurado em `.env`
- [ ] Modal PIX aberto

### Passos
1. Abra Facebook Pixel Helper Chrome Extension
2. Simular pagamento aprovado com webhook
3. Verifique se Purchase event foi enviado

### Resultado Esperado
- [ ] Pixel Helper mostra evento "Purchase"
- [ ] Dados corretos:
  - [ ] Value: 24.90
  - [ ] Currency: BRL
  - [ ] Content Type: product
  - [ ] Email (hashed)
  - [ ] Event ID: transaction_id

### Logs
```bash
grep "Facebook Purchase event sent" storage/logs/laravel.log
```

---

## 🔴 TESTE 11: Tratamento de Erros

### Teste 11A: Token Inválido
1. Mudar `PP_ACCESS_TOKEN_PRODUCTION` para valor inválido
2. Tentar gerar PIX
3. Verificar erro apropriado é exibido

**Resultado:** Erro deve ser capturado e mostrado ao usuário

### Teste 11B: API Indisponível
1. Desativar conexão com internet
2. Tentar gerar PIX
3. Verificar timeout apropriado

**Resultado:** Timeout message mostra ao usuário

### Teste 11C: Webhook com Payload Inválido
```bash
curl -X POST http://127.0.0.1:8000/api/pix/webhook \
  -H "Content-Type: application/json" \
  -d '{"invalid": "data"}'
```

**Resultado:** Webhook retorna erro 400, sem quebrar sistema

---

## 📊 CHECKLIST FINAL DE TESTES

```
FUNCIONALIDADE                          | Status | Data | Tester
─────────────────────────────────────────────────────────────────
1. Geração de QR Code                  | [ ]    |      |
2. Timer Countdown                     | [ ]    |      |
3. Botão "Ou pagar com Cartão"         | [ ]    |      |
4. Blur Effect Background              | [ ]    |      |
5. Polling (Status Check)              | [ ]    |      |
6. Webhook Received (Real-time)        | [ ]    |      |
7. Redirecionamento Upsell             | [ ]    |      |
8. Copy Button (Código PIX)            | [ ]    |      |
9. Fechar Modal                        | [ ]    |      |
10. Timeout (5 minutos)                | [ ]    |      |
11. Mobile Responsiveness              | [ ]    |      |
12. Facebook Pixel Integration         | [ ]    |      |
13. Error Handling                     | [ ]    |      |
14. Payment Declined (Fallback)        | [ ]    |      |
15. Payment Canceled                   | [ ]    |      |
```

---

## 🎯 CASOS DE USO REAIS

### Cenário 1: Cliente Paga PIX com Sucesso
1. Cliente acessa /
2. Preenche dados
3. Seleciona PIX
4. Gera QR code
5. Escaneia com app bancário
6. Confirma pagamento
7. **[WEBHOOK]** Pushing Pay notifica
8. **[POLLING]** Sistema detecta em máx 5s
9. Redirect automático para upsell
10. ✅ Cliente vê oferta

### Cenário 2: Cliente Tira Print do QR Code
1. Cliente gera QR code
2. Tira screenshot
3. Paga depois em outro dispositivo
4. QR code ainda é válido por 5 minutos
5. ✅ Pagamento processado normalmente

### Cenário 3: Cliente Muda de Ideia (Switch para Cartão)
1. Cliente gera PIX
2. Aguarda 30 segundos
3. Vê botão "Ou pagar com Cartão"
4. Clica nele
5. Modal PIX fecha
6. Formulário de cartão aparece
7. ✅ Cliente continua normalmente

### Cenário 4: Cliente Esquece de Pagar (Timeout)
1. Cliente gera PIX
2. Não paga e deixa modal aberto
3. Timer chega a 0:00
4. Modal fecha automaticamente
5. **Timer message**: "PIX expirou"
6. Cliente pode gerar novo PIX
7. ✅ Sem travamento

### Cenário 5: Webhook Falha (Fallback ao Polling)
1. Webhook enviado por Pushing Pay
2. Servidor indisponível (error 500)
3. Pushing Pay retry automático
4. Enquanto isso, polling detecta em 5s
5. ✅ Sistema já processou antes de retry

---

## 📝 RELATÓRIO DE TESTE

Use este template para documentar:

```markdown
# Teste: [Nome do Teste]
**Data:** DD/MM/YYYY  
**Tester:** Seu Nome  
**Ambiente:** Local / Staging / Produção  
**Browser:** Chrome / Firefox / Safari  
**Dispositivo:** Desktop / Mobile

## Resultado
- [ ] ✅ PASSOU
- [ ] ❌ FALHOU
- [ ] ⏸️ BLOQUEADO

## Observações
[Descreva o que viu]

## Bugs Encontrados
- BUG #1: [Descrição]
  - Steps: [Como reproduzir]
  - Expected: [O que deveria acontecer]
  - Actual: [O que aconteceu]

## Screenshots
[Cole screenshot se necessário]

## Log Analysis
[Cole logs relevantes]
```

---

**Gerado:** 25 de Novembro de 2025  
**Status:** ✅ **PRONTO PARA TESTE EM PRODUÇÃO**
