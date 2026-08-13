<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Idle Timeout
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) a session may go without a real refresh before
    | it is treated as expired, even if it is still within the absolute TTL
    | below. Updated on every successful token rotation — see
    | AuthSessionService::touch().
    |
    */

    'idle_timeout_seconds' => (int) env('AUTH_SESSION_IDLE_TIMEOUT_SECONDS', 7200),

    /*
    |--------------------------------------------------------------------------
    | Absolute Session Lifetime
    |--------------------------------------------------------------------------
    |
    | The hard outer cap on a session's life, in days, measured from
    | creation and never extended by activity. Enforced by Redis's own key
    | TTL, independently of the idle-timeout check above.
    |
    */

    'absolute_ttl_days' => (int) env('AUTH_SESSION_ABSOLUTE_TTL_DAYS', 7),

];
