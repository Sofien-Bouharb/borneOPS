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
            'created_at' => now()->toISOString(),
        ]);

        Redis::expire("session:{$sessionId}", $this->ttlSeconds);

        // Index this session under the user so listForUser() can find it later.
        Redis::sadd("user_sessions:{$userId}", $sessionId);
        Redis::expire("user_sessions:{$userId}", $this->ttlSeconds);

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

    /**
     * List all live sessions belonging to a user, most recent first.
     *
     * Filters out revoked sessions and stale ones (session_version no longer
     * matches, e.g. after a logout-all or password reset). Also self-heals
     * the index: if a session's Redis key has already expired (TTL passed)
     * but its UUID is still sitting in the user_sessions set, that stale
     * UUID is removed from the set here rather than being returned.
     */
    public function listForUser(int $userId, int $currentSessionVersion): array
    {
        $sessionIds = Redis::smembers("user_sessions:{$userId}");
        $sessions = [];

        foreach ($sessionIds as $sessionId) {
            $session = $this->find($sessionId);

            if ($session === null) {
                Redis::srem("user_sessions:{$userId}", $sessionId);
                continue;
            }

            if ($session['revoked'] === 'true') {
                continue;
            }

            if ((int) $session['session_version'] !== $currentSessionVersion) {
                continue;
            }

            $sessions[] = [
                'id' => $sessionId,
                'created_at' => $session['created_at'],
            ];
        }

        usort($sessions, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $sessions;
    }
}
