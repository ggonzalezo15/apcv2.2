# Instrucciones para Copilot

<!-- Use this file to provide workspace-specific custom instructions to Copilot. For more details, visit https://code.visualstudio.com/docs/copilot/copilot-customization#_use-a-githubcopilotinstructionsmd-file -->

## Proyecto: Sistema de Autenticación PHP

### Descripción
Este es un sistema de autenticación completo desarrollado en PHP puro con las siguientes características:
- Sistema de login/logout seguro con protección CSRF
- Layout responsivo con sidebar expandible automáticamente
- Gestión de sesiones con timeout configurable
- Interfaz moderna usando CSS Grid y Flexbox
- Base de datos MySQL con PDO para máxima seguridad

### Estructura del Proyecto
```
/
├── config.php              # Configuración de BD y funciones globales
├── index.php              # Página principal (redirige según autenticación)
├── login.php              # Página de inicio de sesión
├── register.php           # Página de registro de usuarios
├── logout.php             # Script de cierre de sesión
├── dashboard.php          # Panel principal del usuario
├── profile.php            # Gestión de perfil de usuario
├── settings.php           # Configuración de usuario
├── reports.php            # Reportes y estadísticas
├── install.php            # Script de inicialización de BD
├── includes/
│   ├── header.php         # Header común con navegación
│   ├── sidebar.php        # Sidebar expandible con menú
│   └── footer.php         # Footer común
└── assets/
    ├── css/
    │   └── style.css      # Estilos principales con variables CSS
    └── js/
        └── script.js      # JavaScript para interactividad
```

### Funcionalidades del Sidebar
- **Estado por defecto**: Siempre colapsado (60px de ancho)
- **Expansión automática**: Se expande a 250px al hacer hover
- **Responsive**: En móviles (<768px) se convierte en menú lateral deslizable
- **Navegación activa**: Enlaces resaltados según la página actual

### Estándares de Código
- Usar **PHP 8+** con tipado estricto cuando sea posible
- Implementar **prepared statements** para todas las consultas SQL
- Validar y sanitizar **todos** los inputs del usuario
- Usar **tokens CSRF** en todos los formularios
- Aplicar principios de **seguridad por defecto**
- Mantener **separación de responsabilidades** (lógica/presentación)

### Seguridad
- Todas las páginas verifican autenticación excepto login/register
- Passwords hasheados con `password_hash()` y `PASSWORD_DEFAULT`
- Sesiones con timeout configurable (por defecto 1 hora)
- Regeneración de ID de sesión en login exitoso
- Protección CSRF en todos los formularios
- Validación tanto client-side como server-side

### CSS y Diseño
- Usar **variables CSS** definidas en `:root` para mantener consistencia
- Seguir **mobile-first** responsive design
- Implementar **transiciones suaves** para mejor UX
- Mantener **accesibilidad** con etiquetas semánticas y ARIA
- Usar **Flexbox** y **CSS Grid** para layouts modernos

### JavaScript
- Escribir **JavaScript vanilla** (sin frameworks)
- Implementar **validación en tiempo real** de formularios
- Manejar **eventos touch** para dispositivos móviles
- Usar **async/await** para peticiones AJAX
- Implementar **lazy loading** cuando sea apropiado

### Base de Datos
- Usar **charset utf8mb4** para soporte completo Unicode
- Implementar **índices** en columnas de búsqueda frecuente
- Usar **FOREIGN KEY** constraints para integridad referencial
- Mantener **logs de actividad** para auditoría
- Implementar **soft deletes** cuando sea apropiado

### Nuevas Funcionalidades
Al agregar nuevas funcionalidades:
1. Seguir el patrón de autenticación existente
2. Incluir enlaces en el sidebar si corresponde
3. Mantener consistencia visual con el diseño existente
4. Implementar validaciones tanto client como server-side
5. Agregar logs de actividad para acciones importantes
6. Documentar cambios en este archivo

### Variables CSS Principales
```css
--primary-color: #2563eb;
--sidebar-width: 250px;
--sidebar-collapsed-width: 60px;
--header-height: 60px;
--transition-normal: 0.3s ease;
```

### Funciones PHP Importantes
- `isLoggedIn()`: Verifica si hay sesión activa
- `checkSessionTimeout()`: Valida timeout de sesión
- `generateCSRFToken()`: Genera token de seguridad
- `verifyCSRFToken()`: Valida token CSRF
- `getConnection()`: Retorna conexión PDO a la BD
