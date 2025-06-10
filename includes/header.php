<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Sistema de Autenticación'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <header class="header">
        <div class="header-content">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header-title">
                <h1><?php echo isset($pageTitle) ? $pageTitle : 'Panel Principal'; ?></h1>
            </div>
            <div class="header-actions">
                <div class="user-menu" id="userMenu">
                    <button class="user-menu-trigger" id="userMenuTrigger">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Usuario'); ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-menu-dropdown" id="userMenuDropdown">
                        <a href="profile.php" class="user-menu-item">
                            <i class="fas fa-user"></i>
                            <span>Mi Perfil</span>
                        </a>
                        <a href="settings.php" class="user-menu-item">
                            <i class="fas fa-cog"></i>
                            <span>Configuración</span>
                        </a>
                        <div class="user-menu-divider"></div>
                        <a href="auth/logout.php" class="user-menu-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Cerrar Sesión</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <style>
    .user-menu {
        position: relative;
    }

    .user-menu-trigger {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: transparent;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        color: var(--text-primary);
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 14px;
    }

    .user-menu-trigger:hover {
        background-color: var(--bg-secondary);
        border-color: var(--primary-color);
    }

    .user-menu-trigger i:last-child {
        font-size: 12px;
        transition: transform 0.2s ease;
    }

    .user-menu.active .user-menu-trigger i:last-child {
        transform: rotate(180deg);
    }

    .user-menu-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        min-width: 200px;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.2s ease;
        margin-top: 8px;
    }

    .user-menu.active .user-menu-dropdown {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .user-menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        color: var(--text-primary);
        text-decoration: none;
        transition: background-color 0.2s ease;
        font-size: 14px;
    }

    .user-menu-item:hover {
        background-color: var(--bg-secondary);
    }

    .user-menu-item i {
        width: 16px;
        text-align: center;
        color: var(--text-secondary);
    }

    .user-menu-divider {
        height: 1px;
        background-color: var(--border-color);
        margin: 4px 0;
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const userMenu = document.getElementById('userMenu');
        const userMenuTrigger = document.getElementById('userMenuTrigger');
        const userMenuDropdown = document.getElementById('userMenuDropdown');

        if (userMenuTrigger) {
            userMenuTrigger.addEventListener('click', function(e) {
                e.stopPropagation();
                userMenu.classList.toggle('active');
            });
        }

        // Cerrar el menú al hacer clic fuera
        document.addEventListener('click', function(e) {
            if (!userMenu.contains(e.target)) {
                userMenu.classList.remove('active');
            }
        });

        // Prevenir que el menú se cierre al hacer clic dentro del dropdown
        if (userMenuDropdown) {
            userMenuDropdown.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        }
    });
    </script>

    <?php endif; ?>
