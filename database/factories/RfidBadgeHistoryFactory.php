<?php

namespace Database\Factories;

use App\Models\RfidBadge;
use App\Models\RfidBadgeHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

class RfidBadgeHistoryFactory extends Factory
{
    protected $model = RfidBadgeHistory::class;

    public function definition(): array
    {
        return [
            'rfid_badge_id' => RfidBadge::factory(),
            'event_type' => 'created',
            'old_values' => null,
            'new_values' => null,
            'source' => 'user',
            'performed_by' => null,
        ];
    }
}
