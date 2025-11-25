# 🚀 INSTRUÇÕES PARA PUSHING PAY EM PRODUÇÃO

## ⚠️ PROBLEMA IDENTIFICADO
Em produção, o PIX está mostrando modo SIMULADO: `SIMULATEDsim_...`

Isso significa que o token da Pushing Pay não está sendo lido.

---

## 🔧 SOLUÇÃO

### Opção 1: Limpar Cache e Reiniciar (RECOMENDADO)

Execute NO SERVIDOR DE PRODUÇÃO via SSH:

```bash
cd /caminho/do/snaphubb-pages

# Limpar todo cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear

# Deletar arquivo de cache se existir
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/routes-v7.php

# Recriar cache
php artisan config:cache
php artisan route:cache

# Reiniciar PHP (escolha uma opção)
systemctl restart php-fpm
# OU
systemctl restart php8.3-fpm
# OU
sudo service php-fpm restart
```

### Opção 2: Verificar Token no Servidor

Execute o comando que criamos:

```bash
php artisan pushing-pay:check-token
```

Este comando vai verificar:
- ✅ Se token está em `.env`
- ✅ Se está sendo lido pelo Laravel
- ✅ Se cache é o problema
- ✅ Se está em modo produção ou simulação

---

## 🔍 VERIFICAR NO .ENV DE PRODUÇÃO

O arquivo `.env` em produção DEVE ter:

```dotenv
PP_ACCESS_TOKEN_PROD=55321|JaTW9wbkkKohC1cgIEyOLj1LhbQDwGg2zDAs3Iov67688d1b
PP_ACCESS_TOKEN_SANDBOX=
ENVIRONMENT=production
```

**NÃO DEIXE O TOKEN VAZIO!**

---

## 📋 CHECKLIST

- [ ] Verificar se `.env` em produção tem `PP_ACCESS_TOKEN_PROD` preenchido
- [ ] Executar `php artisan pushing-pay:check-token`
- [ ] Limpar cache: `php artisan config:clear && php artisan cache:clear`
- [ ] Recriar cache: `php artisan config:cache`
- [ ] Reiniciar PHP-FPM: `systemctl restart php-fpm`
- [ ] Testar PIX novamente
- [ ] Verificar logs: `tail -100 storage/logs/laravel.log | grep -i "pushing\|token"`

---

## 📊 LOGS ESPERADOS

**Após correção, os logs devem mostrar:**

```
PushingPayPixService: ✅ Token de produção encontrado com XX caracteres
```

**NÃO deve aparecer:**

```
PushingPayPixService: ⚠️ Token de produção NÃO ENCONTRADO - usando SIMULAÇÃO
```

---

## 🆘 PROBLEMAS COMUNS

| Problema | Causa | Solução |
|----------|-------|---------|
| Ainda mostra `SIMULATED` | Token vazio em produção | Verificar `.env` |
| Token existe mas não funciona | Cache ativo | `php artisan config:clear` |
| Erro de permissão ao deletar cache | Permissões do servidor | `sudo chmod -R 777 bootstrap/cache` |
| Webhook não funciona | IP não autorizado | Configurar IP na Pushing Pay |

---

## 💡 DICAS

1. **Nunca coloque token no git** - Use `.env.example` como template
2. **Use variáveis de ambiente** - Não hardcode tokens
3. **Teste webhook** - Criar test order antes de usar em produção
4. **Monitore logs** - Sempre verificar `storage/logs/laravel.log`
5. **Configure cronjob** - Se usar polling, configure schedule

---

**Última atualização:** 25/11/2025
**Versão:** 1.0
