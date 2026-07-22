<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\Concerns\InteractsWithTestRedis;
use Tests\TestCase;

class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithTestRedis;

    public function test_account_locks_after_max_failed_attempts(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertStatus(401);
        }

        $user->refresh();

        $this->assertEquals(5, $user->failed_login_attempts);
        $this->assertNotNull($user->locked_until);
        $this->assertTrue($user->locked_until->isFuture());
    }

    public function test_correct_password_still_rejected_while_locked(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertStatus(401);
    }

    public function test_login_succeeds_and_resets_counters_after_lock_expires(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(15),
        ]);

        $this->travel(16)->minutes();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ])->assertStatus(200);

        $user->refresh();
        $this->assertEquals(0, $user->failed_login_attempts);
        $this->assertNull($user->locked_until);
    }
}
