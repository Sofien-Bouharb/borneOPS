<?php

namespace Tests\Feature\Services;

use App\Services\AuthSessionService;
use App\Services\RefreshTokenService;
use Tests\Concerns\InteractsWithTestRedis;
use Tests\TestCase;

class RefreshTokenServiceTest extends TestCase
{
    use InteractsWithTestRedis;

    public function test_issue_creates_a_valid_session_and_returns_a_token_pair(): void
    {
        $service = $this->app->make(RefreshTokenService::class);
        $sessionService = $this->app->make(AuthSessionService::class);

        $result = $service->issue(userId: 1, sessionVersion: 0);

        $this->assertArrayHasKey('session_id', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertTrue($sessionService->isValid($result['session_id'], 0));
    }

    public function test_rotate_succeeds_with_the_correct_token_and_issues_a_new_one(): void
    {
        $service = $this->app->make(RefreshTokenService::class);

        $issued = $service->issue(userId: 1, sessionVersion: 0);

        $rotated = $service->rotate(
            $issued['session_id'],
            $issued['refresh_token'],
            0
        );

        $this->assertNotNull($rotated);
        $this->assertSame($issued['session_id'], $rotated['session_id']);
        $this->assertNotSame($issued['refresh_token'], $rotated['refresh_token']);
    }

    public function test_rotate_fails_and_kills_the_session_when_the_token_is_stale(): void
    {
        $service = $this->app->make(RefreshTokenService::class);
        $sessionService = $this->app->make(AuthSessionService::class);

        $issued = $service->issue(userId: 1, sessionVersion: 0);

        // Simulate a real refresh happening first (rotation moves the token forward)...
        $service->rotate($issued['session_id'], $issued['refresh_token'], 0);

        // ...then someone (or something stolen) tries to reuse the now-stale original token.
        $result = $service->rotate($issued['session_id'], $issued['refresh_token'], 0);

        $this->assertNull($result);
        $this->assertFalse($sessionService->isValid($issued['session_id'], 0));
    }

    public function test_after_a_mismatch_even_the_latest_valid_token_stops_working(): void
    {
        $service = $this->app->make(RefreshTokenService::class);

        $issued = $service->issue(userId: 1, sessionVersion: 0);
        $rotated = $service->rotate($issued['session_id'], $issued['refresh_token'], 0);

        // Trigger the mismatch using the stale original token — this should kill the whole session.
        $service->rotate($issued['session_id'], $issued['refresh_token'], 0);

        // Now even the token that WAS valid a moment ago should no longer work,
        // because the entire session was revoked, not just the bad request rejected.
        $stillDead = $service->rotate($issued['session_id'], $rotated['refresh_token'], 0);

        $this->assertNull($stillDead);
    }
}
