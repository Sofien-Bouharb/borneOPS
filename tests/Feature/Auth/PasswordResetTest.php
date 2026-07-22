<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\AuthSessionService;
use App\Services\RefreshTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\InteractsWithTestRedis;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithTestRedis;

    public function test_forgot_password_sends_reset_notification_for_a_real_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200);
        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_returns_the_same_generic_message_for_an_unknown_email(): void
{
    Notification::fake();

    $knownUser = User::factory()->create();

    $knownResponse = $this->postJson('/api/forgot-password', [
        'email' => $knownUser->email,
    ]);

    $unknownResponse = $this->postJson('/api/forgot-password', [
        'email' => 'nobody@example.com',
    ]);

    $this->assertSame($knownResponse->status(), $unknownResponse->status());
    $this->assertSame($knownResponse->json('message'), $unknownResponse->json('message'));
}
    public function test_reset_password_with_a_valid_token_succeeds_and_changes_the_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password', $user->password));
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('old-password', $user->password));
        $this->assertNotNull($user->password_changed_at);
    }

    public function test_reset_password_with_an_invalid_token_fails(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => 'not-a-real-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(400);
    }

    public function test_reset_password_invalidates_every_other_active_session(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        $refreshTokenService = $this->app->make(RefreshTokenService::class);
        $sessionService = $this->app->make(AuthSessionService::class);

        $deviceA = $refreshTokenService->issue($user->id, $user->session_version);
        $deviceB = $refreshTokenService->issue($user->id, $user->session_version);

        $token = Password::createToken($user);

        $this->postJson('/api/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertStatus(200);

        $user->refresh();

        $this->assertFalse($sessionService->isValid($deviceA['session_id'], $user->session_version));
        $this->assertFalse($sessionService->isValid($deviceB['session_id'], $user->session_version));
    }
}
