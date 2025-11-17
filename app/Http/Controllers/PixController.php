<?php

namespace App\Http\Controllers;

use App\Services\MercadoPagoPixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
     * Cria um novo pagamento PIX
     */
    public function createPayment(Request $request): JsonResponse
    {
        try {
            // Validação dos dados recebidos
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:1',
                'description' => 'nullable|string|max:255',
                'customer_email' => 'required|email',
                'customer_name' => 'required|string|max:255',
            ], [
                'amount.required' => 'O valor do pagamento é obrigatório.',
                'amount.numeric' => 'O valor deve ser um número válido.',
                'amount.min' => 'O valor deve ser maior que zero.',
                'customer_email.required' => 'O email do cliente é obrigatório.',
                'customer_email.email' => 'O email deve ser válido.',
                'customer_name.required' => 'O nome do cliente é obrigatório.',
            ]);

            if ($validator->fails()) {
                Log::warning('PixController: Validação falhou ao criar PIX', [
                    'errors' => $validator->errors(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Dados inválidos.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Prepara os dados para o serviço
            $paymentData = [
                'amount' => (int) $request->input('amount'),
                'description' => $request->input('description', 'Pagamento PIX'),
                'customerEmail' => $request->input('customer_email'),
                'customerName' => $request->input('customer_name'),
            ];

            Log::channel('payment_checkout')->info('PixController: Criando pagamento PIX', [
                'amount' => $paymentData['amount'],
                'customer_email' => $paymentData['customerEmail'],
            ]);

            // Chama o serviço para criar o pagamento
            $result = $this->pixService->createPixPayment($paymentData);

            if ($result['status'] === 'error') {
                return response()->json($result, 400);
            }

            // Retorna os dados do PIX com sucesso
            return response()->json([
                'status' => 'success',
                'data' => $result['data'],
            ], 201);
        } catch (\Exception $e) {
            Log::error('PixController: Erro ao criar pagamento PIX', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Erro interno ao processar o pagamento.',
            ], 500);
        }
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
