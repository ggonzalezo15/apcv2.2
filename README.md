# Sistema de Autenticación PHP

Un sistema de autenticación completo y moderno desarrollado en PHP puro con interfaz responsiva y sidebar expandible.

## 🚀 Características

### Autenticación y Seguridad
- ✅ Sistema de login/logout seguro
- ✅ Registro de usuarios con validación
- ✅ Protección CSRF en todos los formularios
- ✅ Gestión de sesiones con timeout configurable
- ✅ Passwords hasheados con algoritmos seguros
- ✅ Validación tanto client-side como server-side

### Interfaz de Usuario
- ✅ **Sidebar expandible automáticamente**: Colapsado por defecto, se expande al hacer hover
- ✅ Layout responsivo con CSS Grid y Flexbox
- ✅ Diseño moderno y profesional
- ✅ Navegación intuitiva con estados activos
- ✅ Compatibilidad móvil completa

### Funcionalidades
- ✅ Panel de control (Dashboard)
- ✅ Gestión de perfil de usuario
- ✅ Página de configuración
- ✅ Reportes y estadísticas
- ✅ Logs de actividad
- ✅ Sistema de notificaciones

## 📋 Requisitos

- **PHP 7.4+** (recomendado PHP 8.0+)
- **MySQL 5.7+** o **MariaDB 10.3+**
- **Servidor web** (Apache, Nginx, o servidor PHP integrado)
- **Extensiones PHP**: PDO, PDO_MySQL

## 🛠️ Instalación

### 1. Clonar o descargar el proyecto
```bash
# Si tienes Git instalado
git clone <url-del-repositorio>

# O descarga el ZIP y extrae los archivos
```

### 2. Configurar la base de datos
1. Crea una base de datos MySQL llamada `auth_system`
2. Edita `config.php` con tus credenciales de base de datos:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
define('DB_NAME', 'auth_system');
```

### 3. Inicializar la base de datos
Ejecuta el script de instalación en tu navegador:
```
http://localhost/tu-proyecto/install.php
```

Este script creará:
- Tabla `users` para gestión de usuarios
- Tabla `user_sessions` para sesiones
- Tabla `activity_logs` para logs de actividad
- Usuario administrador por defecto

### 4. Usuarios por defecto
El script de instalación crea dos usuarios:

**Administrador:**
- Usuario: `admin`
- Contraseña: `admin123`
- Email: `admin@sistema.com`

**Usuario de prueba:**
- Usuario: `usuario`
- Contraseña: `123456`
- Email: `usuario@test.com`

⚠️ **Importante**: Cambia las contraseñas después del primer login

## 📁 Estructura del Proyecto

```
/
├── config.php              # Configuración y funciones globales
├── index.php              # Página principal (redirige según estado)
├── login.php              # Página de inicio de sesión
├── register.php           # Página de registro
├── logout.php             # Script de cierre de sesión
├── dashboard.php          # Panel principal
├── profile.php            # Gestión de perfil
├── settings.php           # Configuración de usuario
├── reports.php            # Reportes y estadísticas
├── install.php            # Script de inicialización
├── includes/
│   ├── header.php         # Header común
│   ├── sidebar.php        # Sidebar expandible
│   └── footer.php         # Footer común
├── assets/
│   ├── css/
│   │   └── style.css      # Estilos principales
│   └── js/
│       └── script.js      # JavaScript para interactividad
└── .github/
    └── copilot-instructions.md
```

## 🎨 Funcionalidad del Sidebar

### Comportamiento por Defecto
- **Estado inicial**: Siempre colapsado (60px de ancho)
- **Solo iconos visibles** en estado colapsado
- **Posición fija** en el lado izquierdo

### Expansión Automática
- **Hover**: Se expande automáticamente a 250px
- **Transición suave** de 0.3 segundos
- **Muestra texto** de navegación al expandirse
- **Vuelve a colapsar** al quitar el mouse

### Responsivo
- **Desktop**: Comportamiento de hover descrito arriba
- **Tablet/Móvil**: Se convierte en menú lateral deslizable
- **Toggle button**: Aparece en móviles para abrir/cerrar
- **Overlay**: Fondo semitransparente en móviles

## 🔧 Configuración

### Variables CSS Principales
```css
--sidebar-width: 250px;           /* Ancho expandido */
--sidebar-collapsed-width: 60px;  /* Ancho colapsado */
--transition-normal: 0.3s ease;   /* Velocidad de transición */
```

### Configuración PHP (config.php)
```php
define('SESSION_TIMEOUT', 3600);  // Timeout de sesión (segundos)
define('BASE_URL', 'http://localhost/'); // URL base del proyecto
```

## 🔒 Seguridad

### Medidas Implementadas
- **Prepared Statements**: Previene inyección SQL
- **Password Hashing**: Usando `password_hash()` con `PASSWORD_DEFAULT`
- **CSRF Protection**: Tokens en todos los formularios
- **Session Management**: Regeneración de ID y timeout
- **Input Validation**: Sanitización de todos los inputs
- **Secure Headers**: Configuración de headers de seguridad

### Mejores Prácticas
- Cambia las contraseñas por defecto
- Elimina `install.php` después de la instalación
- Configura HTTPS en producción
- Actualiza PHP y MySQL regularmente
- Revisa logs de actividad periódicamente

## 📱 Responsive Design

### Breakpoints
- **Desktop**: > 768px (sidebar con hover)
- **Tablet**: 768px - 480px (menú lateral)
- **Mobile**: < 480px (optimizaciones adicionales)

### Adaptaciones Móviles
- Sidebar se convierte en menú lateral
- Header compacto con botón de menú
- Cards apiladas verticalmente
- Formularios optimizados para touch
- Footer simplificado

## 🎯 Uso

### Navegación Principal
1. **Dashboard**: Panel principal con estadísticas
2. **Perfil**: Gestión de datos personales
3. **Configuración**: Opciones de personalización
4. **Reportes**: Estadísticas y análisis

### Gestión de Usuarios
- **Registro**: Crear nuevas cuentas
- **Login**: Acceso con usuario/email
- **Logout**: Cierre seguro de sesión
- **Perfil**: Editar información y contraseña

## 🔄 Personalización

### Cambiar Colores
Edita las variables CSS en `assets/css/style.css`:
```css
:root {
    --primary-color: #2563eb;      /* Color principal */
    --success-color: #059669;      /* Color de éxito */
    --danger-color: #dc2626;       /* Color de error */
    /* ... más variables ... */
}
```

### Agregar Páginas
1. Crea el archivo PHP siguiendo el patrón existente
2. Incluye verificación de autenticación
3. Agrega enlace en `includes/sidebar.php`
4. Mantén consistencia visual

### Modificar Sidebar
- **Ancho**: Cambiar `--sidebar-width` y `--sidebar-collapsed-width`
- **Velocidad**: Modificar `--transition-normal`
- **Enlaces**: Editar `includes/sidebar.php`

## 🚀 Servidor de Desarrollo

### Opción 1: Servidor PHP Integrado
```bash
cd tu-proyecto
php -S localhost:8000
```
Accede en: `http://localhost:8000`

### Opción 2: XAMPP/WAMP/MAMP
1. Copia el proyecto a `htdocs/` o `www/`
2. Inicia Apache y MySQL
3. Accede en: `http://localhost/tu-proyecto`

## 📝 Logs y Depuración

### Logs de Actividad
El sistema registra automáticamente:
- Inicios de sesión exitosos/fallidos
- Cambios de perfil
- Acciones administrativas
- Errores de sistema

### Ver Logs
Los logs se almacenan en la tabla `activity_logs` y son visibles en la página de Reportes.

## 🤝 Contribución

Para contribuir al proyecto:
1. Sigue los estándares de código establecidos
2. Mantén la consistencia visual
3. Documenta cambios importantes
4. Prueba en diferentes dispositivos
5. Actualiza este README si es necesario

## 📄 Licencia

Este proyecto está disponible bajo la licencia MIT. Puedes usarlo, modificarlo y distribuirlo libremente.

## 🆘 Soporte

Si encuentras problemas:
1. Verifica que los requisitos estén cumplidos
2. Revisa la configuración de la base de datos
3. Consulta los logs de error de PHP
4. Verifica permisos de archivos

---

**¡Disfruta usando este sistema de autenticación! 🎉**
