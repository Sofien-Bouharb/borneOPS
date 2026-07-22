<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\AuthenticationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\Concerns\InteractsWithTestRedis;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithTestRedis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // --- Mandatory enrollment (privileged user, 2FA not yet set up) ---

    public function test_privileged_user_without_2fa_is_forced_into_enrollment(): void
    {
        $service = $this->app->make(AuthenticationService::class);

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'two_factor_enabled' => false,
        ]);
        $user->assignRole('Exploitant');

        $result = $service->attempt($user->email, 'correct-password');

        $this->assertSame('requires_2fa_setup', $result['status']);
        $this->assertArrayHasKey('secret', $result);
        $this->assertArrayHasKey('enrollment_id', $result);
        $this->assertArrayHasKey('otpauth_url', $result);
    }

    public function test_completing_enrollment_with_correct_code_enables_2fa(): void
    {
        $service = $this->app->make(AuthenticationService::class);
        $google2fa = new Google2FA();

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'two_factor_enabled' => false,
        ]);
        $user->assignRole('Exploitant');

        $enrollment = $service->attempt($user->email, 'correct-password');
        $validCode = $google2fa->getCurrentOtp($enrollment['secret']);

        $result = $service->completeTwoFactorEnrollment($enrollment['enrollment_id'], $validCode);

        $this->assertSame('success', $result['status']);

        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
        $this->assertNotNull($user->two_factor_secret);
    }

    public function test_completing_enrollment_with_wrong_code_fails_and_does_not_enable_2fa(): void
    {
        $service = $this->app->make(AuthenticationService::class);

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'two_factor_enabled' => false,
        ]);
        $user->assignRole('Exploitant');

        $enrollment = $service->attempt($user->email, 'correct-password');

        $result = $service->completeTwoFactorEnrollment($enrollment['enrollment_id'], '000000');

        $this->assertSame('failed', $result['status']);

        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
    }

    public function test_enrollment_id_is_single_use(): void
    {
        $service = $this->app->make(AuthenticationService::class);
        $google2fa = new Google2FA();

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'two_factor_enabled' => false,
        ]);
        $user->assignRole('Exploitant');

        $enrollment = $service->attempt($user->email, 'correct-password');
        $validCode = $google2fa->getCurrentOtp($enrollment['secret']);

        $service->completeTwoFactorEnrollment($enrollment['enrollment_id'], $validCode);

        // Reusing the same enrollment_id after it already succeeded should fail —
        // the Redis key was deleted on first successful use.
        $secondAttempt = $service->completeTwoFactorEnrollment($enrollment['enrollment_id'], $validCode);

        $this->assertSame('failed', $secondAttempt['status']);
    }

    // --- Challenge flow (privileged user, 2FA already enrolled) ---

    public function test_privileged_user_with_2fa_enabled_gets_a_challenge(): void
    {
        $service = $this->app->make(AuthenticationService::class);
        $secret = (new Google2FA())->generateSecretKey();

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);
        $user->assignRole('Finance');

        $result = $service->attempt($user->email, 'correct-password');

        $this->assertSame('requires_2fa', $result['status']);
        $this->assertArrayHasKey('challenge_id', $result);
    }

    public function test_verify_two_factor_with_correct_code_issues_a_session(): void
    {
        $service = $this->app->make(AuthenticationService::class);
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);
        $user->assignRole('Finance');

        $challenge = $service->attempt($user->email, 'correct-password');
        $validCode = $google2fa->getCurrentOtp($secret);

        $result = $service->verifyTwoFactor($challenge['challenge_id'], $validCode);

        $this->assertSame('success', $result['status']);
    }

    public function test_verify_two_factor_with_wrong_code_fails(): void
    {
        $service = $this->app->make(AuthenticationService::class);
        $secret = (new Google2FA())->generateSecretKey();

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);
        $user->assignRole('Finance');

        $challenge = $service->attempt($user->email, 'correct-password');

        $result = $service->verifyTwoFactor($challenge['challenge_id'], '000000');

        $this->assertSame('failed', $result['status']);
    }

    public function test_challenge_id_is_single_use(): void
    {
        $service = $this->app->make(AuthenticationService::class);
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);
        $user->assignRole('Finance');

        $challenge = $service->attempt($user->email, 'correct-password');
        $validCode = $google2fa->getCurrentOtp($secret);

        $service->verifyTwoFactor($challenge['challenge_id'], $validCode);

        // Reusing the same challenge_id should fail — it was deleted from Redis on first success.
        $secondAttempt = $service->verifyTwoFactor($challenge['challenge_id'], $validCode);

        $this->assertSame('failed', $secondAttempt['status']);
    }

    // --- Non-privileged accounts should never be touched by any of this ---

    public function test_non_privileged_user_logs_in_directly_without_2fa(): void
    {
        $service = $this->app->make(AuthenticationService::class);

        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
            'two_factor_enabled' => false,
        ]);
        $user->assignRole('Client');

        $result = $service->attempt($user->email, 'correct-password');

        $this->assertSame('success', $result['status']);
    }

    public function test_two_factor_secret_never_appears_in_serialized_user_output(): void
{
    $user = User::factory()->create([
        'two_factor_enabled' => true,
        'two_factor_secret' => 'SOMESECRETVALUE',
    ]);

    $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
}
}
