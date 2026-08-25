<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token de acceso
    |--------------------------------------------------------------------------
    |
    | Configuración de los tokens personales emitidos mediante Sanctum.
    | `ttl_minutes` define la expiración por defecto de un token de la API.
    |
    */

    'token' => [
        'name' => env('API_TOKEN_NAME', 'api-access-token'),
        'ttl_minutes' => (int) env('API_TOKEN_TTL_MINUTES', 1440),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    |
    | Límites de peticiones por minuto. `login_per_minute` protege el
    | endpoint público de autenticación; se aplica por usuario o IP.
    |
    */

    'rate_limit' => [
        'login_per_minute' => (int) env('API_RATE_LIMIT_LOGIN_PER_MINUTE', 5),
        // Throttle global de la API v1 por token (o IP si no autenticado), §8.4.
        'v1_per_minute' => (int) env('API_RATE_LIMIT_V1_PER_MINUTE', 120),
        // Throttle específico de POST /sync/push: el cliente trocea lotes,
        // 60 requests/min es holgado para sync real y contiene abusos.
        'sync_push_per_minute' => (int) env('API_RATE_LIMIT_SYNC_PUSH_PER_MINUTE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Estudiantes
    |--------------------------------------------------------------------------
    |
    | Tamaño de página de GET /students (paginación por cursor, D-07).
    |
    */

    'students' => [
        'page_size' => (int) env('API_STUDENTS_PAGE_SIZE', 200),
    ],

];
