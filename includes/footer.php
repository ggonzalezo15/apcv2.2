    <?php if (isLoggedIn()): ?>
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-left">
                <p>&copy; <?php echo date('Y'); ?> Sistema de Autenticación. Todos los derechos reservados.</p>
            </div>
            <div class="footer-right">
                <span class="footer-info">
                    <i class="fas fa-clock"></i>
                    Sesión activa desde: <?php echo date('H:i', $_SESSION['login_time'] ?? time()); ?>
                </span>
            </div>
        </div>
    </footer>
    <?php endif; ?>
    
    <script src="assets/js/script.js"></script>
</body>
</html>
