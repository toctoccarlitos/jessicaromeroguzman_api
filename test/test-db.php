<?php

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=jrg',
        'jrg_api',
        '...'
    );
    echo "Conexión exitosa!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

?>