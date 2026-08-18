<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class RfidBadgeHistoryResource extends JsonResource
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
            'rfid_badge_id' => $this->rfid_badge_id,
            'event_type' => $this->event_type,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'source' => $this->source,
            'performed_by' => $this->when(
                $this->relationLoaded('performedBy') && $this->performedBy,
                fn () => [
                    'id' => $this->performedBy->id,
                    'name' => $this->performedBy->name,
                    'email' => $this->performedBy->email,
                ]
            ),
            'created_at' => $this->created_at,
        ];
    }
}
