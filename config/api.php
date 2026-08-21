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
    ],

];
