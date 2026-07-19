<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class AuthSessionService
{
    protected int $ttlSeconds = 20160 * 60; // JWT_REFRESH_TTL in minutes, converted to seconds

    public function create(int $userId, string $refreshTokenId, int $sessionVersion): string
    {
        $sessionId = (string) Str::uuid();

        Redis::hset("session:{$sessionId}", [
            'user_id' => $userId,
            'refresh_token_id' => $refreshTokenId,
            'session_version' => $sessionVersion,
            'revoked' => 'false',
            'created_at' => now()->toDateTimeString(),
        ]);

        Redis::expire("session:{$sessionId}", $this->ttlSeconds);

        return $sessionId;
    }

    public function find(string $sessionId): ?array
{
    $data = Redis::hgetall("session:{$sessionId}");

    return empty($data) ? null : $data;
}

public function revoke(string $sessionId): void
{
    Redis::hset("session:{$sessionId}", 'revoked', 'true');
}

public function isValid(string $sessionId, int $currentSessionVersion): bool
{
    $session = $this->find($sessionId);

    if ($session === null) {
        return false;
    }

    if ($session['revoked'] === 'true') {
        return false;
    }

    if ((int) $session['session_version'] !== $currentSessionVersion) {
        return false;
    }

    return true;
}
}
