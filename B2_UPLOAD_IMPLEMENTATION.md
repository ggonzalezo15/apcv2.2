# BackBlaze B2 Upload Implementation

## Resumen

Se ha implementado exitosamente la funcionalidad de upload de archivos a BackBlaze B2 con las siguientes características:

- ✅ Integración con BackBlaze B2 usando AWS SDK compatible
- ✅ Compresión automática de imágenes 
- ✅ Organización de archivos por carpetas de años
- ✅ Creación automática de carpetas
- ✅ API completa para manejo de archivos
- ✅ Archivo de prueba frontend

## Archivos Creados/Modificados

### 1. Dependencias (`composer.json`)
```json
{
    "require": {
        "tecnickcom/tcpdf": "^6.10",
        "aws/aws-sdk-php": "^3.0",
        "spatie/image-optimizer": "^1.7",
        "intervention/image": "^2.7"
    }
}
```

### 2. Configuración (`config.php`)
- Agregadas credenciales de BackBlaze B2
- Configuración de archivos permitidos
- Función `getB2Config()` para obtener configuración

### 3. Servicio de Upload (`includes/B2FileUploader.php`)
- Clase completa para manejo de archivos
- Compresión automática de imágenes
- Validación de archivos
- Creación automática de carpetas por años
- Métodos para upload, download, delete y URLs firmadas

### 4. API Controller (`api/upload/B2UploadController.php`)
- Endpoints completos para todas las operaciones
- Manejo de errores y validaciones
- Integración con base de datos
- Funciones para testing

### 5. Migración de Base de Datos (`migrate_b2_columns.sql`)
- Agregadas columnas `file_key` y `compressed`
- Índices para optimización
- Comentarios para documentación

### 6. Archivo de Prueba (`test_b2_upload.php`)
- Interface completa para testing
- Drag & drop funcional
- Preview de archivos
- Testing de conexión
- Upload individual y múltiple

## Configuración de BackBlaze B2

### Credenciales en `config.php`:
```php
define('B2_KEY_ID', '0058ab3df0e6ae30000000009');
define('B2_APPLICATION_KEY', 'K005gUIaeFIdQOggIlVrHubsVM/rqsA');
define('B2_BUCKET_NAME', 'webapp-apcuadrev2');
define('B2_REGION', 'us-west-000');
define('B2_ENDPOINT', 'https://s3.us-west-000.backblazeb2.com');
```

## Características Implementadas

### 1. Compresión de Imágenes
- Redimensionamiento automático (máx. 1920x1080)
- Compresión JPEG con calidad 85%
- Solo se usa la versión comprimida si reduce el tamaño significativamente (>20%)

### 2. Organización por Años
- Estructura: `expenses/2024/archivo.jpg`
- Creación automática de carpetas por año
- Basado en la fecha del gasto

### 3. Validaciones
- Tipos de archivo: JPG, PNG, PDF
- Tamaño máximo: 2MB por archivo
- Máximo 4 archivos por gasto
- Validación de integridad

### 4. Base de Datos
- Tabla `expense_attachments` actualizada
- Columnas nuevas: `file_key`, `compressed`
- Índices para optimización

## API Endpoints

### 1. Upload Múltiple
```
POST /api/upload/B2UploadController.php?action=uploadFiles
```
**Parámetros:**
- `expense_id`: ID del gasto
- `expense_date`: Fecha del gasto
- `files[]`: Array de archivos

### 2. Upload Individual
```
POST /api/upload/B2UploadController.php?action=uploadSingleFile
```
**Parámetros:**
- `expense_id`: ID del gasto
- `expense_date`: Fecha del gasto
- `file`: Archivo único

### 3. Download
```
GET /api/upload/B2UploadController.php?action=downloadFile&file_id=UUID
```

### 4. Delete
```
POST /api/upload/B2UploadController.php?action=deleteFile
```
**Parámetros:**
- `file_id`: ID del archivo

### 5. URL Firmada
```
GET /api/upload/B2UploadController.php?action=getSignedUrl&file_id=UUID&expiration=+1hour
```

### 6. Test de Conexión
```
GET /api/upload/B2UploadController.php?action=testConnection
```

## Cómo Probar

### 1. Acceder al archivo de prueba
```
http://localhost/apv2.1/test_b2_upload.php
```

### 2. Pasos para testing:
1. **Test de Conexión**: Verificar que se puede conectar a BackBlaze B2
2. **Upload de Archivos**: Arrastrar o seleccionar archivos
3. **Verificar Compresión**: Subir imágenes grandes para ver compresión
4. **Verificar Carpetas**: Cambiar fecha del gasto para probar carpetas por año
5. **Upload Individual**: Probar upload de un solo archivo

### 3. Verificar en BackBlaze B2
- Los archivos deben aparecer en el bucket `webapp-apcuadrev2`
- Organizados en carpetas por año: `expenses/2024/`
- Nombres únicos con timestamp

## Estructura de Archivos en B2

```
webapp-apcuadrev2/
├── expenses/
│   ├── 2024/
│   │   ├── expense-id_20241201123456_abc123.jpg
│   │   ├── expense-id_20241201123456_def456.pdf
│   │   └── ...
│   ├── 2025/
│   │   └── ...
│   └── ...
```

## Próximos Pasos

Una vez verificado el funcionamiento:

1. **Integrar con expenses.php**: Reemplazar la lógica actual de upload
2. **Actualizar ExpenseController.php**: Usar la nueva API
3. **Migrar archivos existentes**: Opcional, mover archivos locales a B2
4. **Cleanup**: Eliminar archivos de prueba

## Beneficios

- ✅ **Escalabilidad**: Los archivos se almacenan en la nube
- ✅ **Compresión**: Menos uso de ancho de banda y almacenamiento
- ✅ **Organización**: Estructura clara por años
- ✅ **Seguridad**: URLs firmadas para acceso temporal
- ✅ **Confiabilidad**: BackBlaze B2 con respaldo automático

## Troubleshooting

### Errores Comunes:

1. **Error de conexión**: Verificar credenciales en `config.php`
2. **Archivos no suben**: Verificar permisos y tamaño
3. **Imágenes no comprimen**: Verificar extensión GD en PHP
4. **Carpetas no crean**: Verificar permisos del bucket

### Logs:
- Revisar error logs de PHP
- Revisar logs de la aplicación
- Usar endpoint de `testConnection` para diagnóstico

## Consideraciones de Seguridad

- ✅ Autenticación requerida en todos los endpoints
- ✅ Validación de tipos de archivo
- ✅ Límites de tamaño y cantidad
- ✅ URLs firmadas con expiración
- ✅ Validación de integridad de archivos

## Costos

BackBlaze B2 es muy económico:
- Almacenamiento: $0.005 por GB/mes
- Transferencia: $0.01 por GB (primeros 1GB gratis)
- Transacciones: $0.004 por 1000 operaciones

Para una aplicación típica, el costo mensual sería menor a $5 USD. 