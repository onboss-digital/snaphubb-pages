# PIX Integration - Implementation Checklist

## ✅ Completed Tasks

### Frontend (PagePay.php)
- [x] Added PIX form fields (name, email, CPF, phone)
- [x] Added CPF validation algorithm with check digits
- [x] Created `preparePIXData()` function to synchronize with Stripe values
- [x] Implemented `sendPixToBackend()` method for secure HTTP communication
- [x] Updated `generatePixPayment()` to use unified data preparation
- [x] Added Http facade import
- [x] PIX UI only appears when language === 'br'

### Backend (PixController.php)
- [x] Created `create()` method with comprehensive validation
- [x] Implemented `isValidAmountForPlan()` for fraud prevention
  - Validates amount matches plan_key
  - Includes order bumps in calculation
  - 5% tolerance for currency conversion
  - Fetches from same API source as frontend
- [x] Implemented `isValidCpf()` with proper check digit algorithm
- [x] Created `buildPaymentDescription()` for formatted payment descriptions
- [x] Added logging for audit trail
- [x] Added error handling with appropriate HTTP status codes

### API Routes (routes/api.php)
- [x] Updated POST /api/pix/create → PixController@create
- [x] Maintained GET /api/pix/status/:payment_id

### Documentation
- [x] Created FLUXO_DADOS_PRECO.md (price data flow)
- [x] Created PIX_INTEGRATION_ARCHITECTURE.md (full architecture)
- [x] Created this checklist

### Git
- [x] Committed all changes (Hash: 1a9ff26)

---

## 🔄 In Progress / Testing Required

### 1. Test Local Integration
```bash
# Test preparePIXData() output format
cd "e:/ONBOSS DIGITAL/SNAPHUBB/snaphubb-pages"

# Verify preparePIXData() structure in PagePay
grep -n "preparePIXData" app/Livewire/PagePay.php

# Verify PixController validation logic
grep -n "isValidAmountForPlan" app/Http/Controllers/PixController.php
```

### 2. Test API Endpoint
```bash
# Test the POST /api/pix/create endpoint with valid data
curl -X POST http://localhost:8000/api/pix/create \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 2490,
    "currency_code": "BRL",
    "plan_key": "mensal",
    "offer_hash": "hash123",
    "customer": {
      "name": "João Silva",
      "email": "joao@example.com",
      "document": "12345678901",
      "phone_number": "11999999999"
    },
    "cart": [
      {
        "product_hash": "hash123",
        "title": "Plano Mensal",
        "price": 2490,
        "quantity": 1,
        "operation_type": 1
      }
    ]
  }'
```

### 3. Verify Price Synchronization
- [ ] Frontend calculates: $this->totals['final_price']
- [ ] preparePIXData() extracts same value
- [ ] sendPixToBackend() sends to /api/pix/create
- [ ] PixController validates against API prices
- [ ] Amount must match exactly (within 5% tolerance)

### 4. Test Error Scenarios
- [ ] Invalid CPF format
- [ ] Amount doesn't match plan
- [ ] Missing required fields
- [ ] Invalid email format
- [ ] API unreachable for validation
- [ ] Mercado Pago API errors

---

## ⏳ Pending Tasks

### 1. Database Schema for Transactions
```php
// Migration: create_pix_transactions_table.php
Schema::create('pix_transactions', function (Blueprint $table) {
    $table->id();
    $table->string('payment_id')->unique(); // Mercado Pago ID
    $table->string('plan_key');
    $table->integer('amount'); // in centavos
    $table->string('currency');
    $table->string('customer_email');
    $table->string('customer_name');
    $table->enum('status', ['pending', 'confirmed', 'expired', 'cancelled']);
    $table->timestamp('expires_at')->nullable();
    $table->timestamp('confirmed_at')->nullable();
    $table->json('metadata')->nullable();
    $table->string('qr_code');
    $table->text('qr_code_base64')->nullable();
    $table->timestamps();
    $table->index(['status', 'created_at']);
    $table->index(['customer_email']);
});
```

**Action**: Create migration file in `database/migrations/`

### 2. Webhook Handler for Mercado Pago
```php
// PixController.php - New method: webhook()
public function webhook(Request $request)
{
    // Validate webhook signature
    // Update transaction status
    // Trigger order confirmation if approved
    // Log payment confirmation
}
```

**Action**: Add webhook method to PixController

### 3. Update generatePixPayment() Response
- [ ] Store PIX data in database before returning to frontend
- [ ] Include transaction_id in response
- [ ] Set expiration time (typically 30 minutes for PIX)

### 4. Polling Status Check
- [ ] Implement real-time polling for PIX confirmation
- [ ] Update UI when payment is confirmed
- [ ] Auto-redirect to success page after confirmation

### 5. Add Order/Invoice Creation
- [ ] Create Order model linked to pix_transactions
- [ ] Generate invoice after PIX confirmation
- [ ] Send confirmation email to customer

### 6. Security Enhancements
- [ ] Add CSRF token validation in sendPixToBackend()
- [ ] Implement request signing/hmac for webhook verification
- [ ] Rate limiting on /api/pix/create endpoint
- [ ] IP whitelisting for Mercado Pago webhooks

### 7. Testing
- [ ] Unit tests for isValidAmountForPlan()
- [ ] Unit tests for isValidCpf()
- [ ] Integration tests for /api/pix/create endpoint
- [ ] E2E tests for complete PIX flow
- [ ] Test with different currencies (BRL, USD, EUR)
- [ ] Test with multiple order bumps active

### 8. Monitoring & Logging
- [ ] Setup error tracking (Sentry, etc)
- [ ] Create payment dashboard
- [ ] Monitor failed payment attempts
- [ ] Alert on webhook failures

---

## 📋 Environment Variables to Configure

Add to `.env`:
```env
# Mercado Pago
MP_ACCESS_TOKEN_SANDBOX=TEST_SANDBOX_TOKEN
MP_PUBLIC_KEY_SANDBOX=TEST_PUBLIC_KEY

# Plans API
PLANS_API_URL=https://snaphubb.com/api/get-plans

# PIX Configuration
PIX_EXPIRATION_MINUTES=30
PIX_WEBHOOK_SECRET=your_webhook_secret_key
```

---

## 🔗 Integration Points

### Frontend → Backend Flow
```
PagePay Component
  ↓
user clicks "Gerar PIX"
  ↓
generatePixPayment() validates fields
  ↓
preparePIXData() prepares synchronized data
  ↓
sendPixToBackend() sends to /api/pix/create
  ↓
PixController@create() validates & creates PIX
  ↓
Returns QR code to frontend
  ↓
User scans & pays
  ↓
Mercado Pago webhook notifies backend
  ↓
Database updated
  ↓
Frontend polling detects confirmation
  ↓
Success redirect
```

### Data Structure
```json
{
  "amount": 2490,
  "currency_code": "BRL",
  "plan_key": "mensal",
  "customer": {
    "name": "João Silva",
    "email": "joao@example.com",
    "document": "12345678901"
  },
  "cart": [
    {
      "product_hash": "hash123",
      "price": 2490,
      "operation_type": 1
    }
  ]
}
```

---

## 🚀 Next Steps Priority

1. **HIGH**: Create database migration for pix_transactions table
2. **HIGH**: Implement webhook handler for Mercado Pago confirmations
3. **HIGH**: Test end-to-end flow with real Mercado Pago sandbox
4. **MEDIUM**: Add Order model and invoice generation
5. **MEDIUM**: Implement frontend polling for confirmation
6. **MEDIUM**: Add comprehensive unit/integration tests
7. **LOW**: Performance optimization and caching
8. **LOW**: Analytics and monitoring dashboard

---

## 📞 Support & Debugging

### Common Issues

**Issue**: "Valor do pagamento não corresponde ao plano"
- **Cause**: Amount doesn't match API prices or bumps calculation
- **Fix**: Check cart items, ensure operation_type is correct (1=product, 2=bump)

**Issue**: CPF validation fails
- **Cause**: Invalid check digits or format
- **Fix**: Verify 11 digits and algorithm implementation

**Issue**: Webhook not received
- **Cause**: Mercado Pago can't reach your endpoint
- **Fix**: Check firewall, ensure URL is public and accessible

---

## 📚 Related Files

- `app/Livewire/PagePay.php` - Frontend component with preparePIXData()
- `app/Http/Controllers/PixController.php` - Backend validation and processing
- `routes/api.php` - API routes
- `PIX_INTEGRATION_ARCHITECTURE.md` - Detailed architecture
- `FLUXO_DADOS_PRECO.md` - Price flow documentation
