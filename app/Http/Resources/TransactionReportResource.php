<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionReportResource extends JsonResource
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
            'category' => $this->category->name,
            'payment_method' => $this->paymentMethod->name,
            'transaction_amount' => $this->transaction_amount,
            'transaction_type' => $this->transaction_type,
            'transaction_date' => $this->transaction_date,
            'client_updated_at' => $this->client_updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
    //summary

}
