<?php

return [
    'store' => env('HERD_STORE', 'demo-file'),
    'demo_path' => storage_path('app/private/herd-demo.json'),
    'cors_origins' => array_values(array_filter(array_map('trim', explode(',',
        env('HERD_CORS_ORIGINS', 'http://localhost:4173,http://127.0.0.1:4173')
    )))),
];
