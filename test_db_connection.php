<?php
require_once __DIR__ . '/config.php';

try {
    $pdo = getConnection();
    echo "Conexión exitosa a la base de datos.";
} catch (Exception $e) {
    echo "Error de conexión: " . $e->getMessage();
} 