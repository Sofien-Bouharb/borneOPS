<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RfidBadge extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'identifier_hash',
        'identifier_hint',
        'label',
        'administrative_status',
        'expires_at',
        'activated_at',
        'blocked_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'blocked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(RfidBadgeHistory::class);
    }

    public static function normalizeIdentifier(string $raw): string
    {
        return trim($raw);
    }

    public static function hashIdentifier(string $raw): string
    {
        return hash('sha256', self::normalizeIdentifier($raw));
    }

    public static function hintFor(string $raw): string
    {
        $normalized = self::normalizeIdentifier($raw);
        $suffix = mb_substr($normalized, -4);

        return str_repeat('*', max(mb_strlen($normalized) - mb_strlen($suffix), 0)) . $suffix;
    }
}
