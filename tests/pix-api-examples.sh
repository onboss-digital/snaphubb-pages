#!/bin/bash
# PIX Mercado Pago - Exemplos de Requisições cURL
# Use este arquivo para testar a API PIX em ambiente de desenvolvimento

# ============================================
# VARIÁVEIS DE CONFIGURAÇÃO
# ============================================

BASE_URL="http://127.0.0.1:8000"
API_PREFIX="/api/pix"

# ============================================
# 1. CRIAR PAGAMENTO PIX
# ============================================
echo "📌 TESTE 1: Criar Pagamento PIX"
echo "===================================="
echo ""
echo "Requisição:"
echo ""

curl -X POST "${BASE_URL}${API_PREFIX}/create" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 2490,
    "description": "Assinatura Premium - Plano Mensal",
    "customer_email": "usuario@example.com",
    "customer_name": "João Silva"
  }' \
  -w "\nStatus HTTP: %{http_code}\n\n"

echo ""
echo "Resposta esperada (sucesso 201):"
echo ""
echo '{
  "status": "success",
  "data": {
    "payment_id": 1234567890,
    "qr_code_base64": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAATUAAAE1...",
    "qr_code": "00020126360014br.gov.bcb.pix0136123e4567-e12b-12d1-a456-426614174000520400005303986540510.00520004000055D4E62330063047CD9",
    "expiration_date": "2025-11-16T15:30:00Z",
    "amount": 24.90,
    "status": "pending"
  }
}'
echo ""
echo ""

# ============================================
# 2. CONSULTAR STATUS DO PAGAMENTO
# ============================================
echo "📌 TESTE 2: Consultar Status do Pagamento"
echo "========================================="
echo ""
echo "Requisição:"
echo ""

# Use o payment_id retornado na resposta anterior
PAYMENT_ID="1234567890"

curl -X GET "${BASE_URL}${API_PREFIX}/status/${PAYMENT_ID}" \
  -H "Content-Type: application/json" \
  -w "\nStatus HTTP: %{http_code}\n\n"

echo ""
echo "Resposta esperada (sucesso 200):"
echo ""
echo '{
  "status": "success",
  "data": {
    "payment_id": 1234567890,
    "payment_status": "approved",
    "status_detail": null,
    "amount": 24.90
  }
}'
echo ""
echo ""

# ============================================
# 3. TESTE COM DADOS INVÁLIDOS
# ============================================
echo "📌 TESTE 3: Criar PIX com Dados Inválidos"
echo "========================================="
echo ""
echo "Requisição:"
echo ""

curl -X POST "${BASE_URL}${API_PREFIX}/create" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": -100,
    "description": "Teste",
    "customer_email": "email-invalido",
    "customer_name": ""
  }' \
  -w "\nStatus HTTP: %{http_code}\n\n"

echo ""
echo "Resposta esperada (erro 422):"
echo ""
echo '{
  "status": "error",
  "message": "Dados inválidos.",
  "errors": {
    "amount": ["O valor deve ser maior que zero"],
    "customer_email": ["O email deve ser válido"],
    "customer_name": ["O name field is required"]
  }
}'
echo ""
echo ""

# ============================================
# 4. TESTE COM PAYMENT_ID INVÁLIDO
# ============================================
echo "📌 TESTE 4: Consultar Status com ID Inválido"
echo "==========================================="
echo ""
echo "Requisição:"
echo ""

curl -X GET "${BASE_URL}${API_PREFIX}/status/9999999999" \
  -H "Content-Type: application/json" \
  -w "\nStatus HTTP: %{http_code}\n\n"

echo ""
echo "Resposta esperada (erro 400):"
echo ""
echo '{
  "status": "error",
  "message": "Pagamento não encontrado."
}'
echo ""
echo ""

# ============================================
# DICAS DE TESTE
# ============================================
echo "💡 DICAS PARA TESTES MANUAIS:"
echo "============================"
echo ""
echo "1. Use Postman ou Insomnia para testes mais fáceis"
echo ""
echo "2. Monitor dos logs em tempo real:"
echo "   tail -f storage/logs/payment_checkout.log"
echo ""
echo "3. Verifique o banco de dados:"
echo "   php artisan tinker"
echo "   >>> DB::table('orders')->latest()->first()"
echo ""
echo "4. Limpe cache se houver problemas:"
echo "   php artisan cache:clear"
echo "   php artisan config:clear"
echo ""
echo "5. Teste com curl simples:"
echo "   curl -X GET http://127.0.0.1:8000/api/pix/status/123"
echo ""
echo ""

# ============================================
# ESTADOS DE PAGAMENTO
# ============================================
echo "📊 ESTADOS DE PAGAMENTO:"
echo "======================="
echo ""
echo "pending   → Aguardando pagamento"
echo "approved  → Pagamento aprovado ✅"
echo "rejected  → Pagamento rejeitado ❌"
echo "cancelled → Pagamento cancelado ❌"
echo "expired   → PIX expirou ⏰"
echo ""
echo ""
