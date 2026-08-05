<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Heartbeat Timeout
    |--------------------------------------------------------------------------
    |
    | The number of seconds since a charging station's last_heartbeat_at
    | timestamp after which the station is considered disconnected.
    |
    | A station is connected when:
    |   last_heartbeat_at IS NOT NULL
    |   AND last_heartbeat_at >= now() - heartbeat_timeout_seconds
    |
    | The boundary case (heartbeat exactly at the cutoff) counts as connected.
    |
    */

    'heartbeat_timeout_seconds' => (int) env('STATION_HEARTBEAT_TIMEOUT_SECONDS', 120),

];
