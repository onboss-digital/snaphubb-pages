# Checklist de Testes - PIX com Sincronização Stripe

## ✅ Testes de Funcionalidade

### Teste 1: Modal PIX Aparece Apenas em PT-BR
- [ ] Acessar site em Português (BR)
  - Esperado: Card "PIX" aparece no seletor de forma de pagamento
- [ ] Acessar site em Inglês
  - Esperado: Card "PIX" NÃO aparece
- [ ] Acessar site em Espanhol
  - Esperado: Card "PIX" NÃO aparece

### Teste 2: Validação Frontend (Obrigatoriedade)
- [ ] Clicar "Gerar PIX" sem preencher nada
  - Esperado: Modal de erro mostra "Nome Completo é obrigatório"
- [ ] Preencher nome, clicar "Gerar PIX" sem email
  - Esperado: Modal de erro mostra "E-mail é obrigatório"
- [ ] Preencher nome + email, clicar "Gerar PIX" sem CPF
  - Esperado: Modal de erro mostra "CPF é obrigatório"

### Teste 3: Validação Frontend (Formato)
- [ ] Email: "invalido@" (falta domínio)
  - Esperado: Erro "E-mail é obrigatório"
- [ ] CPF: "123.456.789-00" (11 dígitos mas algoritmo inválido)
  - Esperado: Erro "CPF é obrigatório"
- [ ] CPF: "11111111111" (todos iguais)
  - Esperado: Erro "CPF é obrigatório"

### Teste 4: Sincronização de Valor
**Cenário 1: Apenas plano**
- [ ] Selecionar Plano Mensal (R$ 19,90)
- [ ] Modal Stripe mostra: "Total: R$ 19,90"
- [ ] Clicar "Gerar PIX" → Backend recebe amount: 1990 (centavos)
- [ ] ✅ VALOR SINCRONIZADO

**Cenário 2: Plano + 1 Bump**
- [ ] Selecionar Plano Mensal (R$ 19,90)
- [ ] Ativar Bump 1 (+R$ 9,90)
- [ ] Modal Stripe mostra: "Total: R$ 29,80"
- [ ] Clicar "Gerar PIX" → Backend recebe amount: 2980
- [ ] ✅ VALOR SINCRONIZADO COM BUMP

**Cenário 3: Plano + 2 Bumps**
- [ ] Selecionar Plano Semi-Anual (R$ 99,00)
- [ ] Ativar Bump 1 (+R$ 9,90) e Bump 2 (+R$ 19,90)
- [ ] Modal Stripe mostra: "Total: R$ 128,80"
- [ ] Clicar "Gerar PIX" → Backend recebe amount: 12880
- [ ] ✅ VALOR SINCRONIZADO COM 2 BUMPS

### Teste 5: Geração de PIX com Sucesso
- [ ] Preencher dados válidos
- [ ] Clicar "Gerar PIX"
- [ ] Esperado:
  - [ ] Mostra modal "Processando Pagamento"
  - [ ] Modal PIX aparece com:
    - [ ] QR Code (imagem)
    - [ ] Código PIX em texto
    - [ ] Valor (R$ X,XX)
    - [ ] Status: "Aguardando Pagamento"
    - [ ] Botão "Copiar Código"

---

## 🔒 Testes de Segurança

### Teste 6: Validação de Integridade (Backend)
**Pré-requisito:** Abrir DevTools (F12) → Network tab

**Cenário: Value Tampering (falsificar valor)**
- [ ] Preencher dados válidos (Plano Mensal = R$ 19,90)
- [ ] Abrir DevTools → Network
- [ ] Clicar "Gerar PIX"
- [ ] Interceptar POST /api/pix/create
- [ ] Alterar: `"amount": 1990` → `"amount": 100` (tentar pagar R$ 1,00)
- [ ] Permitir requisição
- [ ] Esperado:
  - [ ] Response: 422 Unprocessable Entity
  - [ ] Mensagem: "Valor do pagamento não corresponde ao plano selecionado"
  - [ ] Modal de erro mostra mensagem
  - [ ] Backend loga: "Tentativa de pagamento com valor inválido"

### Teste 7: Validação de CPF (Dígitos Verificadores)
- [ ] CPF válido: "123.456.789-09" (exemplo com dígitos corretos)
  - Esperado: Aceita
- [ ] CPF inválido: "123.456.789-08" (dígito verificador errado)
  - Esperado: Erro "CPF é obrigatório"
- [ ] CPF inválido: "000.000.000-00"
  - Esperado: Erro "CPF é obrigatório"

### Teste 8: Validação do Plan Key
**Pré-requisito:** DevTools aberto

- [ ] Preencher dados válidos (Plano: "monthly")
- [ ] Interceptar: POST /api/pix/create
- [ ] Alterar: `"plan_key": "monthly"` → `"plan_key": "invalid_plan"`
- [ ] Esperado:
  - [ ] Response: 422 Unprocessable Entity
  - [ ] Mensagem: "Valor do pagamento não corresponde ao plano selecionado"

### Teste 9: Validação de Email
- [ ] Email: "teste@" (falta domínio)
  - Esperado: Erro frontend
- [ ] Email: "teste@dominio" (falta TLD)
  - Esperado: Erro frontend ou aceita (depende filter_var)
- [ ] Email vazio: ""
  - Esperado: Erro "E-mail é obrigatório"

### Teste 10: Logs de Auditoria
**Pré-requisito:** Acesso a storage/logs/

- [ ] Fazer pagamento com sucesso
  - [ ] storage/logs/laravel.log contém:
    ```
    [INFO] PIX criado com sucesso
    payment_id: 123456789
    amount: 1990
    customer_email: teste@email.com
    plan_key: monthly
    ```

- [ ] Tentar value tampering
  - [ ] storage/logs/laravel.log contém:
    ```
    [WARNING] Tentativa de pagamento com valor inválido
    plan_key: monthly
    amount: 100
    ip: 192.168.1.100
    ```

---

## 🌍 Testes de Moeda e Planos

### Teste 11: BRL (Padrão)
- [ ] Selecionar: Moeda BRL, Plano Mensal
- [ ] Esperado:
  - [ ] Frontend mostra: "R$ 19,90"
  - [ ] Backend recebe: "currency_code": "BRL"
  - [ ] Valor: 1990 centavos

### Teste 12: USD
- [ ] Selecionar: Moeda USD, Plano Mensal
- [ ] Esperado:
  - [ ] Frontend mostra: "$19.90"
  - [ ] Backend recebe: "currency_code": "USD"
  - [ ] Valor convertido em centavos

### Teste 13: EUR
- [ ] Selecionar: Moeda EUR, Plano Mensal
- [ ] Esperado:
  - [ ] Frontend mostra: "€19,90"
  - [ ] Backend recebe: "currency_code": "EUR"
  - [ ] Valor convertido em centavos

### Teste 14: Todos os Planos
- [ ] Plano Mensal
- [ ] Plano Trimestral
- [ ] Plano Semi-Anual
- [ ] Plano Anual
- [ ] Esperado: Cada um gera PIX com valor correto

---

## 📱 Testes de UX

### Teste 15: Modal de Processamento
- [ ] Clicar "Gerar PIX"
- [ ] Esperado:
  - [ ] Modal "Processando Pagamento" aparece
  - [ ] Spinner/Loading visual
  - [ ] Botão "Gerar PIX" desabilita
  - [ ] Após sucesso: desaparece automaticamente

### Teste 16: Modal de Erro
- [ ] Preencher dados inválidos (ex: CPF="123")
- [ ] Clicar "Gerar PIX"
- [ ] Esperado:
  - [ ] Modal vermelha com ícone de erro
  - [ ] Mensagem clara em português
  - [ ] Botão "Fechar"
  - [ ] Formulário ainda preenchido (não limpa)

### Teste 17: Modal de Sucesso (PIX Gerado)
- [ ] Gerar PIX com sucesso
- [ ] Esperado:
  - [ ] QR Code em alta qualidade
  - [ ] Código PIX copiável
  - [ ] Botão "Copiar Código" funciona
  - [ ] Mostra valor final
  - [ ] Status "Aguardando Pagamento"

### Teste 18: Responsividade
- [ ] Abrir em Mobile (375px)
  - [ ] Modal PIX ocupa 90% da tela
  - [ ] QR Code redimensiona
  - [ ] Texto legível
- [ ] Abrir em Tablet (768px)
  - [ ] Layout adaptado
- [ ] Abrir em Desktop (1920px)
  - [ ] Centralizado
  - [ ] Bem dimensionado

---

## 🔧 Testes de Integração

### Teste 19: Fluxo Completo (Simulado)
**Pré-requisito:** Credenciais Mercado Pago Sandbox ativas

1. [ ] Selecionar plano e bumps
2. [ ] Preencher dados PIX
3. [ ] Clicar "Gerar PIX"
4. [ ] Validar modal de PIX gerado
5. [ ] Copiar código
6. [ ] ✅ Fluxo completo funciona

### Teste 20: Dados Enviados Corretamente
**Pré-requisito:** DevTools aberto, Network tab ativa

- [ ] Interceptar POST /api/pix/create
- [ ] Validar JSON enviado:
  ```json
  {
    "amount": 1990,
    "currency_code": "BRL",
    "plan_key": "monthly",
    "offer_hash": "...",
    "customer": {
      "name": "João Silva",
      "email": "joao@email.com",
      "phone_number": "+55 11 98765-4321",
      "document": "12345678909"
    },
    "cart": [...],
    "metadata": {...}
  }
  ```
- [ ] Todos os campos presentes
- [ ] Valores corretos

---

## 🚨 Testes de Erro

### Teste 21: API de Plans Indisponível
**Simular:** Desativar internet ou mockar erro na API

- [ ] Tentar gerar PIX
- [ ] Esperado:
  - [ ] Response: 422 ou 500
  - [ ] Mensagem: "Erro ao processar pagamento"
  - [ ] Log: "Erro ao buscar planos para validação"

### Teste 22: Mercado Pago Indisponível
**Simular:** Credenciais inválidas ou serviço down

- [ ] Tentar gerar PIX
- [ ] Esperado:
  - [ ] Response: 500
  - [ ] Mensagem: "Erro ao gerar código PIX"
  - [ ] Log: "Erro ao criar PIX no Mercado Pago"

### Teste 23: Timeout na API
**Simular:** Slow network ou serviço lento

- [ ] Backend timeout: 10 segundos
- [ ] Esperado:
  - [ ] Mensagem: "Erro ao processar pagamento"
  - [ ] Log: Erro capturado

---

## 📊 Testes de Performance

### Teste 24: Tempo de Resposta
- [ ] Gerar PIX
- [ ] Esperado:
  - [ ] Response < 2 segundos (em condições normais)
  - [ ] < 5 segundos (em condições lentas)

### Teste 25: Requisições Simultâneas
**Simular:** Múltiplos usuários ao mesmo tempo

- [ ] 10 usuários gerando PIX ao mesmo tempo
- [ ] Esperado:
  - [ ] Todos recebem response válido
  - [ ] Sem colisões de payment_id
  - [ ] Todos os logs registrados

---

## 🔐 Testes de Conformidade

### Teste 26: GDPR (Dados Pessoais)
- [ ] Dados sensíveis (CPF, Email) não são logados completos
- [ ] Apenas CPF últimos 2 dígitos (ex: ***78909) em logs públicos
- [ ] Email: não logar em response direto

### Teste 27: PCI Compliance
- [ ] Card data: nunca toca no backend ✅ (Stripe cuida)
- [ ] CPF: validado e logado (conforme lei)
- [ ] Senhas: nunca solicitadas ✅

---

## 📋 Resumo de Testes

| Categoria | Total | Críticos | Status |
|-----------|-------|----------|--------|
| Funcionalidade | 5 | 5 | 🔴 Não Testado |
| Segurança | 5 | 5 | 🔴 Não Testado |
| Moeda/Planos | 4 | 2 | 🔴 Não Testado |
| UX | 4 | 2 | 🔴 Não Testado |
| Integração | 2 | 2 | 🔴 Não Testado |
| Erro | 3 | 3 | 🔴 Não Testado |
| Performance | 2 | 1 | 🔴 Não Testado |
| Conformidade | 2 | 1 | 🔴 Não Testado |
| **TOTAL** | **27** | **21** | **🔴 PENDENTE** |

---

## 🎯 Prioridade de Testes

### 🔴 CRÍTICOS (Testar Primeiro)
1. Teste 1: Modal PIX apenas em BR ✅
2. Teste 4: Sincronização de valor ✅
3. Teste 5: Geração de PIX ✅
4. Teste 6: Value tampering ✅
5. Teste 7: Validação de CPF ✅

### 🟠 IMPORTANTES (Testar Antes do Deploy)
6. Teste 2: Validação obrigatoriedade
7. Teste 19: Fluxo completo
8. Teste 20: Dados enviados
9. Teste 24: Performance

### 🟡 RECOMENDADOS (Testar Depois)
10. Teste 11-14: Moedas
11. Teste 15-18: UX
12. Teste 21-23: Erros
13. Teste 26-27: Conformidade

---

## 🏁 Critério de Sucesso

**Projeto pode fazer MERGE quando:**
- [ ] Todos os testes críticos passam (5/5)
- [ ] Nenhum value tampering consegue passar
- [ ] CPF valida corretamente
- [ ] PIX gerado com sucesso 3x seguidas
- [ ] Não há erros no console (browser ou server)
- [ ] Logs registram auditoria completa

**Projeto pode fazer DEPLOY quando:**
- [ ] Todos testes críticos + importantes passam (9/9)
- [ ] Performance < 2 segundos
- [ ] Nenhuma exceção não capturada
- [ ] Mercado Pago sandbox testado
- [ ] QA aprova funcionalidade

---

**Criado:** 16 de Novembro de 2025  
**Versão:** 1.0  
**Status:** Pronto para Testes  
**Responsável:** QA Team
