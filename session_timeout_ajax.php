<?php
// Solo para propósitos de testing - NO actualizar last_activity
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
$loginTime = $_SESSION['login_time'] ?? null;
$currentTime = time();

if (!$lastActivity) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No last activity found']);
    exit;
}

// Calcular tiempos SIN actualizar la sesión
$timeSinceActivity = $currentTime - $lastActivity;
$timeUntilExpiry = 3600 - $timeSinceActivity; // SESSION_TIMEOUT hardcoded para evitar cargar config.php
$sessionDuration = $loginTime ? $currentTime - $loginTime : 0;
$expiryPercentage = min(100, ($timeSinceActivity / 3600) * 100);

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
    'session_duration' => $sessionDuration,
    'expiry_percentage' => $expiryPercentage,
    'is_expired' => false
]);
?> 