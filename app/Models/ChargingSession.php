<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChargingSession extends Model
{
    use HasFactory;

    /**
     * Sessions are never app-deletable (Module 5 roadmap §3.12). There is
     * deliberately no SoftDeletes trait here — a session's full lifecycle,
     * including cancellation, is recorded via status and charging_session_events
     * rather than by removing the row.
     */
    protected $fillable = [
        'charging_station_id',
        'connector_id',
        'customer_user_id',
        'status',
        'reason_code',
        'reason_detail',
        'meter_start_wh',
        'latest_meter_wh',
        'meter_stop_wh',
        'total_price',
        'currency',
        'ocpp_transaction_id',
        'started_at',
        'paused_at',
        'total_paused_seconds',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'meter_start_wh' => 'integer',
            'latest_meter_wh' => 'integer',
            'meter_stop_wh' => 'integer',
            'total_price' => 'decimal:3',
            'total_paused_seconds' => 'integer',
            'started_at' => 'datetime',
            'paused_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function chargingStation(): BelongsTo
    {
        return $this->belongsTo(ChargingStation::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ChargingSessionEvent::class);
    }
}
