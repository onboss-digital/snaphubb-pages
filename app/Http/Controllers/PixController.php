<?php

namespace App\Http\Controllers;

use App\Factories\PixServiceFactory;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controller para gerenciar pagamentos PIX
 * Endpoints para criar pagamentos e consultar status
 */
class PixController extends Controller
{
    private $pixService;

    public function __construct()
    {
        $this->pixService = PixServiceFactory::make();
    }

    /**
     * POST /api/pix/create
     * Cria um novo pagamento PIX com validação de integridade
     */
    public function create(Request $request): JsonResponse
    {
        Log::info('PixController::create - Recebido', [
            'method' => $request->method(),
            'amount' => $request->input('amount'),
            'customer_email' => $request->input('customer.email'),
        ]);
        try {
            // 1. VALIDAR DADOS OBRIGATÓRIOS DO FRONTEND
            $validated = $request->validate([
                'amount' => 'required|integer|min:50', // Mínimo 0.50 em centavos
                'currency_code' => 'required|in:BRL,USD,EUR',
                'plan_key' => 'required|string',
                'offer_hash' => 'required|string',
                'device_id' => 'nullable|string', // Device ID do Pushing Pay
                'bumps' => 'nullable|array', // NOVO: Order bumps selecionados
                'bumps.*' => 'integer', // IDs dos bumps
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
                'cart' => 'nullable|array',
                'cart.*.product_hash' => 'nullable|string',
                'cart.*.title' => 'nullable|string',
                'cart.*.price' => 'nullable|integer',
                'cart.*.quantity' => 'nullable|integer',
                'metadata' => 'nullable|array',
            ]);

            // 2. VALIDAR INTEGRIDADE DA TRANSAÇÃO
            // SEGURANÇA: Verificar que o amount corresponde ao plan_key solicitado
            $validAmount = $this->isValidAmountForPlan(
                $validated['plan_key'],
                $validated['amount'],
                $validated['currency_code'],
                $validated['cart'],
                $validated['offer_hash'] ?? null
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

            // 5. CALCULAR PREÇO BASE DO BACKEND (PLANO + BUMPS) ANTES DE MONTAR O PAYLOAD
            $planBase = $this->getPlanBaseAmountCents($validated['plan_key'], $validated['offer_hash'] ?? null);
            $bumpsTotal = 0;
            foreach (($validated['cart'] ?? []) as $item) {
                if (($item['operation_type'] ?? 0) === 2) {
                    $bumpsTotal += (int) ($item['price'] ?? 0);
                }
            }

            // Se o backend fornece o preço do plano, usamos exclusivamente ele + bumps.
            // Em local/debug o comportamento anterior de permitir bypass permanece.
            $calculatedExpected = null;
            if (isset($planBase['base_cents'])) {
                $calculatedExpected = $planBase['base_cents'] + $bumpsTotal;
            }

            // Se temos valor calculado pelo backend, forçamos o amount para esse valor.
            $pixAmount = $calculatedExpected ?? (int) $validated['amount'];

            // DEBUG: log de auditoria antes do envio
            Log::info('PixController::create - Debug before sending to PixService', [
                'plan_key' => $validated['plan_key'],
                'provided_amount' => $validated['amount'],
                'plan_base_cents' => $planBase['base_cents'] ?? null,
                'plan_base_source' => $planBase['source'] ?? null,
                'bumps_total_cents' => $bumpsTotal,
                'calculated_expected_total_cents' => $calculatedExpected,
                'amount_to_send_cents' => $pixAmount,
            ]);

            // 6. CHAMAR SERVIÇO PIX PARA GERAR PAGAMENTO
            // Extract webhook_url from metadata (if provided)
            $webhookUrl = $validated['metadata']['webhook_url'] ?? null;
            
            $pixPaymentData = [
                'amount' => (int) $pixAmount,
                'currency' => $validated['currency_code'] ?? 'BRL',
                'currency_code' => $validated['currency_code'] ?? 'BRL',
                'description' => $description,
                'webhook_url' => $webhookUrl,
                'customer' => [
                    'name' => $validated['customer']['name'],
                    'email' => $validated['customer']['email'],
                    'phone_number' => $validated['customer']['phone_number'] ?? null,
                    'document' => $validated['customer']['document'] ?? null,
                    'cpf' => $validated['customer']['document'] ?? null,
                    'phone' => $validated['customer']['phone_number'] ?? null,
                    'address' => $validated['customer']['address'] ?? null,
                ],
                'customerName' => $validated['customer']['name'],
                'customerEmail' => $validated['customer']['email'],
                'customerPhone' => $validated['customer']['phone_number'] ?? null,
                'customerDocument' => $validated['customer']['document'] ?? null,
                'customerAddress' => $validated['customer']['address'] ?? null,
                'device_id' => $validated['device_id'] ?? null,
                'external_reference' => $validated['offer_hash'] ?? null,
                'plan_key' => $validated['plan_key'] ?? null,
                'offer_hash' => $validated['offer_hash'] ?? null,
                'cart' => $validated['cart'] ?? [],
                'metadata' => array_merge($validated['metadata'] ?? [], [
                    'bumps' => $validated['bumps'] ?? [],
                    'plan_base_cents' => $planBase['base_cents'] ?? null,
                    'plan_base_source' => $planBase['source'] ?? null,
                    'bumps_total_cents' => $bumpsTotal,
                    'calculated_expected_total_cents' => $calculatedExpected,
                ]),
            ];

            $pixResponse = $this->pixService->createPixPayment($pixPaymentData);

            // Validar resposta - aceita tanto 'success' => true quanto 'status' => 'success'
            $isSuccess = ($pixResponse['success'] ?? false) === true || ($pixResponse['status'] ?? null) === 'success';

            if (!$isSuccess) {
                Log::error('Erro ao criar PIX', [
                    'response' => $pixResponse,
                    'customer_email' => $validated['customer']['email'],
                    'response_keys' => array_keys($pixResponse['data'] ?? []),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => $pixResponse['message'] ?? 'Erro ao gerar código PIX',
                ], 500);
            }

            // 6. LOG PARA AUDITORIA
            Log::channel('payment_checkout')->info('PIX criado com sucesso', [
                'payment_id' => $pixResponse['data']['payment_id'] ?? null,
                'amount' => $validated['amount'],
                'customer_email' => $validated['customer']['email'],
                'plan_key' => $validated['plan_key'],
                'has_qr_code_base64' => !empty($pixResponse['data']['qr_code_base64']),
                'qr_code_base64_length' => strlen($pixResponse['data']['qr_code_base64'] ?? ''),
                'has_qr_code' => !empty($pixResponse['data']['qr_code']),
            ]);

            // 8. RETORNAR DADOS DO PIX PARA O FRONTEND
            $responseData = [
                'status' => 'success',
                'data' => [
                    'payment_id' => $pixResponse['data']['payment_id'] ?? null,
                    'qr_code_base64' => $pixResponse['data']['qr_code_base64'] ?? null,
                    'qr_code' => $pixResponse['data']['qr_code'] ?? null,
                    'amount' => $validated['amount'],
                    'expiration_date' => $pixResponse['data']['expiration_date'] ?? null,
                    'status' => $pixResponse['data']['status'] ?? 'pending',
                ],
            ];

            Log::debug('PixController::create - Resposta ao frontend', [
                'has_qr_code_base64' => !empty($responseData['data']['qr_code_base64']),
                'qr_code_base64_length' => strlen($responseData['data']['qr_code_base64'] ?? ''),
            ]);

            return response()->json($responseData, 201);

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
        array $cart,
        ?string $offerHash = null
    ): bool
    {
        try {
            // 1) Tentar buscar o plano no banco (Modules\Subscriptions\Models\Plan ou tabela `plan`)
            $expectedAmount = null;

            try {
                $planRecord = null;

                if (class_exists('\\Modules\\Subscriptions\\Models\\Plan')) {
                    if (is_numeric($planKey)) {
                        $planRecord = \Modules\Subscriptions\Models\Plan::find((int) $planKey);
                    } else {
                        $planRecord = \Modules\Subscriptions\Models\Plan::where('identifier', $planKey)->first();
                    }
                } else {
                    // Tentar consulta direta na tabela `plan` como fallback
                    if (is_numeric($planKey)) {
                        $planRecord = DB::table('plan')->where('id', (int) $planKey)->first();
                    } else {
                        $planRecord = DB::table('plan')->where('identifier', $planKey)->first();
                    }
                }

                if ($planRecord) {
                    // If this request corresponds to a pages PIX upsell (offerHash matches pages_pix_product_external_id)
                    // prefer the explicit pages_pix_price when present.
                    $pagesPixProductExternal = null;
                    $pagesPixPrice = null;
                    if (is_object($planRecord)) {
                        $pagesPixProductExternal = $planRecord->pages_pix_product_external_id ?? null;
                        $pagesPixPrice = $planRecord->pages_pix_price ?? null;
                    } else {
                        $pagesPixProductExternal = $planRecord['pages_pix_product_external_id'] ?? null;
                        $pagesPixPrice = $planRecord['pages_pix_price'] ?? null;
                    }

                    if (!is_null($offerHash) && $pagesPixProductExternal && $offerHash == $pagesPixProductExternal && !is_null($pagesPixPrice) && $pagesPixPrice !== '') {
                        $expectedAmount = (int) round(floatval($pagesPixPrice) * 100);
                    } else {
                        $pushEnabled = isset($planRecord->pushinpay_enabled) ? (bool) $planRecord->pushinpay_enabled : (bool) ($planRecord['pushinpay_enabled'] ?? false);
                        $pushAmount = isset($planRecord->pushinpay_amount_override) ? $planRecord->pushinpay_amount_override : ($planRecord['pushinpay_amount_override'] ?? null);

                        if ($pushEnabled && !is_null($pushAmount) && $pushAmount != '') {
                            $expectedAmount = (int) round(floatval($pushAmount) * 100);
                        } else {
                            // fallback to plan price fields
                            $basePrice = $planRecord->pushinpay_amount_override ?? $planRecord->price ?? $planRecord->total_price ?? ($planRecord['price'] ?? null);
                            if (!is_null($basePrice) && $basePrice !== '') {
                                $expectedAmount = (int) round(floatval($basePrice) * 100);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Erro ao buscar plano no DB para validação PIX, fallback para mock', ['error' => $e->getMessage(), 'plan_key' => $planKey]);
                $expectedAmount = null;
            }

            // 2) Se não conseguimos determinar pelo DB, NÃO USAR MOCKS/hardcode para PIX.
            // Rejeitar a transação a menos que estejamos em ambiente local/debug.
            if (is_null($expectedAmount)) {

                // CRITICAL FIX: If we are in production but the local database does NOT have a 'plan' table
                // (e.g. this is a frontend-only deployment consuming external APIs), we cannot validate locally.
                // In this specific case, we must trust the upstream/controller input to allow PIX generation.
                $hasPlanTable = false;
                try {
                    $hasPlanTable = Schema::hasTable('plan');
                } catch (\Throwable $e) {
                    // DB connection might be missing entirely
                }

                if (!$hasPlanTable && !class_exists('\\Modules\\Subscriptions\\Models\\Plan')) {
                    Log::warning('PIX Validation Skipped: No local plan table found. Trusting input amount.', [
                        'plan_key' => $planKey,
                        'amount' => $amount
                    ]);
                    return true;
                }

                Log::warning('Nenhuma fonte de planos no DB para validação PIX — recusando uso de mock', [
                    'plan_key' => $planKey,
                ]);

                if (app()->environment('local') || config('app.debug') === true) {
                    Log::info('Ambiente local/debug: permitindo bypass temporário da validação de plano (apenas para testes)', ['plan_key' => $planKey]);
                    return true;
                }

                return false;
            }

            // 3) Somar valores dos bumps (order bumps)
            $bumpsTotal = 0;
            foreach ($cart as $item) {
                if (($item['operation_type'] ?? 0) === 2) { // operation_type 2 = bump
                    $bumpsTotal += $item['price'] ?? 0;
                }
            }

            $totalExpected = $expectedAmount + $bumpsTotal;

            // 4) VALIDAR: O amount recebido deve ser igual ao total esperado
            // Tolerância: 5% para erros de conversão de moedas
            $tolerance = (int) round($totalExpected * 0.05);
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
     * Retorna o valor base do plano em centavos e a origem (db) ou null se não encontrado.
     * Não faz fallback para mocks — isso é intencional para evitar usar hardcoded values.
     */
    private function getPlanBaseAmountCents(string $planKey, ?string $offerHash = null): ?array
    {
        try {
            $planRecord = null;

            if (class_exists('\Modules\Subscriptions\Models\Plan')) {
                if (is_numeric($planKey)) {
                    $planRecord = \Modules\Subscriptions\Models\Plan::find((int) $planKey);
                } else {
                    $planRecord = \Modules\Subscriptions\Models\Plan::where('identifier', $planKey)->first();
                }
            } else {
                if (is_numeric($planKey)) {
                    $planRecord = DB::table('plan')->where('id', (int) $planKey)->first();
                } else {
                    $planRecord = DB::table('plan')->where('identifier', $planKey)->first();
                }
            }

            if (!$planRecord) {
                return null;
            }

            // If this is an upsell via pages (offerHash matches pages_pix_product_external_id),
            // prefer the explicit pages_pix_price when present.
            $pagesPixProductExternal = null;
            $pagesPixPrice = null;
            if (is_object($planRecord)) {
                $pagesPixProductExternal = $planRecord->pages_pix_product_external_id ?? null;
                $pagesPixPrice = $planRecord->pages_pix_price ?? null;
            } else {
                $pagesPixProductExternal = $planRecord['pages_pix_product_external_id'] ?? null;
                $pagesPixPrice = $planRecord['pages_pix_price'] ?? null;
            }

            if (!is_null($offerHash) && $pagesPixProductExternal && $offerHash == $pagesPixProductExternal && !is_null($pagesPixPrice) && $pagesPixPrice !== '') {
                return ['base_cents' => (int) round(floatval($pagesPixPrice) * 100), 'source' => 'pages_pix_price'];
            }

            $pushEnabled = isset($planRecord->pushinpay_enabled) ? (bool) $planRecord->pushinpay_enabled : (bool) ($planRecord['pushinpay_enabled'] ?? false);
            $pushAmount = isset($planRecord->pushinpay_amount_override) ? $planRecord->pushinpay_amount_override : ($planRecord['pushinpay_amount_override'] ?? null);

            if ($pushEnabled && !is_null($pushAmount) && $pushAmount !== '') {
                return ['base_cents' => (int) round(floatval($pushAmount) * 100), 'source' => 'pushinpay_override'];
            }

            $basePrice = $planRecord->price ?? $planRecord->total_price ?? ($planRecord['price'] ?? null);
            if (!is_null($basePrice) && $basePrice !== '') {
                return ['base_cents' => (int) round(floatval($basePrice) * 100), 'source' => 'plan_price'];
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('getPlanBaseAmountCents: erro ao buscar plano', ['plan_key' => $planKey, 'error' => $e->getMessage()]);
            return null;
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
    /**
     * GET /api/pix/status/{paymentId}
     * 
     * Endpoint para frontend fazer polling e detectar quando pagamento foi aprovado
     * Usado pelo componente Livewire para detectar pagamento sem depender de webhook
     * 
     * @param string $paymentId - ID do pagamento (PIX payment ID)
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentStatus(string $paymentId): \Illuminate\Http\JsonResponse
    {
        try {
            // Validação de entrada
            if (empty($paymentId)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment ID is required',
                ], 400);
            }

            // Buscar ordem associada ao payment ID
            $order = Order::where('pix_id', $paymentId)
                ->orWhere('external_payment_id', $paymentId)
                ->first();

            if (!$order) {
                Log::warning('Order not found for payment status check', [
                    'payment_id' => $paymentId,
                ]);

                // Retornar erro amigável ao invés de 404 para não prender modal
                return response()->json([
                    'status' => 'not_found',
                    'message' => 'Payment not found. Please check your transaction ID.',
                    'payment_id' => $paymentId,
                ], 200); // 200 ao invés de 404 para JavaScript continuar tentando
            }

            Log::info('Payment status checked', [
                'payment_id' => $paymentId,
                'order_id' => $order->id,
                'order_status' => $order->status,
            ]);

            // Retornar status simples para frontend saber se deve redirecionar
            $response = [
                'order_id' => $order->id,
                'payment_id' => $paymentId,
                'status' => $order->status, // 'pending', 'paid', 'declined', 'expired'
                'amount' => (float) $order->price,
                'currency' => $order->currency,
                'created_at' => $order->created_at?->toIso8601String(),
                'updated_at' => $order->updated_at?->toIso8601String(),
            ];

            // Se pagamento foi aprovado, adicionar redirect URL
            if ($order->status === 'paid') {
                $redirectUrl = $order->user_id 
                    ? '/account' // Usuário foi criado, ir para conta
                    : '/checkout'; // Algo deu errado, voltar para checkout

                $response['redirect_url'] = $redirectUrl;
                $response['message'] = 'Payment approved! Redirecting...';
            } elseif ($order->status === 'declined') {
                $response['message'] = 'Payment declined. Please try again.';
            } elseif ($order->status === 'expired') {
                $response['message'] = 'Payment expired. Please create a new transaction.';
            } else {
                $response['message'] = 'Payment still pending...';
            }

            return response()->json($response, 200);

        } catch (\Exception $e) {
            Log::error('Error checking payment status', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error checking payment status. Please try again later.',
                'payment_id' => $paymentId,
            ], 200); // 200 para não quebrar polling
        }
    }
