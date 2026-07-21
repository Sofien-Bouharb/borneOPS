<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\AuthSessionService;
use App\Services\RefreshTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(
        protected AuthenticationService $authenticationService,
        protected RefreshTokenService $refreshTokenService,
        protected AuthSessionService $authSessionService
    ) {}

    public function login(LoginRequest $request)
    {
        $result = $this->authenticationService->attempt(
            $request->string('email'),
            $request->string('password')
        );

        return match ($result['status']) {
            'failed' => response()->json([
                'message' => 'These credentials do not match our records.',
            ], 401),

            'requires_2fa' => response()->json([
                'requires_2fa' => true,
                'challenge_id' => $result['challenge_id'],
            ]),

            'requires_2fa_setup' => response()->json([
                'requires_2fa_setup' => true,
                'enrollment_id' => $result['enrollment_id'],
                'secret' => $result['secret'],
                'otpauth_url' => $result['otpauth_url'],
            ]),

            'success' => $this->respondWithSession($result),
        };
    }

    public function verifyTwoFactor(Request $request)
    {
        $request->validate([
            'challenge_id' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $result = $this->authenticationService->verifyTwoFactor(
            $request->string('challenge_id'),
            $request->string('code')
        );

        if ($result['status'] === 'failed') {
            return response()->json(['message' => 'Invalid or expired code.'], 401);
        }

        return $this->respondWithSession($result);
    }

    public function completeTwoFactorEnrollment(Request $request)
    {
        $request->validate([
            'enrollment_id' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $result = $this->authenticationService->completeTwoFactorEnrollment(
            $request->string('enrollment_id'),
            $request->string('code')
        );

        if ($result['status'] === 'failed') {
            return response()->json(['message' => 'Invalid or expired code.'], 401);
        }

        return $this->respondWithSession($result);
    }

    protected function respondWithSession(array $result)
    {
        $accessToken = JWTAuth::fromUser($result['user']);

        return response()->json([
            'access_token' => $accessToken,
            'user' => $result['user'],
        ])->withCookie(Cookie::make(
            'refresh_token', $result['refresh_token'], 60 * 24 * 14, httpOnly: true
        ))->withCookie(Cookie::make(
            'session_id', $result['session_id'], 60 * 24 * 14, httpOnly: true
        ));
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }

    public function refresh(Request $request)
    {
        $sessionId = $request->cookie('session_id');
        $presentedToken = $request->cookie('refresh_token');

        if ($sessionId === null || $presentedToken === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $session = $this->authSessionService->find($sessionId);

        if ($session === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::find($session['user_id']);

        $rotated = $this->refreshTokenService->rotate(
            $sessionId,
            $presentedToken,
            $user->session_version
        );

        if ($rotated === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401)
                ->withoutCookie('refresh_token')
                ->withoutCookie('session_id');
        }

        $accessToken = JWTAuth::fromUser($user);

        return response()->json([
            'access_token' => $accessToken,
        ])->withCookie(Cookie::make(
            'refresh_token', $rotated['refresh_token'], 60 * 24 * 14, httpOnly: true
        ))->withCookie(Cookie::make(
            'session_id', $rotated['session_id'], 60 * 24 * 14, httpOnly: true
        ));
    }

    public function logout(Request $request)
    {
        $sessionId = $request->cookie('session_id');

        if ($sessionId !== null) {
            $this->authSessionService->revoke($sessionId);
        }

        return response()->json(['message' => 'Logged out.'])
            ->withoutCookie('refresh_token')
            ->withoutCookie('session_id');
    }

    public function logoutAll(Request $request)
    {
        $user = auth('api')->user();

        $user->increment('session_version');

        return response()->json(['message' => 'Logged out of all sessions.'])
            ->withoutCookie('refresh_token')
            ->withoutCookie('session_id');
    }
}
