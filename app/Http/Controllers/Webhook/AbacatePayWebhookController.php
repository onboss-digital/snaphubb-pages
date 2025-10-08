<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\PaymentGateways\AbacatePayGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AbacatePayWebhookController extends Controller
{
    private AbacatePayGateway $gateway;

    public function __construct(AbacatePayGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Recebe e processa webhooks do AbacatePay
     */
    public function handle(Request $request)
    {
        try {
            // Captura o payload bruto
            $payload = $request->getContent();
            $signature = $request->header('X-Webhook-Signature');

            // Log do webhook recebido
            Log::channel('webhooks')->info('AbacatePay Webhook Recebido', [
                'headers' => $request->headers->all(),
                'payload' => $payload,
            ]);

            // Valida a assinatura do webhook
            if (!$this->gateway->validateWebhookSignature($payload, $signature)) {
                Log::channel('webhooks')->warning('AbacatePay Webhook: Assinatura inválida', [
                    'signature' => $signature,
                ]);

                return response()->json([
                    'error' => 'Invalid signature'
                ], 401);
            }

            // Decodifica o payload
            $data = json_decode($payload, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::channel('webhooks')->error('AbacatePay Webhook: JSON inválido', [
                    'error' => json_last_error_msg(),
                ]);

                return response()->json([
                    'error' => 'Invalid JSON'
                ], 400);
            }

            // Processa o evento
            $this->processEvent($data);

            return response()->json([
                'status' => 'success',
                'message' => 'Webhook processed'
            ], 200);

        } catch (\Exception $e) {
            Log::channel('webhooks')->error('AbacatePay Webhook: Erro ao processar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Processa o evento do webhook baseado no tipo
     */
    private function processEvent(array $data): void
    {
        $eventType = $data['event'] ?? $data['type'] ?? null;
        $billingData = $data['data'] ?? $data;

        Log::channel('webhooks')->info('AbacatePay Webhook: Processando evento', [
            'event' => $eventType,
            'billing_id' => $billingData['id'] ?? null,
            'status' => $billingData['status'] ?? null,
        ]);

        switch ($eventType) {
            case 'billing.paid':
                $this->handlePaidEvent($billingData);
                break;

            case 'billing.expired':
                $this->handleExpiredEvent($billingData);
                break;

            case 'billing.cancelled':
                $this->handleCancelledEvent($billingData);
                break;

            default:
                Log::channel('webhooks')->info('AbacatePay Webhook: Evento não tratado', [
                    'event' => $eventType,
                ]);
                break;
        }
    }

    /**
     * Trata evento de pagamento confirmado
     */
    private function handlePaidEvent(array $billingData): void
    {
        $pixId = $billingData['id'];
        $metadata = $billingData['metadata'] ?? [];

        Log::channel('webhooks')->info('AbacatePay: Pagamento confirmado', [
            'pix_id' => $pixId,
            'amount' => $billingData['amount'] ?? null,
            'metadata' => $metadata,
        ]);

        // TODO: Implementar lógica de negócio
        // 1. Atualizar status do pedido no banco de dados
        // 2. Liberar acesso ao produto/serviço
        // 3. Enviar email de confirmação
        // 4. Registrar no sistema de analytics
        
        // Exemplo de como buscar o pedido:
        // $order = Order::where('pix_id', $pixId)->first();
        // if ($order) {
        //     $order->update(['status' => 'paid']);
        //     // Liberar acesso, enviar email, etc.
        // }
    }

    /**
     * Trata evento de pagamento expirado
     */
    private function handleExpiredEvent(array $billingData): void
    {
        $pixId = $billingData['id'];

        Log::channel('webhooks')->info('AbacatePay: Pagamento expirado', [
            'pix_id' => $pixId,
        ]);

        // TODO: Implementar lógica de negócio
        // 1. Atualizar status do pedido para expirado
        // 2. Enviar email notificando expiração
        // 3. Oferecer opção de gerar novo PIX
        
        // Exemplo:
        // $order = Order::where('pix_id', $pixId)->first();
        // if ($order) {
        //     $order->update(['status' => 'expired']);
        // }
    }

    /**
     * Trata evento de pagamento cancelado
     */
    private function handleCancelledEvent(array $billingData): void
    {
        $pixId = $billingData['id'];

        Log::channel('webhooks')->info('AbacatePay: Pagamento cancelado', [
            'pix_id' => $pixId,
        ]);

        // TODO: Implementar lógica de negócio
        // 1. Atualizar status do pedido para cancelado
        // 2. Registrar motivo do cancelamento se disponível
        
        // Exemplo:
        // $order = Order::where('pix_id', $pixId)->first();
        // if ($order) {
        //     $order->update(['status' => 'cancelled']);
        // }
    }
}
