<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChargingSessionEvent extends Model
{
    use HasFactory;

    /**
     * Append-only lifecycle trace (Module 5 roadmap §3.12). Rows are never
     * updated after creation, so there is no updated_at column and Eloquent's
     * automatic timestamp management is disabled here. created_at is still
     * set automatically at the database level via useCurrent() in the
     * migration, and Eloquent also populates it on create() as normal.
     */
    public $timestamps = false;

    protected $fillable = [
        'charging_session_id',
        'event_type',
        'from_status',
        'to_status',
        'meter_value_wh',
        'reason_code',
        'reason_detail',
        'source',
        'performed_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meter_value_wh' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function chargingSession(): BelongsTo
    {
        return $this->belongsTo(ChargingSession::class);
    }

    public function performedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
