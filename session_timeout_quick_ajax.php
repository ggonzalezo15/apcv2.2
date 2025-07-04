<?php
// Solo para propósitos de testing rápido - NO actualizar last_activity
// Este archivo NO debe existir en producción

// Iniciar sesión sin actualizar actividad
session_start();

// Verificar si hay sesión activa
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No session found']);
    exit;
}

// Obtener información sin actualizar last_activity
$lastActivity = $_SESSION['last_activity'] ?? null;
$currentTime = time();

if (!$lastActivity) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No last activity found']);
    exit;
}

// Usar timeout de 30 segundos para test rápido
$quickTimeout = 30;

// Calcular tiempos SIN actualizar la sesión
$timeSinceActivity = $currentTime - $lastActivity;
$timeUntilExpiry = $quickTimeout - $timeSinceActivity;
$expiryPercentage = min(100, ($timeSinceActivity / $quickTimeout) * 100);

// Si la sesión ha expirado, informar
if ($timeUntilExpiry <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Session expired']);
    exit;
}

// Retornar información actualizada
header('Content-Type: application/json');
echo json_encode([
    'last_activity' => $lastActivity,
    'current_time' => $currentTime,
    'time_since_activity' => $timeSinceActivity,
    'time_until_expiry' => $timeUntilExpiry,
    'expiry_percentage' => $expiryPercentage,
    'is_expired' => false,
    'timeout_limit' => $quickTimeout
]);
?> 