<?php

namespace App\Http\Controllers;

use App\Services\MercadoPagoPixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;

/**
 * Controller para gerenciar pagamentos PIX
 * Endpoints para criar pagamentos e consultar status
 */
class PixController extends Controller
{
    private MercadoPagoPixService $pixService;

    public function __construct(MercadoPagoPixService $pixService)
    {
        $this->pixService = $pixService;
    }

    /**
     * POST /api/pix/create
     * Cria um novo pagamento PIX com validação de integridade
     */
    public function create(Request $request): JsonResponse
    {
        try {
            // 1. VALIDAR DADOS OBRIGATÓRIOS DO FRONTEND
            $validated = $request->validate([
                'amount' => 'required|integer|min:50', // Mínimo 0.50 em centavos
                'currency_code' => 'required|in:BRL,USD,EUR',
                'plan_key' => 'required|string',
                'offer_hash' => 'required|string',
                'device_id' => 'nullable|string', // Device ID do SDK MercadoPago.JS V2
                'customer' => 'required|array',
                'customer.name' => 'required|string|min:3',
                'customer.email' => 'required|email',
                'customer.phone_number' => 'nullable|string',
                'customer.document' => 'nullable|string',
                'customer.address' => 'nullable|array', // Endereço do cliente
                'customer.address.street' => 'nullable|string',
                'customer.address.number' => 'nullable|string',
                'customer.address.zip' => 'nullable|string',
                'customer.address.city' => 'nullable|string',
                'customer.address.state' => 'nullable|string',
                'cart' => 'required|array|min:1',
                'cart.*.category_id' => 'nullable|string', // Categoria do item
                'cart.*.description' => 'nullable|string', // Descrição do item
                'metadata' => 'nullable|array',
            ]);

            // 2. VALIDAR INTEGRIDADE DA TRANSAÇÃO
            // SEGURANÇA: Verificar que o amount corresponde ao plan_key solicitado
            $validAmount = $this->isValidAmountForPlan(
                $validated['plan_key'],
                $validated['amount'],
                $validated['currency_code'],
                $validated['cart']
            );

            if (!$validAmount) {
                Log::warning('Tentativa de pagamento com valor inválido', [
                    'plan_key' => $validated['plan_key'],
                    'amount' => $validated['amount'],
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Valor do pagamento não corresponde ao plano selecionado',
                ], 422);
            }

            // 3. VALIDAR DOCUMENTO (CPF) SE FORNECIDO
            if (!empty($validated['customer']['document'])) {
                if (!$this->isValidCpf($validated['customer']['document'])) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'CPF inválido',
                    ], 422);
                }
            }

            // 4. CRIAR DESCRIÇÃO DO PAGAMENTO
            $description = $this->buildPaymentDescription(
                $validated['plan_key'],
                $validated['customer']['name']
            );

            // 5. CHAMAR MERCADO PAGO PARA GERAR PIX
            $pixPaymentData = [
                'amount' => (int) $validated['amount'],
                'description' => $description,
                'customerName' => $validated['customer']['name'],
                'customerEmail' => $validated['customer']['email'],
                'customerPhone' => $validated['customer']['phone_number'] ?? null,
                'customerDocument' => $validated['customer']['document'] ?? null,
                'customerAddress' => $validated['customer']['address'] ?? null,
                'device_id' => $validated['device_id'] ?? null,
                'external_reference' => $validated['offer_hash'] ?? null,
                'cart' => $validated['cart'] ?? [],
            ];

            $pixResponse = $this->pixService->createPixPayment($pixPaymentData);

            if ($pixResponse['status'] !== 'success') {
                Log::error('Erro ao criar PIX no Mercado Pago', [
                    'response' => $pixResponse,
                    'customer_email' => $validated['customer']['email'],
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => $pixResponse['message'] ?? 'Erro ao gerar código PIX',
                ], 500);
            }

            // 6. LOG PARA AUDITORIA
            Log::info('PIX criado com sucesso', [
                'payment_id' => $pixResponse['data']['payment_id'] ?? null,
                'amount' => $validated['amount'],
                'customer_email' => $validated['customer']['email'],
                'plan_key' => $validated['plan_key'],
            ]);

            // 7. RETORNAR DADOS DO PIX PARA O FRONTEND
            return response()->json([
                'status' => 'success',
                'data' => [
                    'payment_id' => $pixResponse['data']['payment_id'] ?? null,
                    'qr_code_base64' => $pixResponse['data']['qr_code_base64'] ?? null,
                    'qr_code' => $pixResponse['data']['qr_code'] ?? null,
                    'amount' => $validated['amount'],
                    'expiration_date' => $pixResponse['data']['expiration_date'] ?? null,
                    'status' => $pixResponse['data']['status'] ?? 'pending',
                ],
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::warning('Validação falhou ao criar PIX', [
                'errors' => $e->errors(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Dados de validação inválidos',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            Log::error('Erro ao criar pagamento PIX', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao processar pagamento',
            ], 500);
        }
    }

    /**
     * SEGURANÇA: Valida que o valor do pagamento corresponde ao plano
     * Previne tentativas de alterar valores no frontend
     */
    private function isValidAmountForPlan(
        string $planKey,
        int $amount,
        string $currencyCode,
        array $cart
    ): bool
    {
        try {
            // SEMPRE USAR MOCK PARA PIX
            $mockPath = resource_path('mock/get-plans.json');
            $plansData = null;

            if (file_exists($mockPath)) {
                $mockJson = file_get_contents($mockPath);
                $mockData = json_decode($mockJson, true);
                // Converter formato do mock para formato esperado
                if (is_array($mockData)) {
                    $plansData = [];
                    foreach ($mockData as $key => $plan) {
                        $plansData[] = [
                            'key' => $key,
                            'prices' => $plan['prices'] ?? [],
                        ];
                    }
                }
            }

            if (empty($plansData)) {
                Log::warning('Nenhuma fonte de planos disponível', [
                    'plan_key' => $planKey,
                ]);
                return false;
            }

            // 3. Encontrar o plano solicitado
            $planFound = false;
            $expectedAmount = 0;

            foreach ($plansData as $plan) {
                if ($plan['key'] === $planKey) {
                    $planFound = true;

                    // Encontrar preço na moeda solicitada
                    if (isset($plan['prices'][$currencyCode])) {
                        $priceData = $plan['prices'][$currencyCode];
                        $basePrice = floatval($priceData['descont_price'] ?? $priceData['price'] ?? 0);
                        $expectedAmount = (int)round($basePrice * 100);
                    }
                    break;
                }
            }

            if (!$planFound) {
                Log::warning('Plano não encontrado para validação', [
                    'plan_key' => $planKey,
                ]);
                return false;
            }

            // 4. Somar valores dos bumps (order bumps)
            $bumpsTotal = 0;
            foreach ($cart as $item) {
                if (($item['operation_type'] ?? 0) === 2) { // operation_type 2 = bump
                    $bumpsTotal += $item['price'] ?? 0;
                }
            }

            $totalExpected = $expectedAmount + $bumpsTotal;

            // 4. VALIDAR: O amount recebido deve ser igual ao total esperado
            // Tolerância: 5% para erros de conversão de moedas
            $tolerance = (int)round($totalExpected * 0.05);
            $isValid = abs($amount - $totalExpected) <= $tolerance;

            if (!$isValid) {
                Log::warning('Valor do pagamento não corresponde ao plano', [
                    'expected_amount' => $totalExpected,
                    'received_amount' => $amount,
                    'plan_key' => $planKey,
                    'difference' => abs($amount - $totalExpected),
                ]);
            }

            return $isValid;

        } catch (\Exception $e) {
            Log::error('Erro ao validar quantidade do plano', [
                'exception' => $e->getMessage(),
                'plan_key' => $planKey,
            ]);
            // Por segurança, rejeitar se não conseguir validar
            return false;
        }
    }

    /**
     * Valida CPF com algoritmo de dígitos verificadores
     */
    private function isValidCpf(string $cpf): bool
    {
        // Remove pontuação
        $cpf = preg_replace('/[^0-9]/', '', $cpf);

        // CPF deve ter 11 dígitos
        if (strlen($cpf) !== 11) {
            return false;
        }

        // Verifica se todos os dígitos são iguais
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        // Valida primeiro dígito verificador
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($cpf[$i]) * (10 - $i);
        }
        $firstVerifier = 11 - ($sum % 11);
        $firstVerifier = $firstVerifier >= 10 ? 0 : $firstVerifier;

        if (intval($cpf[9]) !== $firstVerifier) {
            return false;
        }

        // Valida segundo dígito verificador
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += intval($cpf[$i]) * (11 - $i);
        }
        $secondVerifier = 11 - ($sum % 11);
        $secondVerifier = $secondVerifier >= 10 ? 0 : $secondVerifier;

        if (intval($cpf[10]) !== $secondVerifier) {
            return false;
        }

        return true;
    }

    /**
     * Constrói a descrição do pagamento
     */
    private function buildPaymentDescription(string $planKey, string $customerName): string
    {
        $timestamp = now()->format('d/m/Y H:i');
        return "Assinatura SnapHubb - Plano {$planKey} - {$customerName} - {$timestamp}";
    }

    /**
     * GET /api/pix/status/:payment_id
     * Consulta o status de um pagamento PIX
     */
    public function getPaymentStatus(Request $request, int $paymentId): JsonResponse
    {
        try {
            // Validação do ID do pagamento
            if ($paymentId <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'ID de pagamento inválido.',
                ], 400);
            }

            Log::channel('payment_checkout')->debug('PixController: Consultando status do PIX', [
                'payment_id' => $paymentId,
            ]);

            // Consulta o status no serviço
            $result = $this->pixService->getPaymentStatus($paymentId);

            if ($result['status'] === 'error') {
                return response()->json($result, 400);
            }

            // Retorna o status do pagamento
            return response()->json([
                'status' => 'success',
                'data' => $result['data'],
            ], 200);
        } catch (\Exception $e) {
            Log::error('PixController: Erro ao consultar status do PIX', [
                'payment_id' => $paymentId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro ao consultar o status do pagamento.',
            ], 500);
        }
    }
}
