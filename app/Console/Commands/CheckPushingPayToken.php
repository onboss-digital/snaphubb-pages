<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPushingPayToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pushing-pay:check-token';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Verifica se o token da Pushing Pay está configurado corretamente';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verificando configuração da Pushing Pay...');
        $this->newLine();

        // Verificar via env()
        $envToken = env('PP_ACCESS_TOKEN_PROD', '');
        $this->line('1️⃣  Via env(): ' . ($envToken ? '✅ Encontrado (' . strlen($envToken) . ' chars)' : '❌ Vazio'));

        // Verificar via getenv()
        $getenvToken = getenv('PP_ACCESS_TOKEN_PROD', '');
        $this->line('2️⃣  Via getenv(): ' . ($getenvToken ? '✅ Encontrado (' . strlen($getenvToken) . ' chars)' : '❌ Vazio'));

        // Verificar via config()
        $configToken = config('services.pushing_pay.token_prod', '');
        $this->line('3️⃣  Via config(): ' . ($configToken ? '✅ Encontrado (' . strlen($configToken) . ' chars)' : '❌ Vazio'));

        // Verificar $_ENV
        $envArrayToken = $_ENV['PP_ACCESS_TOKEN_PROD'] ?? '';
        $this->line('4️⃣  Via $_ENV: ' . ($envArrayToken ? '✅ Encontrado (' . strlen($envArrayToken) . ' chars)' : '❌ Vazio'));

        // Arquivo .env existe?
        $envFile = base_path('.env');
        $this->line('5️⃣  Arquivo .env existe: ' . (file_exists($envFile) ? '✅ Sim' : '❌ Não'));

        if (file_exists($envFile)) {
            $envContent = file_get_contents($envFile);
            if (strpos($envContent, 'PP_ACCESS_TOKEN_PROD') !== false) {
                $this->line('    - PP_ACCESS_TOKEN_PROD encontrado no arquivo');
            } else {
                $this->line('    - ❌ PP_ACCESS_TOKEN_PROD NÃO encontrado no arquivo');
            }
        }

        // Cache de config
        $this->line('6️⃣  Cache de config: ' . (file_exists(base_path('bootstrap/cache/config.php')) ? '✅ Existe (pode ser problema!)' : '❌ Não existe (bom)'));

        $this->newLine();

        // Testar com a classe
        $service = app(\App\Services\PushingPayPixService::class);
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('simulate');
        $property->setAccessible(true);
        $isSimulating = $property->getValue($service);

        if ($isSimulating) {
            $this->error('❌ MODO SIMULAÇÃO ATIVO - Token não foi encontrado!');
            Log::error('CheckPushingPayToken: Token não configurado - modo simulação ativo');
        } else {
            $this->info('✅ MODO PRODUÇÃO ATIVO - Token foi encontrado!');
            Log::info('CheckPushingPayToken: Token configurado - modo produção ativo');
        }

        $this->newLine();
        $this->comment('💡 Se o token não foi encontrado:');
        $this->comment('   1. Verifique o .env em produção');
        $this->comment('   2. Execute: php artisan config:clear');
        $this->comment('   3. Execute: php artisan cache:clear');
        $this->comment('   4. Execute: php artisan config:cache');

        return 0;
    }
}
