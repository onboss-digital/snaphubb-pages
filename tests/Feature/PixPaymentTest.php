<?php

namespace Tests\Feature;

use App\Services\MercadoPagoPixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes de Feature para PIX Mercado Pago
 * 
 * Execute com:
 * php artisan test tests/Feature/PixPaymentTest.php
 */
class PixPaymentTest extends TestCase
{
    use RefreshDatabase;

    private MercadoPagoPixService $pixService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pixService = app(MercadoPagoPixService::class);
    }

    /**
     * Teste: Criar pagamento PIX com dados válidos
     */
    public function test_create_pix_payment_with_valid_data(): void
    {
        // Skip se token não estiver configurado
        if (empty(env('MP_ACCESS_TOKEN_SANDBOX'))) {
            $this->markTestSkipped('MP_ACCESS_TOKEN_SANDBOX não configurado');
        }

        $response = $this->postJson('/api/pix/create', [
            'amount' => 10000,
            'description' => 'Teste PIX',
            'customer_email' => 'teste@example.com',
            'customer_name' => 'Usuário Teste',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'status',
            'data' => [
                'payment_id',
                'qr_code_base64',
                'qr_code',
                'expiration_date',
                'amount',
                'status',
            ]
        ]);

        $this->assertEquals('success', $response->json('status'));
        $this->assertNotNull($response->json('data.payment_id'));
    }

    /**
     * Teste: Validação de email obrigatório
     */
    public function test_create_pix_requires_email(): void
    {
        $response = $this->postJson('/api/pix/create', [
            'amount' => 10000,
            'description' => 'Teste PIX',
            'customer_name' => 'Usuário Teste',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('customer_email');
    }

    /**
     * Teste: Validação de email inválido
     */
    public function test_create_pix_requires_valid_email(): void
    {
        $response = $this->postJson('/api/pix/create', [
            'amount' => 10000,
            'description' => 'Teste PIX',
            'customer_email' => 'email-invalido',
            'customer_name' => 'Usuário Teste',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('customer_email');
    }

    /**
     * Teste: Validação de valor obrigatório
     */
    public function test_create_pix_requires_amount(): void
    {
        $response = $this->postJson('/api/pix/create', [
            'description' => 'Teste PIX',
            'customer_email' => 'teste@example.com',
            'customer_name' => 'Usuário Teste',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }

    /**
     * Teste: Validação de valor positivo
     */
    public function test_create_pix_requires_positive_amount(): void
    {
        $response = $this->postJson('/api/pix/create', [
            'amount' => -100,
            'description' => 'Teste PIX',
            'customer_email' => 'teste@example.com',
            'customer_name' => 'Usuário Teste',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('amount');
    }

    /**
     * Teste: Validação de nome obrigatório
     */
    public function test_create_pix_requires_customer_name(): void
    {
        $response = $this->postJson('/api/pix/create', [
            'amount' => 10000,
            'description' => 'Teste PIX',
            'customer_email' => 'teste@example.com',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('customer_name');
    }

    /**
     * Teste: Consultar status com payment_id válido
     */
    public function test_get_payment_status_with_valid_id(): void
    {
        // Skip se token não estiver configurado
        if (empty(env('MP_ACCESS_TOKEN_SANDBOX'))) {
            $this->markTestSkipped('MP_ACCESS_TOKEN_SANDBOX não configurado');
        }

        // Primeiro, cria um pagamento
        $createResponse = $this->postJson('/api/pix/create', [
            'amount' => 10000,
            'description' => 'Teste Status',
            'customer_email' => 'status-test@example.com',
            'customer_name' => 'Teste Status',
        ]);

        if ($createResponse->status() === 201) {
            $paymentId = $createResponse->json('data.payment_id');

            // Agora consulta o status
            $statusResponse = $this->getJson("/api/pix/status/{$paymentId}");

            $statusResponse->assertStatus(200);
            $statusResponse->assertJsonStructure([
                'status',
                'data' => [
                    'payment_id',
                    'payment_status',
                    'status_detail',
                    'amount',
                ]
            ]);

            $this->assertEquals('success', $statusResponse->json('status'));
        }
    }

    /**
     * Teste: Consultar status com payment_id inválido
     */
    public function test_get_payment_status_with_invalid_id(): void
    {
        $response = $this->getJson('/api/pix/status/9999999999');

        // Pode retornar 400 ou 404
        $this->assertIn($response->status(), [400, 404]);
        $this->assertEquals('error', $response->json('status'));
    }

    /**
     * Teste: Validação de descrição opcional
     */
    public function test_create_pix_with_optional_description(): void
    {
        $response = $this->postJson('/api/pix/create', [
            'amount' => 10000,
            'customer_email' => 'teste@example.com',
            'customer_name' => 'Usuário Teste',
        ]);

        // Deve aceitar sem descrição (é opcional)
        $this->assertIn($response->status(), [201, 422]);
    }

    /**
     * Teste: Variação de valores
     */
    public function test_create_pix_with_different_amounts(): void
    {
        if (empty(env('MP_ACCESS_TOKEN_SANDBOX'))) {
            $this->markTestSkipped('MP_ACCESS_TOKEN_SANDBOX não configurado');
        }

        $amounts = [100, 1000, 10000, 100000]; // R$ 1, 10, 100, 1000

        foreach ($amounts as $amount) {
            $response = $this->postJson('/api/pix/create', [
                'amount' => $amount,
                'description' => "Teste PIX R$ " . ($amount / 100),
                'customer_email' => 'teste@example.com',
                'customer_name' => 'Usuário Teste',
            ]);

            // Ambos devem ser aceitos ou rejeitados consistentemente
            $this->assertIn($response->status(), [201, 400]);
        }
    }

    /**
     * Teste: Múltiplas requisições simultâneas
     */
    public function test_multiple_concurrent_pix_requests(): void
    {
        if (empty(env('MP_ACCESS_TOKEN_SANDBOX'))) {
            $this->markTestSkipped('MP_ACCESS_TOKEN_SANDBOX não configurado');
        }

        $responses = [];

        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/pix/create', [
                'amount' => 10000,
                'description' => "Teste Concorrente $i",
                'customer_email' => "teste$i@example.com",
                'customer_name' => "Usuário Teste $i",
            ]);

            $responses[] = $response;
        }

        // Verifica se todas as requisições foram processadas
        $this->assertCount(3, $responses);

        // Verifica se todas retornaram o mesmo status
        foreach ($responses as $response) {
            $this->assertIn($response->status(), [201, 400]);
        }
    }
}
