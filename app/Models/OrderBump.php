<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderBump extends Model
{
    protected $table = 'order_bumps';

    protected $fillable = [
        'order_id',
        'plan_id',
        'name',
        'description',
        'original_price',
        'discount_percentage',
        'icon',
        'badge',
        'badge_color',
        'social_proof_count',
        'urgency_text',
        'recommended',
        'payment_method', // 'card', 'pix', 'all'
        'active',
    ];

    protected $casts = [
        'recommended' => 'boolean',
        'active' => 'boolean',
        'original_price' => 'float',
        'discount_percentage' => 'integer',
        'social_proof_count' => 'integer',
    ];

    /**
     * Obter bumps por método de pagamento
     */
    public static function getByPaymentMethod($method = 'card')
    {
        return self::where('active', true)
            ->where(function ($query) use ($method) {
                $query->where('payment_method', $method)
                    ->orWhere('payment_method', 'all');
            })
            ->get()
            ->toArray();
    }

    /**
     * Obter todos os bumps ativos
     */
    public static function getAllActive()
    {
        return self::where('active', true)->get()->toArray();
    }
}
