<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;

class RefreshTokenService
{
    public function __construct(
        protected AuthSessionService $authSessionService
    ) {}


public function issue(int $userId, int $sessionVersion): array
{
    $refreshToken = Str::random(64);

    $sessionId = $this->authSessionService->create(
        $userId,
        $refreshToken,
        $sessionVersion
    );

    return [
        'session_id' => $sessionId,
        'refresh_token' => $refreshToken,
    ];
}

public function rotate(string $sessionId, string $presentedToken, int $currentSessionVersion): ?array
{
    $session = $this->authSessionService->find($sessionId);

    if ($session === null) {
        return null;
    }

    if (! $this->authSessionService->isValid($sessionId, $currentSessionVersion)) {
        return null;
    }

    if (! hash_equals($session['refresh_token_id'], $presentedToken)) {
        $this->authSessionService->revoke($sessionId);

        // audit log call goes here once AuditService exists — event: refresh_token_mismatch

        return null;
    }

    $newRefreshToken = Str::random(64);

    Redis::hset("session:{$sessionId}", 'refresh_token_id', $newRefreshToken);

    return [
        'session_id' => $sessionId,
        'refresh_token' => $newRefreshToken,
    ];
}


}


