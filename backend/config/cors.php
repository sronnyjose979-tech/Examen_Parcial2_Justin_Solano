<?php

/*
| Examen II — orígenes del cliente móvil agregados al CORS del Examen I.
|   http://localhost:8100   → ionic serve / npm run dev en el navegador
|   https://localhost       → WebView de Capacitor en Android (androidScheme https)
|   capacitor://localhost   → WebView de Capacitor en iOS
| CORS no es un control de autorización: solo le dice al navegador qué orígenes pueden leer la respuesta.
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:8100',
        'http://127.0.0.1:8100',
        'https://localhost',
        'capacitor://localhost',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept'],

    'exposed_headers' => ['Location'],

    'max_age' => 0,

    // Se usa token Bearer, no cookies
    'supports_credentials' => false,

];
