<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChargingStation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'reference',
        'serial_number',
        'model',
        'manufacturer',
        'address',
        'latitude',
        'longitude',
        'firmware_version',
        'ocpp_version',
        'ocpp_identifier',
        'declared_connector_count',
        'power_kw',
        'operational_status',
        'administrative_status',
        'site_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'power_kw' => 'decimal:2',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ChargingStationHistory::class);
    }
}
