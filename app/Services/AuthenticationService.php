<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redis;
use PragmaRX\Google2FA\Google2FA;

class AuthenticationService
{
    public function __construct(
        protected RefreshTokenService $refreshTokenService
    ) {}

    public function attempt(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if ($user === null) {
            return ['status' => 'failed'];
        }

        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            return ['status' => 'failed'];
        }

        if (! Hash::check($password, $user->password)) {
            $this->registerFailedAttempt($user);

            return ['status' => 'failed'];
        }

        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->last_login_at = now();
        $user->save();

        if ($this->mustEnrollTwoFactor($user)) {
            return $this->beginTwoFactorEnrollment($user);
        }

        if ($this->requiresTwoFactor($user)) {
            $challengeId = (string) Str::uuid();

            Redis::setex("2fa_challenge:{$challengeId}", 300, $user->id);

            return ['status' => 'requires_2fa', 'challenge_id' => $challengeId];
        }

        return $this->issueSession($user);
    }

    protected function isPrivilegedRole(User $user): bool
    {
        return $user->hasAnyRole(['Super Administrator', 'Exploitant', 'Finance']);
    }

    protected function requiresTwoFactor(User $user): bool
    {
        return $user->two_factor_enabled && $this->isPrivilegedRole($user);
    }

    protected function mustEnrollTwoFactor(User $user): bool
    {
        return ! $user->two_factor_enabled && $this->isPrivilegedRole($user);
    }

    protected function beginTwoFactorEnrollment(User $user): array
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        $enrollmentId = (string) Str::uuid();

        Redis::setex(
            "2fa_enrollment:{$enrollmentId}",
            600,
            json_encode(['user_id' => $user->id, 'secret' => $secret])
        );

        $otpauthUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return [
            'status' => 'requires_2fa_setup',
            'enrollment_id' => $enrollmentId,
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
        ];
    }

    public function completeTwoFactorEnrollment(string $enrollmentId, string $code): array
    {
        $data = Redis::get("2fa_enrollment:{$enrollmentId}");

        if ($data === null) {
            return ['status' => 'failed'];
        }

        $data = json_decode($data, true);

        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey($data['secret'], $code)) {
            return ['status' => 'failed'];
        }

        $user = User::find($data['user_id']);

        if ($user === null) {
            return ['status' => 'failed'];
        }

        $user->two_factor_secret = $data['secret'];
        $user->two_factor_enabled = true;
        $user->save();

        Redis::del("2fa_enrollment:{$enrollmentId}");

        return $this->issueSession($user);
    }

    public function verifyTwoFactor(string $challengeId, string $code): array
    {
        $userId = Redis::get("2fa_challenge:{$challengeId}");

        if ($userId === null) {
            return ['status' => 'failed'];
        }

        $user = User::find($userId);

        if ($user === null) {
            return ['status' => 'failed'];
        }

        $google2fa = new Google2FA();

        if (! $google2fa->verifyKey($user->two_factor_secret, $code)) {
            return ['status' => 'failed'];
        }

        Redis::del("2fa_challenge:{$challengeId}");

        return $this->issueSession($user);
    }

    protected function issueSession(User $user): array
    {
        $session = $this->refreshTokenService->issue(
            userId: $user->id,
            sessionVersion: $user->session_version
        );

        return [
            'status' => 'success',
            'user' => $user,
            'session_id' => $session['session_id'],
            'refresh_token' => $session['refresh_token'],
        ];
    }

    protected function registerFailedAttempt(User $user): void
    {
        $user->failed_login_attempts++;

        if ($user->failed_login_attempts >= config('lockout.max_attempts')) {
            $user->locked_until = now()->addMinutes(config('lockout.lockout_minutes'));
        }

        $user->save();
    }
}
