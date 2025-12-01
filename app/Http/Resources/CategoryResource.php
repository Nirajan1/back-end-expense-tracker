<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            'name' => $this->name,
            'is_global' => $this->is_global,

            'client_updated_at'  => $this->client_updated_at?->toDateTimeString(),

            // Server timestamps (important for sync)
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            // Soft-delete timestamp
            'deleted_at' => $this->deleted_at?->toDateTimeString(),

        ];
    }
}
