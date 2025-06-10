<?php
require_once 'config.php';

// Verificar si el usuario ya está logueado
if (isLoggedIn() && checkSessionTimeout()) {
    // Redireccionar al dashboard
    header('Location: dashboard.php');
} else {
    // Redireccionar al login
    header('Location: auth/login.php');
}
exit;
?>
