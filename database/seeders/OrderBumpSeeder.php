<?php

namespace Database\Seeders;

use App\Models\OrderBump;
use Illuminate\Database\Seeder;

class OrderBumpSeeder extends Seeder
{
    public function run(): void
    {
        // BUMPS PARA CARTÃO DE CRÉDITO
        OrderBump::create([
            'name' => 'Acesso Premium',
            'description' => 'Acesso a conteúdos exclusivos ao vivo e eventos',
            'original_price' => 29.90,
            'discount_percentage' => 40,
            'icon' => 'video',
            'badge' => 'POPULAR',
            'badge_color' => 'red',
            'social_proof_count' => 1284,
            'urgency_text' => '⏰ Limitado a 50 pessoas',
            'recommended' => true,
            'payment_method' => 'card',
            'active' => true,
        ]);

        OrderBump::create([
            'name' => 'Guia Completo',
            'description' => 'Acesso ao guia completo de estratégias avançadas',
            'original_price' => 19.90,
            'discount_percentage' => 50,
            'icon' => 'book',
            'badge' => 'BEST SELLER',
            'badge_color' => 'gold',
            'social_proof_count' => 3421,
            'urgency_text' => '⭐ Mais comprado este mês',
            'recommended' => false,
            'payment_method' => 'card',
            'active' => true,
        ]);

        OrderBump::create([
            'name' => 'Suporte 24/7',
            'description' => 'Atendimento prioritário via chat e email',
            'original_price' => 14.90,
            'discount_percentage' => 60,
            'icon' => 'lock',
            'badge' => null,
            'badge_color' => null,
            'social_proof_count' => 892,
            'urgency_text' => '🔒 Garantia de satisfação',
            'recommended' => false,
            'payment_method' => 'card',
            'active' => true,
        ]);

        // BUMPS PARA PIX (design simplificado)
        OrderBump::create([
            'name' => 'Bônus Extra PIX',
            'description' => 'Material complementar exclusivo',
            'original_price' => 9.99,
            'discount_percentage' => 70,
            'icon' => 'star',
            'badge' => 'PIX',
            'badge_color' => 'blue',
            'social_proof_count' => 547,
            'urgency_text' => '⚡ Válido apenas para PIX',
            'recommended' => true,
            'payment_method' => 'pix',
            'active' => true,
        ]);

        OrderBump::create([
            'name' => 'Acesso Liberado',
            'description' => 'Desbloqueie conteúdo premium',
            'original_price' => 7.99,
            'discount_percentage' => 75,
            'icon' => 'lock',
            'badge' => null,
            'badge_color' => null,
            'social_proof_count' => 213,
            'urgency_text' => '✨ Aproveite agora',
            'recommended' => false,
            'payment_method' => 'pix',
            'active' => true,
        ]);
    }
}
