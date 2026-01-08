<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('order_bumps', function (Blueprint $table) {
            if (!Schema::hasColumn('order_bumps', 'payment_method')) {
                $table->enum('payment_method', ['card', 'pix', 'all'])->default('all');
            }
            if (!Schema::hasColumn('order_bumps', 'active')) {
                $table->boolean('active')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_bumps', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'active']);
        });
    }
};
