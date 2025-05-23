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
                <div class="user-menu">
                    <span class="user-name">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'Usuario'); ?>
                    </span>
                    <a href="logout.php" class="logout-btn" title="Cerrar Sesión">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>
