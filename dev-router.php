<?php
// Router para el servidor embebido de PHP (vista previa local):
//   php -S 127.0.0.1:8050 C:/wamp64/www/SIGUE_2026/dev-router.php
chdir(__DIR__ . '/public');
return require __DIR__ . '/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
