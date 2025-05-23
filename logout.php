<?php
require_once 'config.php';

// Verificar si hay sesión activa
if (isLoggedIn()) {
    // Destruir sesión
    session_unset();
    session_destroy();
    
    // Regenerar ID de sesión por seguridad
    session_start();
    session_regenerate_id(true);
}

// Redireccionar al login
header('Location: login.php');
exit;
?>
