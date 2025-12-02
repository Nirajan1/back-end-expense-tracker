<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{

    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'type',
        'user_id',
        'client_updated_at',
    ];

    protected $casts = [
        'uuid' => 'string',
        'client_updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];


    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
