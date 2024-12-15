<?php
return [
    'driver'   => 'pdo_mysql',
    'host'     => $_ENV['DB_HOST'],
    'dbname'   => $_ENV['DB_NAME'],
    'user'     => $_ENV['DB_USER'],
    'password' => $_ENV['DB_PASS'],
    'charset'  => 'utf8mb4'
];