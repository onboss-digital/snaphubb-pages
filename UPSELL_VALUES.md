# 💰 Valores de Upsell - SnapHubb

## 📊 Estrutura de Preços

### **Produto Principal (Monthly)**
```json
{
  "label": "Acesso Mensal",
  "origin_price": 49.90,
  "descont_price": 24.90,
  "recurring": true
}
```

**Valores em centavos:**
- Origem: `4990` (R$ 49,90)
- Com desconto PIX: `2490` (R$ 24,90)
- **Desconto**: R$ 25,00

---

### **Upsell - Painel das Garotas**
```json
{
  "label": "Painel das garotas",
  "origin_price": 97.00,
  "descont_price": 37.00,
  "recurring": false
}
```

**Valores em centavos:**
- Origem: `9700` (R$ 97,00)
- Com desconto PIX: `3700` (R$ 37,00)
- **Desconto**: R$ 60,00
- **Tipo**: Não-recorrente (compra única)

---

## 🔗 Como o Upsell Funciona

### **Arquivo de Origem**
`resources/mock/get-plans.json`

### **Lógica de Carregamento**
**Arquivo**: `app/Livewire/UpsellOffer.php` linhas 23-47

```php
public function mount()
{
    $mockPath = resource_path('mock/get-plans.json');
    
    $this->product = [
        'hash' => 'painel_das_garotas',
        'label' => 'Painel das garotas',
        'price' => 3700,  // ← Valor padrão em centavos
        'currency' => 'BRL',
    ];

    if (file_exists($mockPath)) {
        // Lê o JSON e sobrescreve com valores reais
        $this->product['price'] = 
            (int)round($p['prices']['BRL']['descont_price'] * 100);
            // ↑ Multiplica por 100 para converter para centavos
    }
}
```

---

## 💳 Fluxo de Pagamento Upsell

```
1. Usuário aprovado no PIX (R$ 24,90)
   ↓
2. Redireciona para: /upsell/painel-das-garotas
   ↓
3. Componente UpsellOffer carrega
   ↓
4. Lê preço de painel_das_garotas do JSON
   ↓
5. Exibe oferta:
   - Origem: R$ 97,00
   - Desconto: R$ 37,00 (61% OFF)
   ↓
6. Se usuário clica "Aprovar":
   - Gera PIX para R$ 37,00
   - Mesmo fluxo de pagamento (5 min timer)
   ↓
7. Se aprovado:
   - Redireciona para /upsell/thank-you (sucesso)
   ↓
8. Se recusado:
   - Redireciona para /upsell/thank-you-recused
```

---

## 📝 Resumo dos Valores

| Produto | Origem | Com Desconto | Recurr. | Centavos |
|---------|--------|-------------|---------|----------|
| **Monthly** | R$ 49,90 | R$ 24,90 | ✅ Sim | `2490` |
| **Painel das Garotas** | R$ 97,00 | R$ 37,00 | ❌ Não | `3700` |

---

## 🔑 Onde Alterar os Valores

**Arquivo**: `resources/mock/get-plans.json`

```json
"painel_das_garotas": {
    "prices": {
        "BRL": {
            "origin_price": 97.00,        // ← Preço original
            "descont_price": 37.00,       // ← Preço com desconto (será * 100)
            "recurring": false
        }
    }
}
```

**Importante:**
- Os valores no JSON são em **reais** (com ponto)
- Internamente, o sistema converte para **centavos** (× 100)
- Ex: `37.00` → `3700` (R$ 37,00)

---

**Gerado**: 2025-11-24 21:38
