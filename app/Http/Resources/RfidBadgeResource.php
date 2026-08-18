<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class RfidBadgeResource extends JsonResource
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
            'identifier_hint' => $this->identifier_hint,
            'label' => $this->label,
            'administrative_status' => $this->administrative_status,
            'expires_at' => $this->expires_at,
            'activated_at' => $this->activated_at,
            'blocked_at' => $this->blocked_at,
            'user' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn () => [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ]
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
