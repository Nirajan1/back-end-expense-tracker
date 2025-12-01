<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'uuid',
        'user_id',
        'category_id',
        'payment_method_id',
        'transaction_amount',
        'transaction_type',
        'transaction_date',
        'client_updated_at',
    ];
    // Casts for proper data types
    protected $casts = [
        'uuid' => 'string',
        'transaction_date' => 'date',
        'transaction_amount' => 'decimal:2',
        'client_updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    protected static function booted()
    {

        static::creating(function ($transaction) {
            if (empty($transaction->uuid)) {
                $transaction->uuid = Str::uuid();
            }
        });
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
