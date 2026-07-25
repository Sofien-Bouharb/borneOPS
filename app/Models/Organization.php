<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'type',
        'contact_email',
        'contact_phone',
        'address',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
