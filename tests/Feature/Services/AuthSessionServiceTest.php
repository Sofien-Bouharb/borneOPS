<?php

namespace Tests\Feature\Services;

use App\Services\AuthSessionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Tests\Concerns\InteractsWithTestRedis;
use Tests\TestCase;

class AuthSessionServiceTest extends TestCase
{
    use InteractsWithTestRedis;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_session_is_valid_immediately_after_creation(): void
    {
        $service = $this->app->make(AuthSessionService::class);

        $sessionId = $service->create(userId: 1, refreshTokenId: 'token-1', sessionVersion: 0);

        $this->assertTrue($service->isValid($sessionId, 0));
    }

    public function test_session_becomes_invalid_after_exceeding_the_idle_timeout(): void
    {
        config(['auth_session.idle_timeout_seconds' => 7200]);

        $service = $this->app->make(AuthSessionService::class);

        Carbon::setTestNow('2026-01-01 10:00:00');
        $sessionId = $service->create(userId: 1, refreshTokenId: 'token-1', sessionVersion: 0);

        Carbon::setTestNow('2026-01-01 12:00:01');

        $this->assertFalse($service->isValid($sessionId, 0));
    }

    public function test_session_stays_valid_right_up_to_the_idle_timeout_boundary(): void
    {
        config(['auth_session.idle_timeout_seconds' => 7200]);

        $service = $this->app->make(AuthSessionService::class);

        Carbon::setTestNow('2026-01-01 10:00:00');
        $sessionId = $service->create(userId: 1, refreshTokenId: 'token-1', sessionVersion: 0);

        Carbon::setTestNow('2026-01-01 12:00:00');

        $this->assertTrue($service->isValid($sessionId, 0));
    }

    public function test_touch_resets_the_idle_clock_without_extending_the_absolute_ttl(): void
    {
        config(['auth_session.idle_timeout_seconds' => 7200]);

        $service = $this->app->make(AuthSessionService::class);

        Carbon::setTestNow('2026-01-01 10:00:00');
        $sessionId = $service->create(userId: 1, refreshTokenId: 'token-1', sessionVersion: 0);
        $ttlAfterCreate = Redis::ttl("session:{$sessionId}");

        Carbon::setTestNow('2026-01-01 11:59:00');
        $service->touch($sessionId);
        $ttlAfterTouch = Redis::ttl("session:{$sessionId}");

        // The idle clock reset, so the session survives past the point it
        // would otherwise have gone idle...
        Carbon::setTestNow('2026-01-01 13:00:00');
        $this->assertTrue($service->isValid($sessionId, 0));

        // ...but touch() must never re-issue the Redis TTL — the absolute
        // cap is fixed from creation and is not supposed to slide forward.
        $this->assertLessThanOrEqual($ttlAfterCreate, $ttlAfterTouch);
    }

    public function test_list_for_user_excludes_idle_sessions(): void
    {
        config(['auth_session.idle_timeout_seconds' => 7200]);

        $service = $this->app->make(AuthSessionService::class);

        Carbon::setTestNow('2026-01-01 10:00:00');
        $activeSessionId = $service->create(userId: 1, refreshTokenId: 'token-active', sessionVersion: 0);
        $idleSessionId = $service->create(userId: 1, refreshTokenId: 'token-idle', sessionVersion: 0);

        Carbon::setTestNow('2026-01-01 11:00:00');
        $service->touch($activeSessionId);

        Carbon::setTestNow('2026-01-01 12:30:00');

        $sessions = $service->listForUser(1, 0);
        $sessionIds = array_column($sessions, 'id');

        $this->assertContains($activeSessionId, $sessionIds);
        $this->assertNotContains($idleSessionId, $sessionIds);
    }

    public function test_session_ttl_matches_configured_absolute_lifetime(): void
    {
        config(['auth_session.absolute_ttl_days' => 7]);

        $service = $this->app->make(AuthSessionService::class);

        $sessionId = $service->create(userId: 1, refreshTokenId: 'token-1', sessionVersion: 0);

        $ttl = Redis::ttl("session:{$sessionId}");

        $this->assertGreaterThan(6 * 86400, $ttl);
        $this->assertLessThanOrEqual(7 * 86400, $ttl);
    }
}
