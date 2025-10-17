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


    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}