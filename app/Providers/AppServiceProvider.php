<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Interfaces\PaymentGatewayInterface; // Added
use App\Factories\PaymentGatewayFactory; // Added

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            return PaymentGatewayFactory::create(config('services.default_payment_gateway'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure manifest.json is readable for Vite
        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            // Make sure the manifest is properly loaded
            @json_decode(file_get_contents($manifestPath), true);
        }
    }
}
