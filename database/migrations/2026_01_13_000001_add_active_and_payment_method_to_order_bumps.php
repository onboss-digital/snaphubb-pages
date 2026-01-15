<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_bumps')) {
            if (! Schema::hasColumn('order_bumps', 'active')) {
                Schema::table('order_bumps', function (Blueprint $table) {
                    $table->boolean('active')->default(true)->after('recommended');
                });
            }

            if (! Schema::hasColumn('order_bumps', 'payment_method')) {
                Schema::table('order_bumps', function (Blueprint $table) {
                    $table->string('payment_method', 32)->default('card')->after('active');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_bumps')) {
            Schema::table('order_bumps', function (Blueprint $table) {
                if (Schema::hasColumn('order_bumps', 'payment_method')) {
                    $table->dropColumn('payment_method');
                }
                if (Schema::hasColumn('order_bumps', 'active')) {
                    $table->dropColumn('active');
                }
            });
        }
    }
};
