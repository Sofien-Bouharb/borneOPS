<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'source' => $this->source,
            'performed_by' => new UserResource($this->whenLoaded('performedBy')),
            'created_at' => $this->created_at,
        ];
    }
}
