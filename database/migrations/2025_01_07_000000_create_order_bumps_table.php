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
        Schema::create('order_bumps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('plan_id')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('original_price', 10, 2)->nullable();
            $table->integer('discount_percentage')->nullable();
            $table->string('icon')->nullable();
            $table->string('badge')->nullable();
            $table->string('badge_color')->nullable();
            $table->integer('social_proof_count')->nullable();
            $table->string('urgency_text')->nullable();
            $table->boolean('recommended')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_bumps');
    }
};
