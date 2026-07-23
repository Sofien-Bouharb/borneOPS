<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\AuthSessionService;
use App\Services\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTestRedis;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithTestRedis;

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertStatus(401);
    }

    public function test_logout_revokes_the_current_session_only(): void
    {
        $user = User::factory()->create();
        $refreshTokenService = $this->app->make(RefreshTokenService::class);
        $sessionService = $this->app->make(AuthSessionService::class);

        $deviceA = $refreshTokenService->issue($user->id, $user->session_version);
        $deviceB = $refreshTokenService->issue($user->id, $user->session_version);

        // Note: post() + explicit Accept header used instead of postJson(),
        // since postJson() does not forward request cookies in this Laravel version.
        $this->actingAs($user, 'api')
            ->withUnencryptedCookie('session_id', $deviceA['session_id'])
            ->post('/api/logout', [], ['Accept' => 'application/json'])
            ->assertStatus(200);

        $this->assertFalse($sessionService->isValid($deviceA['session_id'], $user->session_version));
        $this->assertTrue($sessionService->isValid($deviceB['session_id'], $user->session_version));
    }

    public function test_logout_all_kills_every_device_at_once(): void
    {
        $user = User::factory()->create();
        $refreshTokenService = $this->app->make(RefreshTokenService::class);
        $sessionService = $this->app->make(AuthSessionService::class);

        $deviceA = $refreshTokenService->issue($user->id, $user->session_version);
        $deviceB = $refreshTokenService->issue($user->id, $user->session_version);

        $this->actingAs($user, 'api')
            ->postJson('/api/logout-all')
            ->assertStatus(200);

        $user->refresh();

        $this->assertFalse($sessionService->isValid($deviceA['session_id'], $user->session_version));
        $this->assertFalse($sessionService->isValid($deviceB['session_id'], $user->session_version));
    }
}
