#!/bin/bash

# Script de inicialização do Laravel
PROJECT_DIR="/e/ONBOSS DIGITAL/SNAPHUBB/snaphubb-pages"
PHP_PATH="/e/ARQUIVOS/php-8.3.29-nts-Win32-vs16-x64/php.exe"
PHP_INI="/e/ARQUIVOS/php-8.3.29-nts-Win32-vs16-x64/php_clean.ini"

echo "=== Iniciando projeto Laravel ==="

# Verificar se diretório existe
if [ ! -d "$PROJECT_DIR" ]; then
    echo "ERRO: Diretório do projeto não existe!"
    exit 1
fi

# Ir para diretório do projeto
cd "$PROJECT_DIR"

# Verificar se PHP funciona
echo "Testando PHP..."
if ! "$PHP_PATH" -v > /dev/null 2>&1; then
    echo "ERRO: PHP não está funcionando!"
    exit 1
fi

echo "PHP OK"

# Verificar se vendor existe
if [ ! -d "vendor" ]; then
    echo "Instalando dependências do Composer..."
    "$PHP_PATH" -c "$PHP_INI" /c/composer/composer install --no-interaction --ignore-platform-reqs
fi

# Verificar se node_modules existe
if [ ! -d "node_modules" ]; then
    echo "Instalando dependências do NPM..."
    npm install
fi

# Compilar assets se necessário
if [ ! -d "public/build" ]; then
    echo "Compilando assets..."
    npm run build
fi

# Verificar se .env existe
if [ ! -f ".env" ]; then
    echo "ERRO: Arquivo .env não existe!"
    exit 1
fi

echo "Iniciando servidor Laravel..."
"$PHP_PATH" -c "$PHP_INI" artisan serve --host=0.0.0.0 --port=8000