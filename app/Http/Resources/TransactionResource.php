<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'category' => $this->category ? [
                'id'        => $this->category->id,
                'name'      => $this->category->name,
                'is_global' => $this->category->is_global,
            ] : null,
            'payment_method' => $this->paymentMethod ? [
                'id'        => $this->paymentMethod->id,
                'name'      => $this->paymentMethod->name,
                'type'      => $this->paymentMethod->type,
            ] : null,
            'transaction_amount' => $this->transaction_amount,
            'transaction_date' => $this->transaction_date?->toDateTimeString(),
            'transaction_type' => $this->transaction_type,
            'client_updated_at'  => $this->client_updated_at?->toDateTimeString(),


            // Server timestamps (important for sync)
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            // Soft-delete timestamp
            'deleted_at' => $this->deleted_at?->toDateTimeString(),
        ];
    }
}
