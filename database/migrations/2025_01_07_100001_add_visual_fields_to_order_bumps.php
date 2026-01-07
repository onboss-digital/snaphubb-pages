<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('order_bumps', function (Blueprint $table) {
            // Campos para melhoria visual e psicológica
            $table->decimal('original_price', 10, 2)->nullable()->after('plan_id');
            $table->integer('discount_percentage')->nullable()->after('original_price');
            $table->string('icon')->nullable()->after('discount_percentage'); // ex: 'lock', 'video', 'book', 'star'
            $table->string('badge')->nullable()->after('icon'); // ex: 'POPULAR', 'BEST SELLER', 'LIMITED TIME'
            $table->string('badge_color')->nullable()->after('badge'); // ex: 'red', 'gold', 'blue'
            $table->integer('social_proof_count')->nullable()->after('badge_color'); // ex: 1250
            $table->string('urgency_text')->nullable()->after('social_proof_count'); // ex: 'Válido apenas nesta compra'
            $table->boolean('recommended')->default(false)->after('urgency_text'); // Se deve estar pré-selecionado
        });
    }

    public function down(): void
    {
        Schema::table('order_bumps', function (Blueprint $table) {
            $table->dropColumn([
                'original_price',
                'discount_percentage',
                'icon',
                'badge',
                'badge_color',
                'social_proof_count',
                'urgency_text',
                'recommended',
            ]);
        });
    }
};
