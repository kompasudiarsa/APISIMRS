<?php

return [
    'client_token' => env('API_CLIENT_TOKEN', ''),

    'client_secret' => env('API_CLIENT_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Toleransi timestamp
    |--------------------------------------------------------------------------
    |
    | Nilai dalam detik. Default 300 detik atau 5 menit.
    |
    */
    'timestamp_tolerance' => (int) env(
        'API_TIMESTAMP_TOLERANCE',
        300
    ),
];