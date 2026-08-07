<?php
use Illuminate\Support\Facades\Broadcast;
use App\Models\User;

Broadcast::channel('supervision', function (User $user) {
    return $user->can('supervision.view');
});

Broadcast::channel('charging-sessions', function (User $user) {
    return $user->can('charging_sessions.view');
});
