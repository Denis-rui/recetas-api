<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Límite de solicitudes asíncronas para el listado de usuarios
    |--------------------------------------------------------------------------
    |
    | Define la cantidad máxima de consultas asíncronas por minuto que puede
    | realizar un administrador autenticado sobre la tabla de usuarios.
    |
    */
    'rate_limit' => (int) env('USUARIOS_LISTADO_RATE_LIMIT', 60),
];

