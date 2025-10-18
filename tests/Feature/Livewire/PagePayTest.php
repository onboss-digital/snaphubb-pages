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
        Config::set('services.stripe.api_public_key', 'pk_test_123');
        Config::set('services.stripe.api_secret_key', 'sk_test_123');
        Config::set('services.stripe.api_url', 'https://api.stripe.com/v1');
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


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_captures_utm_parameters_and_sends_them_to_stripe()
    {
        // Arrange
        $utmParameters = [
            'utm_source' => 'facebook',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'summer_sale',
            'utm_term' => 'running_shoes',
            'utm_content' => 'ad_1',
            'utm_id' => '12345',
            'src' => 'test_src',
            'sck' => 'test_sck',
        ];

        // Mock the StripeGateway
        $stripeGatewayMock = Mockery::mock(StripeGateway::class, \App\Interfaces\PaymentGatewayInterface::class, function (MockInterface $mock) use ($utmParameters) {
            $mock->shouldReceive('processPayment')
                ->once()
                ->with(Mockery::on(function ($data) use ($utmParameters) {
                    $metadata = $data['metadata'];
                    $this->assertEquals($utmParameters['utm_source'], $metadata['utm_source']);
                    $this->assertEquals($utmParameters['utm_medium'], $metadata['utm_medium']);
                    $this->assertEquals($utmParameters['utm_campaign'], $metadata['utm_campaign']);
                    $this->assertEquals($utmParameters['utm_term'], $metadata['utm_term']);
                    $this->assertEquals($utmParameters['utm_content'], $metadata['utm_content']);
                    $this->assertEquals($utmParameters['utm_id'], $metadata['utm_id']);
                    $this->assertEquals($utmParameters['src'], $metadata['src']);
                    $this->assertEquals($utmParameters['sck'], $metadata['sck']);
                    return true;
                }))
                ->andReturn(['status' => 'success', 'data' => []]);

            $mock->shouldReceive('formatPlans')->andReturn($this->getFakePlans());
        });

        // Replace the factory binding with our mock
        $this->instance(PaymentGatewayFactory::class, new class($stripeGatewayMock)
        {
            public function __construct(private $mock)
            {
            }
            public function create(): \App\Interfaces\PaymentGatewayInterface
            {
                return $this->mock;
            }
        });

        Config::set('services.default_payment_gateway', 'stripe');

        // Act & Assert
        $fakePlans = $this->getFakePlans();

        Livewire::withQueryParams($utmParameters)
            ->test(PagePay::class)
            ->set('plans', $fakePlans)
            ->set('bumps', $fakePlans['monthly']['order_bumps'])
            ->set('cardName', 'Test User')
            ->set('email', 'test@example.com')
            ->set('phone', '1234567890')
            ->set('cpf', '123.456.789-00')
            ->set('paymentMethodId', 'pm_card_visa')
            ->call('calculateTotals')
            ->call('sendCheckout');
    }

    private function getFakePlans()
    {
        return [
            'monthly' => [
                'hash' => 'monthly_hash',
                'label' => 'Monthly Plan',
                'nunber_months' => 1,
                'prices' => [
                    'BRL' => [
                        'id' => 'price_monthly_brl',
                        'origin_price' => 50.00,
                        'descont_price' => 39.90,
                        'recurring' => true,
                    ],
                ],
                'upsell_url' => 'http://example.com/upsell',
                'order_bumps' => [],
            ],
            'quarterly' => [
                'hash' => 'quarterly_hash',
                'label' => 'Quarterly Plan',
                'nunber_months' => 3,
                'prices' => [
                    'BRL' => [
                        'id' => 'price_quarterly_brl',
                        'origin_price' => 150.00,
                        'descont_price' => 109.90,
                        'recurring' => true,
                    ],
                ],
                'upsell_url' => null,
                'order_bumps' => [],
            ],
            'semi-annual' => [
                'hash' => 'semi_annual_hash',
                'label' => 'Semi-Annual Plan',
                'nunber_months' => 6,
                'prices' => [
                    'BRL' => [
                        'id' => 'price_semi_annual_brl',
                        'origin_price' => 300.00,
                        'descont_price' => 199.90,
                        'recurring' => true,
                    ],
                ],
                'upsell_url' => null,
                'order_bumps' => [],
            ],
        ];
    }
}