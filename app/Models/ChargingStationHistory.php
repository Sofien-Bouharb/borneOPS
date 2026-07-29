<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargingStationHistory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'charging_station_id',
        'event_type',
        'old_values',
        'new_values',
        'reason',
        'comment',
        'source',
        'performed_by',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function chargingStation(): BelongsTo
    {
        return $this->belongsTo(ChargingStation::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
