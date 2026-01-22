<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'customer_name',
        'plan',
        'currency',
        'price',
        'pix_id',
        'external_payment_id',
        'payment_status',
        'status',
        'paid_at',
    ];

    /**
     * The attributes that should be cast
     */
    protected $casts = [
        'price' => 'decimal:2',
        'paid_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user associated with this order
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
