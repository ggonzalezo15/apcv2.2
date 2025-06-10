<?php
require_once 'config.php';

try {
    $pdo = getConnection();
    $dbName = DB_NAME;
    
    // Obtener todas las tablas
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    $schema = "-- Esquema de la base de datos $dbName\n\n";
    $schema .= "CREATE DATABASE IF NOT EXISTS `$dbName`;\nUSE `$dbName`;\n\n";
    
    foreach ($tables as $table) {
        $row = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $create = $row[array_keys($row)[1]];
        $schema .= $create . ";\n\n";
    }
    
    file_put_contents('session.sql', $schema);
    echo "Esquema exportado correctamente a session.sql";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
} 
