<?php

return [
    /*
     * Accesos rápidos de prueba en el login (como en RIC Condominios).
     * null  = solo en desarrollo (APP_ENV=local) y entrando desde esta computadora (localhost).
     * true  = siempre (servidor de demostración).  false = nunca.
     */
    'accesos_demo' => env('ACCESOS_DEMO') === null ? null : filter_var(env('ACCESOS_DEMO'), FILTER_VALIDATE_BOOLEAN),
];
