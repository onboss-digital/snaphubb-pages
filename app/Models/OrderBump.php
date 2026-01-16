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
        $items = self::where('active', true)
            ->where(function ($query) use ($method) {
                $query->where('payment_method', $method)
                    ->orWhere('payment_method', 'all');
            })
            ->get();

        return $items->map(function ($b) {
            $arr = $b->toArray();

            return [
                'id' => $arr['id'] ?? null,
                'hash' => $arr['external_id'] ?? $arr['hash'] ?? null,
                'title' => $arr['title'] ?? $arr['name'] ?? null,
                'price' => isset($arr['price']) ? floatval($arr['price']) : (isset($arr['original_price']) ? floatval($arr['original_price']) : 0.0),
                'original_price' => isset($arr['original_price']) ? floatval($arr['original_price']) : (isset($arr['price']) ? floatval($arr['price']) : 0.0),
                'price_id' => $arr['price_id'] ?? null,
                'recurring' => $arr['recurring'] ?? null,
                // Do not mark bumps as selected by default in the UI; user must opt-in
                'active' => isset($arr['active']) ? (bool)$arr['active'] : false,
                'recommended' => isset($arr['recommended']) ? (bool)$arr['recommended'] : false,
                'description' => $arr['description'] ?? null,
                'payment_method' => $arr['payment_method'] ?? 'card',
            ];
        })->toArray();
    }

    /**
     * Obter todos os bumps ativos
     */
    public static function getAllActive()
    {
        $items = self::where('active', true)->get();

        return $items->map(function ($b) {
            $arr = $b->toArray();

            return [
                'id' => $arr['id'] ?? null,
                'hash' => $arr['external_id'] ?? $arr['hash'] ?? null,
                'title' => $arr['title'] ?? $arr['name'] ?? null,
                'price' => isset($arr['price']) ? floatval($arr['price']) : (isset($arr['original_price']) ? floatval($arr['original_price']) : 0.0),
                'original_price' => isset($arr['original_price']) ? floatval($arr['original_price']) : (isset($arr['price']) ? floatval($arr['price']) : 0.0),
                'price_id' => $arr['price_id'] ?? null,
                'recurring' => $arr['recurring'] ?? null,
                // Do not mark bumps as selected by default in the UI; user must opt-in
                'active' => isset($arr['active']) ? (bool)$arr['active'] : false,
                'recommended' => isset($arr['recommended']) ? (bool)$arr['recommended'] : false,
                'description' => $arr['description'] ?? null,
                'payment_method' => $arr['payment_method'] ?? 'card',
            ];
        })->toArray();
    }
}
