<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class AuthSessionService
{
    public function create(int $userId, string $refreshTokenId, int $sessionVersion): string
    {
        $sessionId = (string) Str::uuid();
        $now = now()->toISOString();

        Redis::hset("session:{$sessionId}", [
            'user_id' => $userId,
            'refresh_token_id' => $refreshTokenId,
            'session_version' => $sessionVersion,
            'revoked' => 'false',
            'created_at' => $now,
            'last_used_at' => $now,
        ]);

        Redis::expire("session:{$sessionId}", $this->ttlSeconds());

        // Index this session under the user so listForUser() can find it later.
        Redis::sadd("user_sessions:{$userId}", $sessionId);
        Redis::expire("user_sessions:{$userId}", $this->ttlSeconds());

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

    /**
     * Records real activity on a session (a successful token rotation).
     * Deliberately does NOT extend the session's Redis TTL — the absolute
     * lifetime cap (config('auth_session.absolute_ttl_days')) is fixed from
     * creation and never slides, by design; only the idle-timeout clock
     * tracked here resets.
     */
    public function touch(string $sessionId): void
    {
        Redis::hset("session:{$sessionId}", 'last_used_at', now()->toISOString());
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

        if ($this->isIdle($session)) {
            return false;
        }

        return true;
    }

    /**
     * List all live sessions belonging to a user, most recent first.
     *
     * Filters out revoked sessions, stale ones (session_version no longer
     * matches, e.g. after a logout-all or password reset), and idle ones
     * (no real activity within config('auth_session.idle_timeout_seconds')).
     * Also self-heals the index: if a session's Redis key has already
     * expired (TTL passed) but its UUID is still sitting in the
     * user_sessions set, that stale UUID is removed from the set here
     * rather than being returned.
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

            if ($this->isIdle($session)) {
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

    /**
     * Compares raw Unix timestamps rather than Carbon::diffInSeconds() —
     * deliberately, to avoid any ambiguity around that method's sign/
     * absolute-value behavior. A plain integer subtraction is unambiguous
     * and trivially correct to test.
     */
    protected function isIdle(array $session): bool
    {
        $lastUsedAt = $session['last_used_at'] ?? $session['created_at'];
        $lastUsedTimestamp = \Carbon\Carbon::parse($lastUsedAt)->getTimestamp();
        $elapsedSeconds = now()->getTimestamp() - $lastUsedTimestamp;

        return $elapsedSeconds > config('auth_session.idle_timeout_seconds');
    }

    protected function ttlSeconds(): int
    {
        return config('auth_session.absolute_ttl_days') * 86400;
    }
}
