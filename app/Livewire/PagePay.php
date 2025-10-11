<?php

namespace Tests\Feature\Livewire;

use App\Factories\PaymentGatewayFactory;
use App\Livewire\PagePay;
use App\Services\PaymentGateways\AbacatePayGateway;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PagePayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.abacatepay.api_key', 'test_abacate_key');

        Log::shouldReceive('channel->info')->zeroOrMoreTimes();
        Log::shouldReceive('channel->error')->zeroOrMoreTimes();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Blade::anonymousComponentPath(resource_path('views/components'));

        Http::fake([
            config('services.streamit.api_url') . '/get-plans' => Http::response(['data' => []], 200),
        ]);
    }

    private function mockAbacatePayGateway(string $paymentStatus = 'PAID')
    {
        $gatewayMock = Mockery::mock(AbacatePayGateway::class);
        $gatewayMock->shouldReceive('formatPlans')->andReturn([]);
        $gatewayMock->shouldReceive('processPayment')->once()->andReturn([
            'status' => 'success',
            'data' => ['pix_id' => 'pix_12345', 'status' => 'PENDING']
        ]);
        $gatewayMock->shouldReceive('checkPaymentStatus')->once()->with('pix_12345')->andReturn([
            'status' => 'success',
            'data' => ['status' => $paymentStatus]
        ]);

        $factoryMock = Mockery::mock(PaymentGatewayFactory::class);
        $factoryMock->shouldReceive('create')->andReturn($gatewayMock);

        $this->app->instance(PaymentGatewayFactory::class, $factoryMock);
    }

    private function getFilledPagePayComponent()
    {
        return Livewire::test(PagePay::class)
            ->set('selectedPaymentMethod', 'pix')
            ->set('selectedLanguage', 'br')
            ->set('cardName', 'Consumidor PIX')
            ->set('email', 'pix@teste.com')
            ->set('cpf', '111.222.333-44')
            ->set('phone', '11987654321');
    }

    #[Test]
    public function it_redirects_to_success_page_on_paid_pix_payment()
    {
        $this->mockAbacatePayGateway('PAID');

        $component = $this->getFilledPagePayComponent()->call('startCheckout');

        $component->assertDispatched('pix-generated')
            ->call('checkPixStatus')
            ->assertRedirect('https://web.snaphubb.online/obg-br/');
    }

    #[Test]
    public function it_redirects_to_fail_page_on_failed_pix_payment()
    {
        $this->mockAbacatePayGateway('FAILED');

        $component = $this->getFilledPagePayComponent()->call('startCheckout');

        $component->assertDispatched('pix-generated')
            ->call('checkPixStatus')
            ->assertRedirect('https://web.snaphubb.online/fail-br');
    }

    #[Test]
    public function it_redirects_to_fail_page_on_expired_pix_payment()
    {
        $this->mockAbacatePayGateway('EXPIRED');

        $component = $this->getFilledPagePayComponent()->call('startCheckout');

        $component->assertDispatched('pix-generated')
            ->call('checkPixStatus')
            ->assertRedirect('https://web.snaphubb.online/fail-br');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
