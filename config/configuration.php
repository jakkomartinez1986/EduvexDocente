<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Versión del esquema de configuración
    |--------------------------------------------------------------------------
    |
    | Identifica la estructura del payload de GET /api/v1/configuration.
    | El cliente Kotlin la usa para invalidar su caché local cuando el
    | contrato cambia de forma incompatible. Súbela junto con el cambio
    | documentado en docs/api/configuration.md.
    |
    */

    'schema_version' => env('API_CONFIGURATION_SCHEMA_VERSION', '1.0'),

    /*
    |--------------------------------------------------------------------------
    | Capacidades del cliente oficial
    |--------------------------------------------------------------------------
    |
    | Banderas opcionales controladas por el servidor que habilitan features
    | en las apps cliente sin publicar una nueva versión. Vacío por defecto;
    | ver docs/api/configuration.md para las banderas soportadas.
    |
    */

    'client' => [
        'features' => [
            // 'offline_gradebook' => true,
        ],
    ],

];
