<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfidBadgeHistory extends Model
{
    use HasFactory;

    public $timestamps = false;

    const UPDATED_AT = null;

    protected $fillable = [
        'rfid_badge_id',
        'event_type',
        'old_values',
        'new_values',
        'source',
        'performed_by',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function rfidBadge(): BelongsTo
    {
        return $this->belongsTo(RfidBadge::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
