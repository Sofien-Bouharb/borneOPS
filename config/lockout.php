<?php

return [
    'max_attempts' => (int) env('LOCKOUT_MAX_ATTEMPTS', 5),
    'lockout_minutes' => (int) env('LOCKOUT_MINUTES', 15),
];
