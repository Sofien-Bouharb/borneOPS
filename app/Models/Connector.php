<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Connector extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * standard → current_type is a strict one-to-one mapping (Module 3 roadmap §5).
     * current_type is NEVER accepted from client input — the service derives it
     * from this map on both create and update.
     */
    public const CURRENT_TYPE_BY_STANDARD = [
        'type2' => 'ac',
        'ccs' => 'dc',
        'chademo' => 'dc',
    ];

    protected $fillable = [
        'charging_station_id',
        'connector_number',
        'standard',
        'current_type',
        'max_power_kw',
        'operational_status',
        'administrative_status',
    ];

    protected function casts(): array
    {
        return [
            'max_power_kw' => 'decimal:2',
            'deleted_at' => 'datetime',
        ];
    }

    public function chargingStation(): BelongsTo
    {
        return $this->belongsTo(ChargingStation::class);
    }
}
