<?php
/**
 * Endpoint de prueba para verificar autorización
 */

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

echo json_encode([
    'timestamp' => date('Y-m-d H:i:s'),
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION,
    'user_logged_in' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null,
    'username' => $_SESSION['username'] ?? null,
    'role' => $_SESSION['role'] ?? null,
    'is_admin' => isset($_SESSION['role']) && $_SESSION['role'] === 'admin',
    'php_version' => PHP_VERSION,
    'server_info' => [
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'N/A',
        'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'N/A',
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'N/A'
    ]
], JSON_PRETTY_PRINT);
?>
