# 🔧 GUIA DE DEPLOY - PIX EM PRODUÇÃO

## ⚠️ PROBLEMA IDENTIFICADO

Em produção, está aparecendo:
- ❌ QR Code com interrogação (não é a imagem real)
- ❌ Código PIX começa com `SIMULATEDsim_` (modo teste)

**Causa:** O servidor de produção ainda está usando token vazio de Pushing Pay

---

## ✅ SOLUÇÃO

### **1. Verificar variáveis de ambiente no servidor**

SSH para o servidor de produção e execute:

```bash
cat .env | grep "PP_ACCESS"
cat .env | grep "ENVIRONMENT"
```

**Esperado:**
```
ENVIRONMENT=production
PP_ACCESS_TOKEN_PROD=55321|JaTW9wbkkKohC1cgIEyOLj1LhbQDwGg2zDAs3Iov67688d1b
PP_ACCESS_TOKEN_SANDBOX=
```

### **2. Se os valores estiverem vazios, atualize o .env**

```bash
# Backup
cp .env .env.backup

# Editar
nano .env

# Adicionar/atualizar as linhas:
ENVIRONMENT=production
PP_ACCESS_TOKEN_PROD=55321|JaTW9wbkkKohC1cgIEyOLj1LhbQDwGg2zDAs3Iov67688d1b
PP_ACCESS_TOKEN_SANDBOX=
```

### **3. Limpar todos os caches do Laravel**

```bash
# Remover caches de bootstrap
rm bootstrap/cache/config.php 2>/dev/null
rm bootstrap/cache/routes-v7.php 2>/dev/null

# Regenerar caches
php artisan config:cache
php artisan route:cache
php artisan cache:clear

# Ou tudo de uma vez
php artisan optimize:clear && php artisan optimize
```

### **4. Reiniciar o servidor**

```bash
# Se usando PHP-FPM
sudo systemctl restart php-fpm

# Se usando supervisor
sudo supervisorctl restart all

# Se usando Apache
sudo systemctl restart apache2

# Se usando Nginx
sudo systemctl restart nginx

# Se em cPanel/Plesk, reinicie via painel
```

### **5. Testar**

Execute o script de teste:

```bash
php test-pushing-pay-production.php
```

**Esperado:**
```
✓ PIX CRIADO COM SUCESSO EM PRODUÇÃO!
- Payment ID: a071a633-b6ab-48e8-bdfa-ffc8d8a6c453
- QR Code (base64): iVBORw0KGgoAAAANSUhEUgAA...
```

**Se ainda estiver em modo simulação:**
```
⚠️  MODO SIMULAÇÃO DETECTADO!
- Payment ID: sim_17640997748120
```

---

## 🧪 TESTE MANUAL NO NAVEGADOR

1. Abra: `https://pay.snaphubb.com/`
2. Selecione **PIX**
3. Preencha dados
4. Clique em **Gerar PIX**

**Deve aparecer:**
- ✅ QR Code real (não interrogação)
- ✅ Código PIX válido (29 dígitos, não `SIMULATED...`)
- ✅ Ao clicar "Copiar código", o texto muda para "✓ Código copiado!"
- ✅ Aparece mensagem "Entre em seu banco e realize o pagamento"

---

## 📋 CHECKLIST FINAL

- [ ] `.env` atualizado com token de produção
- [ ] `bootstrap/cache/config.php` deletado
- [ ] `bootstrap/cache/routes-v7.php` deletado
- [ ] `php artisan config:cache` executado
- [ ] `php artisan route:cache` executado
- [ ] Servidor/PHP reiniciado
- [ ] `test-pushing-pay-production.php` mostra modo produção
- [ ] QR Code aparece corretamente no navegador
- [ ] Código PIX mostra valores reais (não `SIMULATED...`)
- [ ] Copy-paste funciona (botão muda para "✓ Código copiado!")

---

## 🆘 SE AINDA NÃO FUNCIONAR

1. **Verifique os logs do servidor:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i "pushing\|pix"
   ```

2. **Confirme conectividade com Pushing Pay:**
   ```bash
   curl -I https://api.pushinpay.com.br/api
   ```

3. **Execute novo test:**
   ```bash
   php artisan tinker
   $service = app(\App\Services\PushingPayPixService::class);
   $service->createPixPayment(['amount' => 100])
   ```

4. **Contacte support@snaphubb.com com:**
   - Saída do `test-pushing-pay-production.php`
   - Últimas 50 linhas de `storage/logs/laravel.log`
