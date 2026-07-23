<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithTestRedis;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithTestRedis;

    public function test_correct_credentials_return_access_token_and_cookies(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token'])
            ->assertCookie('refresh_token')
            ->assertCookie('session_id');
    }

    public function test_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_unknown_email_returns_the_same_response_as_wrong_password(): void
    {
        $unknownEmailResponse = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ]);

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);
        $wrongPasswordResponse = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $unknownEmailResponse->assertStatus($wrongPasswordResponse->status());
        $this->assertSame(
            $unknownEmailResponse->json('message'),
            $wrongPasswordResponse->json('message')
        );
    }
}
