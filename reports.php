<?php
require_once 'config.php';

// Verificar autenticación
if (!isLoggedIn() || !checkSessionTimeout()) {
    header('Location: login.php');
    exit;
}

// NOTA: El contenido de reportes se ha integrado en Settings > Informes
// Este archivo se mantiene para funciones futuras específicas de reportes
$pageTitle = 'Reportes - Funciones Específicas';
?>

<?php include 'includes/header.php'; ?>

<div class="main-layout">
    <?php include 'includes/sidebar.php'; ?>
    
    <main class="content">
        <div class="content-header">
            <h1 class="content-title">
                <i class="fas fa-chart-bar"></i>
                Reportes Específicos
            </h1>
            <p class="content-subtitle">Este archivo está reservado para funciones específicas de reportes</p>
        </div>
        
        <!-- Aviso de migración -->
        <div class="card" style="margin-bottom: 30px; border-left: 4px solid var(--info-color);">
            <div style="padding: 20px; background: var(--bg-info);">
                <h3 style="margin: 0 0 12px 0; color: var(--info-color); display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle"></i>
                    Contenido Migrado
                </h3>
                <p style="margin: 0 0 16px 0; color: var(--text-primary);">
                    El contenido principal de reportes se ha integrado en la sección <strong>Informes</strong> dentro de Configuración.
                </p>
                <div style="display: flex; gap: 12px;">
                    <a href="settings.php?tab=reports" class="btn" style="background: var(--info-color); color: white; text-decoration: none;">
                        <i class="fas fa-chart-bar"></i>
                        Ver Informes Integrados
                    </a>
                    <a href="settings.php" class="btn" style="background: var(--secondary-color); color: white; text-decoration: none;">
                        <i class="fas fa-cog"></i>
                        Ir a Configuración
                    </a>
                </div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; margin-bottom: 30px;">
            <!-- Reporte de usuarios -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-users" style="color: var(--primary-color);"></i>
                        Usuarios
                    </h3>
                </div>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 36px; font-weight: bold; color: var(--primary-color); margin-bottom: 8px;">
                        25
                    </div>
                    <div style="color: var(--text-secondary); margin-bottom: 12px;">Total registrados</div>
                    <div style="font-size: 14px;">
                        <span style="color: var(--success-color);">
                            <i class="fas fa-arrow-up"></i> +3 esta semana
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Reporte de sesiones -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-clock" style="color: var(--info-color);"></i>
                        Sesiones
                    </h3>
                </div>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 36px; font-weight: bold; color: var(--info-color); margin-bottom: 8px;">
                        147
                    </div>
                    <div style="color: var(--text-secondary); margin-bottom: 12px;">Este mes</div>
                    <div style="font-size: 14px;">
                        <span style="color: var(--success-color);">
                            <i class="fas fa-arrow-up"></i> +12% vs mes anterior
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Reporte de actividad -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-chart-line" style="color: var(--success-color);"></i>
                        Actividad
                    </h3>
                </div>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 36px; font-weight: bold; color: var(--success-color); margin-bottom: 8px;">
                        89%
                    </div>
                    <div style="color: var(--text-secondary); margin-bottom: 12px;">Tasa de actividad</div>
                    <div style="font-size: 14px;">
                        <span style="color: var(--success-color);">
                            <i class="fas fa-check"></i> Excelente rendimiento
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Reporte de seguridad -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-shield-alt" style="color: var(--warning-color);"></i>
                        Seguridad
                    </h3>
                </div>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 36px; font-weight: bold; color: var(--warning-color); margin-bottom: 8px;">
                        2
                    </div>
                    <div style="color: var(--text-secondary); margin-bottom: 12px;">Intentos fallidos hoy</div>
                    <div style="font-size: 14px;">
                        <span style="color: var(--success-color);">
                            <i class="fas fa-lock"></i> Sistema seguro
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de actividad -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-area"></i>
                    Actividad Semanal
                </h3>
                <p class="card-subtitle">Logins y actividad de usuarios en los últimos 7 días</p>
            </div>
            
            <div style="padding: 20px; min-height: 300px; display: flex; align-items: center; justify-content: center; background: var(--bg-primary); border-radius: 8px;">
                <div style="text-align: center;">
                    <i class="fas fa-chart-line" style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px;"></i>
                    <h4 style="color: var(--text-secondary); margin-bottom: 8px;">Gráfico de Actividad</h4>
                    <p style="color: var(--text-muted);">Los gráficos interactivos estarán disponibles próximamente</p>
                </div>
            </div>
        </div>
        
        <!-- Tablas de reportes -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 30px;">
            <!-- Últimos logins -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-history"></i>
                        Últimos Accesos
                    </h3>
                    <p class="card-subtitle">Registro de los últimos inicios de sesión</p>
                </div>
                
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border-color);">
                                <th style="padding: 12px 8px; text-align: left; font-weight: 600; color: var(--text-secondary);">Usuario</th>
                                <th style="padding: 12px 8px; text-align: left; font-weight: 600; color: var(--text-secondary);">Fecha</th>
                                <th style="padding: 12px 8px; text-align: left; font-weight: 600; color: var(--text-secondary);">IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-user-circle" style="color: var(--primary-color);"></i>
                                        <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                                    </div>
                                </td>
                                <td style="padding: 12px 8px; color: var(--text-secondary);">
                                    <?php echo date('d/m/Y H:i', $_SESSION['login_time'] ?? time()); ?>
                                </td>
                                <td style="padding: 12px 8px; font-family: monospace; color: var(--text-secondary);">
                                    <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR']); ?>
                                </td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-user-circle" style="color: var(--secondary-color);"></i>
                                        <span>admin</span>
                                    </div>
                                </td>
                                <td style="padding: 12px 8px; color: var(--text-secondary);">22/05/2025 14:30</td>
                                <td style="padding: 12px 8px; font-family: monospace; color: var(--text-secondary);">192.168.1.100</td>
                            </tr>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="fas fa-user-circle" style="color: var(--success-color);"></i>
                                        <span>usuario1</span>
                                    </div>
                                </td>
                                <td style="padding: 12px 8px; color: var(--text-secondary);">22/05/2025 11:15</td>
                                <td style="padding: 12px 8px; font-family: monospace; color: var(--text-secondary);">10.0.0.50</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Resumen del sistema -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-server"></i>
                        Estado del Sistema
                    </h3>
                    <p class="card-subtitle">Información técnica y rendimiento</p>
                </div>
                
                <div style="padding: 10px 0;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                        <span style="font-weight: 500;">Servidor Web:</span>
                        <span style="color: var(--success-color);">
                            <i class="fas fa-circle" style="font-size: 8px;"></i> Activo
                        </span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                        <span style="font-weight: 500;">Base de Datos:</span>
                        <span style="color: var(--success-color);">
                            <i class="fas fa-circle" style="font-size: 8px;"></i> Conectada
                        </span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                        <span style="font-weight: 500;">PHP:</span>
                        <span style="color: var(--text-secondary);">v<?php echo PHP_VERSION; ?></span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-color);">
                        <span style="font-weight: 500;">Memoria Usada:</span>
                        <span style="color: var(--text-secondary);">
                            <?php echo round(memory_get_usage() / 1024 / 1024, 2); ?>MB
                        </span>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 0;">
                        <span style="font-weight: 500;">Uptime:</span>
                        <span style="color: var(--text-secondary);">
                            <?php 
                            $uptime = time() - ($_SESSION['login_time'] ?? time());
                            $hours = floor($uptime / 3600);
                            $minutes = floor(($uptime % 3600) / 60);
                            echo sprintf('%02d:%02d', $hours, $minutes);
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Acciones de reportes -->
        <div style="margin-top: 30px; display: flex; gap: 16px; justify-content: center;">
            <button class="btn" style="background: var(--success-color); color: white; padding: 12px 24px;">
                <i class="fas fa-file-excel"></i>
                Exportar a Excel
            </button>
            <button class="btn" style="background: var(--danger-color); color: white; padding: 12px 24px;">
                <i class="fas fa-file-pdf"></i>
                Generar PDF
            </button>
            <button class="btn" style="background: var(--info-color); color: white; padding: 12px 24px;">
                <i class="fas fa-envelope"></i>
                Enviar por Email
            </button>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</div>
