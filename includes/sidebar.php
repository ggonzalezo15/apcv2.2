<?php if (isLoggedIn()): ?>
<aside class="sidebar collapsed" id="sidebar">
    <div class="sidebar-content">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-shield-alt"></i>
                <span class="sidebar-title">Panel</span>
            </div>
        </div>
        
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span class="nav-text">Inicio</span>
                    </a>
                </li>
                
                <!-- Módulos APCUADRE -->
                <li class="nav-item">
                    <a href="incomes.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'incomes.php' ? 'active' : ''; ?>">
                        <i class="fas fa-arrow-up"></i>
                        <span class="nav-text">Ingresos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="expenses.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'expenses.php' ? 'active' : ''; ?>">
                        <i class="fas fa-arrow-down"></i>
                        <span class="nav-text">Gastos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="teams.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'teams.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span class="nav-text">Equipos</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="contractors.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contractors.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-tie"></i>
                        <span class="nav-text">Contratistas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="vendors.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'vendors.php' ? 'active' : ''; ?>">
                        <i class="fas fa-store"></i>
                        <span class="nav-text">Proveedores</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="bank_accounts.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'bank_accounts.php' ? 'active' : ''; ?>">
                        <i class="fas fa-university"></i>
                        <span class="nav-text">Cuentas</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="payment_types.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payment_types.php' ? 'active' : ''; ?>">
                        <i class="fas fa-money-check-alt"></i>
                        <span class="nav-text">Tipos de Pago</span>
                    </a>
                </li>
                
                <!-- Administración -->
                <li class="nav-item">
                    <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user"></i>
                        <span class="nav-text">Perfil</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i>
                        <span class="nav-text">Reportes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i>
                        <span class="nav-text">Configuración</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="sidebar-footer">
            <div class="nav-item">
                <a href="logout.php" class="nav-link logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-text">Cerrar Sesión</span>
                </a>
            </div>
        </div>
    </div>
</aside>
<?php endif; ?>
