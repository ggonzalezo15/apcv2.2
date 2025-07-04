<?php
require_once 'config.php';

// Verificar autenticación
checkAPIAuthentication();

// Obtener acción
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'getSecurityStatus':
            getSecurityStatus();
            break;
        case 'getFailedLoginStats':
            getFailedLoginStatsAjax();
            break;
        case 'getSecurityAlerts':
            getSecurityAlerts();
            break;
        case 'getSuspiciousIPs':
            getSuspiciousIPs();
            break;
        case 'getSessionInfo':
            getSessionInfo();
            break;
        case 'clearFailedAttempts':
            clearFailedAttempts();
            break;
        case 'generateSecurityReport':
            generateSecurityReport();
            break;
        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getSecurityStatus() {
    try {
        $sessionSecurityStatus = getSessionSecurityStatus();
        
        // Debug: Log what we're getting
        error_log("Session Security Status: " . print_r($sessionSecurityStatus, true));
        
        $status = [
            'session' => $sessionSecurityStatus ?: [],
            'rate_limiting' => [
                'enabled' => true,
                'max_attempts' => 5,
                'time_window' => 900 // 15 minutos
            ],
            'headers' => [
                'enabled' => true,
                'csp' => true,
                'xss_protection' => true,
                'frame_options' => true
            ],
            'audit' => [
                'enabled' => true,
                'login_tracking' => true,
                'failed_attempts_tracking' => true
            ]
        ];
        
        header('Content-Type: application/json');
        echo json_encode($status);
    } catch (Exception $e) {
        error_log("Error in getSecurityStatus: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'session' => [],
            'rate_limiting' => ['enabled' => false],
            'headers' => ['enabled' => false],
            'audit' => ['enabled' => false],
            'error' => 'Error loading security status: ' . $e->getMessage()
        ]);
    }
}

function getFailedLoginStatsAjax() {
    try {
        $stats = getFailedLoginStats(3600); // Última hora
        
        // Debug: Log what we're getting
        error_log("Failed Login Stats: " . print_r($stats, true));
        
        // Ensure we always return a valid structure
        if ($stats === null || !is_array($stats)) {
            $stats = [
                'totals' => [
                    'total' => 0,
                    'unique_ips' => 0,
                    'unique_usernames' => 0
                ],
                'by_reason' => [],
                'suspicious_ips' => [],
                'time_window_hours' => 1
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($stats);
    } catch (Exception $e) {
        error_log("Error in getFailedLoginStatsAjax: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'totals' => [
                'total' => 0,
                'unique_ips' => 0,
                'unique_usernames' => 0
            ],
            'by_reason' => [],
            'suspicious_ips' => [],
            'time_window_hours' => 1,
            'error' => 'Error loading stats: ' . $e->getMessage()
        ]);
    }
}

function getSecurityAlerts() {
    try {
        $alerts = detectPotentialAttacks();
        
        // Ensure alerts is an array
        if (!is_array($alerts)) {
            $alerts = [];
        }
        
        // Agregar más alertas basadas en configuración
        $sessionStatus = getSessionSecurityStatus();
        
        if (is_array($sessionStatus)) {
            // Alerta si no está usando HTTPS
            if (!($sessionStatus['is_https'] ?? false)) {
                $alerts[] = [
                    'type' => 'SECURITY_CONFIG',
                    'severity' => 'MEDIUM',
                    'message' => 'Conexión no segura: Se recomienda usar HTTPS',
                    'data' => ['https_enabled' => false]
                ];
            }
            
            // Alerta si las cookies no están configuradas de forma segura
            if (!($sessionStatus['secure'] ?? false) || !($sessionStatus['httponly'] ?? false)) {
                $alerts[] = [
                    'type' => 'COOKIE_SECURITY',
                    'severity' => 'MEDIUM',
                    'message' => 'Configuración de cookies insegura',
                    'data' => $sessionStatus
                ];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode($alerts);
    } catch (Exception $e) {
        error_log("Error in getSecurityAlerts: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([]);
    }
}

function getSuspiciousIPs() {
    try {
        $stats = getFailedLoginStats(86400); // Últimas 24 horas
        
        // Debug: Log what we're getting
        error_log("Suspicious IPs Stats: " . print_r($stats, true));
        
        $suspiciousIPs = [];
        if (is_array($stats) && isset($stats['suspicious_ips']) && is_array($stats['suspicious_ips'])) {
            $suspiciousIPs = $stats['suspicious_ips'];
        }
        
        header('Content-Type: application/json');
        echo json_encode($suspiciousIPs);
    } catch (Exception $e) {
        error_log("Error in getSuspiciousIPs: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([]);
    }
}

function getSessionInfo() {
    try {
        $sessionInfo = getSessionTimeoutInfo();
        $sessionSecurityStatus = getSessionSecurityStatus();
        
        // Debug: Log what we're getting
        error_log("Session Timeout Info: " . print_r($sessionInfo, true));
        error_log("Session Security Status: " . print_r($sessionSecurityStatus, true));
        
        $info = [
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $sessionInfo['last_activity'] ?? time(),
            'time_until_expiry' => $sessionInfo['time_until_expiry'] ?? SESSION_TIMEOUT,
            'session_duration' => $sessionInfo['session_duration'] ?? 0,
            'expiry_percentage' => $sessionInfo['expiry_percentage'] ?? 0,
            'security_level' => $sessionSecurityStatus['security_level'] ?? 'UNKNOWN'
        ];
        
        header('Content-Type: application/json');
        echo json_encode($info);
    } catch (Exception $e) {
        error_log("Error in getSessionInfo: " . $e->getMessage());
        header('Content-Type: application/json');
        echo json_encode([
            'session_id' => session_id(),
            'user_id' => $_SESSION['user_id'] ?? null,
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => time(),
            'time_until_expiry' => SESSION_TIMEOUT,
            'session_duration' => 0,
            'expiry_percentage' => 0,
            'security_level' => 'UNKNOWN',
            'error' => 'Error loading session info: ' . $e->getMessage()
        ]);
    }
}

function clearFailedAttempts() {
    try {
        $pdo = getConnection();
        
        // Limpiar intentos fallidos de la base de datos
        $stmt = $pdo->prepare("DELETE FROM failed_login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $stmt->execute();
        $deletedRows = $stmt->rowCount();
        
        // Limpiar intentos de la sesión
        $sessionKeys = array_keys($_SESSION);
        foreach ($sessionKeys as $key) {
            if (strpos($key, 'login_attempts_') === 0) {
                unset($_SESSION[$key]);
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => "Se eliminaron {$deletedRows} intentos fallidos",
            'deleted_rows' => $deletedRows
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Error al limpiar intentos fallidos: ' . $e->getMessage());
    }
}

function generateSecurityReport() {
    try {
        // Recopilar datos del reporte
        $securityStatus = getSecurityStatusData();
        $failedLoginStats = getFailedLoginStats(86400); // 24 horas
        $securityAlerts = detectPotentialAttacks();
        $sessionInfo = getSessionTimeoutInfo();
        $currentUser = $_SESSION['user_id'] ?? null;
        $currentIP = getClientIP();
        
        // Crear instancia de TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configurar información del documento
        $pdf->SetCreator('AP Cuadre - Sistema de Seguridad');
        $pdf->SetAuthor('AP Cuadre');
        $pdf->SetTitle('Reporte de Seguridad');
        $pdf->SetSubject('Análisis de Seguridad del Sistema');
        $pdf->SetKeywords('Seguridad, Dashboard, Reporte, AP Cuadre');
        
        // Configurar márgenes
        $pdf->SetMargins(15, 27, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Configurar auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Configurar fuente
        $pdf->SetFont('helvetica', '', 10);
        
        // Agregar página
        $pdf->AddPage();
        
        // Título del reporte
        $pdf->SetFont('helvetica', 'B', 18);
        $pdf->Cell(0, 10, 'REPORTE DE SEGURIDAD', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 8, 'AP Cuadre - Sistema de Gestión', 0, 1, 'C');
        $pdf->Ln(5);
        
        // Información general
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'INFORMACIÓN GENERAL', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(50, 6, 'Fecha y Hora:', 0, 0, 'L');
        $pdf->Cell(0, 6, date('d/m/Y H:i:s'), 0, 1, 'L');
        $pdf->Cell(50, 6, 'Usuario:', 0, 0, 'L');
        $pdf->Cell(0, 6, $currentUser ? "ID: $currentUser" : 'No identificado', 0, 1, 'L');
        $pdf->Cell(50, 6, 'IP de Origen:', 0, 0, 'L');
        $pdf->Cell(0, 6, $currentIP, 0, 1, 'L');
        $pdf->Cell(50, 6, 'Versión PHP:', 0, 0, 'L');
        $pdf->Cell(0, 6, PHP_VERSION, 0, 1, 'L');
        $pdf->Ln(5);
        
        // Estado de seguridad
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'ESTADO DE SEGURIDAD', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        // Configuración de sesión
        $sessionStatus = $securityStatus['session'] ?? [];
        $pdf->Cell(0, 6, 'Configuración de Sesión:', 0, 1, 'L');
        $pdf->Cell(10, 6, '• ', 0, 0, 'L');
        $pdf->Cell(0, 6, 'HTTPS: ' . (($sessionStatus['is_https'] ?? false) ? 'Activado' : 'Desactivado'), 0, 1, 'L');
        $pdf->Cell(10, 6, '• ', 0, 0, 'L');
        $pdf->Cell(0, 6, 'Cookies Seguras: ' . (($sessionStatus['secure'] ?? false) ? 'Activado' : 'Desactivado'), 0, 1, 'L');
        $pdf->Cell(10, 6, '• ', 0, 0, 'L');
        $pdf->Cell(0, 6, 'HttpOnly: ' . (($sessionStatus['httponly'] ?? false) ? 'Activado' : 'Desactivado'), 0, 1, 'L');
        $pdf->Cell(10, 6, '• ', 0, 0, 'L');
        $pdf->Cell(0, 6, 'SameSite: ' . ($sessionStatus['samesite'] ?? 'No configurado'), 0, 1, 'L');
        $pdf->Ln(3);
        
        // Rate Limiting
        $rateLimiting = $securityStatus['rate_limiting'] ?? [];
        $pdf->Cell(0, 6, 'Rate Limiting:', 0, 1, 'L');
        $pdf->Cell(10, 6, '• ', 0, 0, 'L');
        $pdf->Cell(0, 6, 'Estado: ' . (($rateLimiting['enabled'] ?? false) ? 'Activado' : 'Desactivado'), 0, 1, 'L');
        $pdf->Cell(10, 6, '• ', 0, 0, 'L');
        $pdf->Cell(0, 6, 'Máximo Intentos: ' . ($rateLimiting['max_attempts'] ?? 'No configurado'), 0, 1, 'L');
        $pdf->Cell(10, 6, '• ', 0, 0, 'L');
        $pdf->Cell(0, 6, 'Ventana de Tiempo: ' . (($rateLimiting['time_window'] ?? 0) / 60) . ' minutos', 0, 1, 'L');
        $pdf->Ln(5);
        
        // Estadísticas de intentos fallidos
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'ESTADÍSTICAS DE ACCESO (ÚLTIMAS 24 HORAS)', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        if ($failedLoginStats && isset($failedLoginStats['totals'])) {
            $totals = $failedLoginStats['totals'];
            $pdf->Cell(0, 6, 'Resumen de Intentos Fallidos:', 0, 1, 'L');
            $pdf->Cell(10, 6, '• ', 0, 0, 'L');
            $pdf->Cell(0, 6, 'Total de Intentos: ' . ($totals['total'] ?? 0), 0, 1, 'L');
            $pdf->Cell(10, 6, '• ', 0, 0, 'L');
            $pdf->Cell(0, 6, 'IPs Únicas: ' . ($totals['unique_ips'] ?? 0), 0, 1, 'L');
            $pdf->Cell(10, 6, '• ', 0, 0, 'L');
            $pdf->Cell(0, 6, 'Usuarios Intentados: ' . ($totals['unique_usernames'] ?? 0), 0, 1, 'L');
            $pdf->Ln(3);
            
            // Razones de fallos
            if (isset($failedLoginStats['by_reason']) && !empty($failedLoginStats['by_reason'])) {
                $pdf->Cell(0, 6, 'Razones de Fallos:', 0, 1, 'L');
                foreach ($failedLoginStats['by_reason'] as $reason) {
                    $pdf->Cell(10, 6, '• ', 0, 0, 'L');
                    $pdf->Cell(0, 6, $reason['reason'] . ': ' . $reason['count_by_reason'] . ' intentos', 0, 1, 'L');
                }
            }
        } else {
            $pdf->Cell(0, 6, 'No se encontraron intentos fallidos en las últimas 24 horas.', 0, 1, 'L');
        }
        $pdf->Ln(5);
        
        // Alertas de seguridad
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'ALERTAS DE SEGURIDAD', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        if (!empty($securityAlerts)) {
            foreach ($securityAlerts as $alert) {
                $pdf->SetFont('helvetica', 'B', 10);
                $severityColor = getSeverityColor($alert['severity'] ?? 'LOW');
                $pdf->Cell(0, 6, '• [' . ($alert['severity'] ?? 'LOW') . '] ' . ($alert['type'] ?? 'UNKNOWN'), 0, 1, 'L');
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(10, 6, '', 0, 0, 'L');
                $pdf->Cell(0, 6, $alert['message'] ?? 'Sin mensaje', 0, 1, 'L');
                $pdf->Ln(2);
            }
        } else {
            $pdf->Cell(0, 6, 'No se detectaron alertas de seguridad activas.', 0, 1, 'L');
        }
        $pdf->Ln(5);
        
        // IPs sospechosas
        if (isset($failedLoginStats['suspicious_ips']) && !empty($failedLoginStats['suspicious_ips'])) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'IPs SOSPECHOSAS', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);
            
            foreach ($failedLoginStats['suspicious_ips'] as $ip) {
                $pdf->Cell(10, 6, '• ', 0, 0, 'L');
                $pdf->Cell(0, 6, $ip['ip'] . ' (' . $ip['attempts'] . ' intentos)', 0, 1, 'L');
            }
            $pdf->Ln(5);
        }
        
        // Información de sesión actual
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'INFORMACIÓN DE SESIÓN ACTUAL', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        if ($sessionInfo) {
            $pdf->Cell(50, 6, 'ID de Sesión:', 0, 0, 'L');
            $pdf->Cell(0, 6, session_id(), 0, 1, 'L');
            $pdf->Cell(50, 6, 'Tiempo de Sesión:', 0, 0, 'L');
            $pdf->Cell(0, 6, round(($sessionInfo['session_duration'] ?? 0) / 60, 1) . ' minutos', 0, 1, 'L');
            $pdf->Cell(50, 6, 'Tiempo hasta Expirar:', 0, 0, 'L');
            $pdf->Cell(0, 6, round(($sessionInfo['time_until_expiry'] ?? 0) / 60, 1) . ' minutos', 0, 1, 'L');
        }
        
        // Pie de página con información adicional
        $pdf->Ln(10);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->Cell(0, 6, 'Reporte generado automáticamente por AP Cuadre - Sistema de Seguridad', 0, 1, 'C');
        $pdf->Cell(0, 6, 'Para más información, contacte al administrador del sistema', 0, 1, 'C');
        
        // Configurar headers para descarga
        $filename = 'reporte_seguridad_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Limpiar buffer de salida
        ob_clean();
        
        // Configurar headers para PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Generar y enviar PDF
        $pdf->Output($filename, 'D');
        
    } catch (Exception $e) {
        throw new Exception('Error al generar reporte PDF: ' . $e->getMessage());
    }
}

// Función auxiliar para obtener color de severidad
function getSeverityColor($severity) {
    switch (strtoupper($severity)) {
        case 'CRITICAL': return [255, 0, 0];
        case 'HIGH': return [255, 165, 0];
        case 'MEDIUM': return [255, 255, 0];
        case 'LOW': return [0, 255, 0];
        default: return [128, 128, 128];
    }
}

function getSecurityStatusData() {
    $sessionSecurityStatus = getSessionSecurityStatus();
    
    return [
        'session' => $sessionSecurityStatus,
        'rate_limiting' => [
            'enabled' => true,
            'max_attempts' => 5,
            'time_window' => 900 // 15 minutos
        ],
        'headers' => [
            'enabled' => true,
            'csp' => true,
            'xss_protection' => true,
            'frame_options' => true
        ],
        'audit' => [
            'enabled' => true,
            'login_tracking' => true,
            'failed_attempts_tracking' => true
        ]
    ];
}
?> 