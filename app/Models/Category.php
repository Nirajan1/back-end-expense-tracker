<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'name',
        'user_id',
        'is_global',
        'client_updated_at',
    ];

    public function user(): BelongsTo
    {
        return $this->BelongsTo(User::class);
    }
    protected $casts = [
        'uuid' => 'string',
        'is_global' => 'boolean',
        'client_updated_at' => 'datetime',
    ];
    // A category can have many transactions.
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
