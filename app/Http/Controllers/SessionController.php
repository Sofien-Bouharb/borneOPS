<?php

namespace App\Http\Controllers;

use App\Services\AuthSessionService;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    public function __construct(private AuthSessionService $sessions) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $currentSessionId = $request->cookie('session_id');

        $sessions = $this->sessions->listForUser($user->id, $user->session_version);

        // Flag which one is "this" session, so the frontend can label it
        $sessions = array_map(function ($s) use ($currentSessionId) {
            $s['is_current'] = $s['id'] === $currentSessionId;
            return $s;
        }, $sessions);

        return response()->json(['sessions' => $sessions]);
    }

    public function destroy(Request $request, string $sessionId)
    {
        $user = $request->user();

        // Confirm this session actually belongs to the requesting user
        // before revoking it — otherwise anyone could revoke anyone's session
        // just by guessing a UUID.
        $owned = collect($this->sessions->listForUser($user->id, $user->session_version))
            ->pluck('id')
            ->contains($sessionId);

        if (! $owned) {
            return response()->json(['message' => 'Session not found.'], 404);
        }

        $this->sessions->revoke($sessionId);

        return response()->json(['message' => 'Session revoked.']);
    }
}
