<?php
require_once 'config.php';

if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: auth/login.php');
    exit;
}

$pageTitle = 'Dashboard de Seguridad';
?>
<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-shield-alt"></i>
                Dashboard de Seguridad
            </h1>
            <p class="content-subtitle">Monitoreo y análisis de seguridad del sistema</p>
        </div>

        <!-- Loading State -->
        <div id="securityLoading" class="security-loading" style="display: none;">
            <div class="loading-spinner">
                <i class="fas fa-shield-alt fa-spin"></i>
                <span>Cargando datos de seguridad...</span>
            </div>
        </div>

        <!-- Security Dashboard -->
        <div id="securityDashboard" class="security-dashboard">
            <!-- Security Metrics Overview -->
            <div class="security-metrics-grid">
                <div class="security-metric-card" id="securityLevel">
                    <div class="metric-header">
                        <h3>Nivel de Seguridad</h3>
                        <i class="fas fa-shield-check"></i>
                    </div>
                    <div class="metric-value">
                        <span class="metric-number" id="securityLevelValue">--</span>
                        <div class="metric-indicator" id="securityLevelIndicator"></div>
                    </div>
                    <div class="metric-change" id="securityLevelChange">Evaluando...</div>
                </div>

                <div class="security-metric-card" id="failedAttemptsCard">
                    <div class="metric-header">
                        <h3>Intentos Fallidos</h3>
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="metric-value">
                        <span class="metric-number" id="failedAttemptsValue">--</span>
                        <div class="metric-trend" id="failedAttemptsTrend">
                            <i class="fas fa-arrow-up"></i>
                            <span>Última hora</span>
                        </div>
                    </div>
                    <div class="metric-change" id="failedAttemptsChange">Cargando...</div>
                </div>

                <div class="security-metric-card" id="activeAlertsCard">
                    <div class="metric-header">
                        <h3>Alertas Activas</h3>
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="metric-value">
                        <span class="metric-number" id="activeAlertsValue">--</span>
                        <div class="metric-status" id="activeAlertsStatus">
                            <i class="fas fa-clock"></i>
                            <span>Tiempo real</span>
                        </div>
                    </div>
                    <div class="metric-change" id="activeAlertsChange">Monitoreando...</div>
                </div>

                <div class="security-metric-card" id="suspiciousIPsCard">
                    <div class="metric-header">
                        <h3>IPs Sospechosas</h3>
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="metric-value">
                        <span class="metric-number" id="suspiciousIPsValue">--</span>
                        <div class="metric-period" id="suspiciousIPsPeriod">
                            <i class="fas fa-history"></i>
                            <span>Últimas 24h</span>
                        </div>
                    </div>
                    <div class="metric-change" id="suspiciousIPsChange">Analizando...</div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="security-content-grid">
                <!-- Left Column -->
                <div class="security-left-column">
                    <!-- Security Configuration Status -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-cog"></i>
                                Configuración de Seguridad
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="security-config-list" id="securityConfigList">
                                <div class="config-item">
                                    <div class="config-loading">
                                        <i class="fas fa-circle-notch fa-spin"></i>
                                        <span>Cargando configuración...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Alerts -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-exclamation-triangle"></i>
                                Alertas de Seguridad
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="security-alerts-list" id="securityAlertsList">
                                <div class="alert-item">
                                    <div class="alert-loading">
                                        <i class="fas fa-circle-notch fa-spin"></i>
                                        <span>Cargando alertas...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Suspicious IPs -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-ban"></i>
                                IPs Sospechosas
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="suspicious-ips-list" id="suspiciousIPsList">
                                <div class="ip-item">
                                    <div class="ip-loading">
                                        <i class="fas fa-circle-notch fa-spin"></i>
                                        <span>Analizando IPs...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="security-right-column">
                    <!-- Session Information -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-user-clock"></i>
                                Información de Sesión
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="session-info-panel" id="sessionInfoPanel">
                                <div class="session-loading">
                                    <i class="fas fa-circle-notch fa-spin"></i>
                                    <span>Cargando información de sesión...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Failed Login Stats -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-chart-line"></i>
                                Estadísticas de Acceso
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="login-stats-panel" id="loginStatsPanel">
                                <div class="stats-loading">
                                    <i class="fas fa-circle-notch fa-spin"></i>
                                    <span>Cargando estadísticas...</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Actions -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-tools"></i>
                                Acciones de Seguridad
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="security-actions-panel">
                                <div class="action-buttons">
                                    <button class="btn btn-primary" onclick="clearFailedAttempts()">
                                        <i class="fas fa-broom"></i>
                                        Limpiar Intentos Fallidos
                                    </button>
                                    <button class="btn btn-secondary" onclick="generateSecurityReport()">
                                        <i class="fas fa-file-pdf"></i>
                                        Generar Reporte PDF
                                    </button>
                                    <button class="btn btn-success" onclick="refreshSecurityDashboard()">
                                        <i class="fas fa-sync-alt"></i>
                                        Actualizar Dashboard
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Toast de notificación -->
<div id="toast" style="position: fixed; bottom: 32px; right: 32px; min-width: 220px; z-index: 9999; display: none; background: var(--primary-color, #2563eb); color: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 2px 12px rgba(0,0,0,0.12); font-size: 16px; font-weight: 500; align-items: center; gap: 8px;">
    <span id="toastIcon" style="margin-right: 8px;"></span>
    <span id="toastMessage"></span>
</div>

<?php include 'includes/footer.php'; ?>
<script src="assets/js/security_dashboard.js"></script>
</body>
</html> 