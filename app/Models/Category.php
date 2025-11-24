<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',     // 'INCOME' or 'EXPENSE'
        'user_id',
        'is_global',
    ];

    public function user(): BelongsTo
    {
        return $this->BelongsTo(User::class);
    }
    protected $casts = [
        'is_global' => 'boolean'
    ];
    // A category can have many transactions.
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
