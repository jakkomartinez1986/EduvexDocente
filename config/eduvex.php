<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cedula del super administrador
    |--------------------------------------------------------------------------
    |
    | Cedula de la cuenta que recibe el rol SUPER-ADMIN al registrarse. Se
    | mantiene en la variable de entorno SUPER_ADMIN_DNI (fuera del
    | repositorio) para no publicar una credencial en el codigo fuente.
    |
    | Si la variable no esta definida o queda vacia, el registro publico no
    | genera ningun super administrador: el sistema arranca sin privileged
    | accounts y el alta se hace con `php artisan make:super-admin`.
    |
    */

    'super_admin_dni' => env('SUPER_ADMIN_DNI'),

];
