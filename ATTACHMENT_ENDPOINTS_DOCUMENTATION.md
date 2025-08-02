# 📎 Documentación de Endpoints para Attachments

## ✅ **Nuevo Endpoint Implementado: Eliminación Individual de Attachments**

### 🎯 **Problema Resuelto:**
Antes: Solo se podían eliminar attachments cuando se eliminaba un expense completo.
Ahora: Se pueden eliminar attachments individuales manteniendo el expense.

---

## 📋 **Endpoints Disponibles para Attachments**

### 1. **Subir Archivos** 
- **URL:** `api/upload/B2UploadController.php?action=uploadFiles`
- **Método:** POST (multipart/form-data)
- **Parámetros:**
  - `files[]`: Array de archivos
  - `expense_id`: ID del expense
  - `expense_date`: Fecha del expense

### 2. **Subir Archivo Individual**
- **URL:** `api/upload/B2UploadController.php?action=uploadSingleFile`
- **Método:** POST (multipart/form-data)
- **Parámetros:**
  - `file`: Archivo individual
  - `expense_id`: ID del expense
  - `expense_date`: Fecha del expense

### 3. **Listar Attachments de un Expense**
- **URL:** `api/expense/ExpenseController.php?action=getExpenseAttachments&id={expense_id}`
- **Método:** GET

### 4. **Descargar Attachment**
- **URL:** `api/expense/ExpenseController.php?action=downloadAttachment&id={attachment_id}`
- **Método:** GET

### 5. **🆕 Eliminar Attachment Individual** ⭐
- **URL:** `api/expense/ExpenseController.php?action=deleteAttachment&id={attachment_id}`
- **Método:** GET/DELETE
- **Parámetros:**
  - `id`: ID del attachment a eliminar

### 6. **Eliminar via B2UploadController** (Alternativo)
- **URL:** `api/upload/B2UploadController.php?action=deleteFile`
- **Método:** POST (JSON)
- **Body:**
```json
{
  "attachment_id": "uuid-del-attachment"
}
```

---

## 🔄 **Flujo de Eliminación Completo**

### **Cuando se elimina un Expense:**
```
1. deleteExpense() → 
2. Busca todos los attachments → 
3. Para cada attachment: deleteAttachment() → 
4. Elimina de B2 + Elimina de BD → 
5. Elimina expense
```

### **Cuando se elimina un Attachment individual:**
```
1. deleteAttachmentEndpoint() → 
2. Valida attachment_id → 
3. Llama deleteAttachment() → 
4. Elimina de B2 + Elimina de BD → 
5. Mantiene expense intacto
```

---

## 📝 **Ejemplos de Uso**

### **JavaScript/Frontend:**

```javascript
// Eliminar attachment individual
async function deleteAttachment(attachmentId) {
    try {
        const response = await fetch(
            `api/expense/ExpenseController.php?action=deleteAttachment&id=${attachmentId}`,
            { method: 'GET' }
        );
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Attachment eliminado:', result.message);
            // Actualizar UI
            removeAttachmentFromDOM(attachmentId);
        } else {
            console.error('Error:', result.error);
        }
    } catch (error) {
        console.error('Error de red:', error);
    }
}
```

### **cURL:**

```bash
# Eliminar attachment individual
curl -X GET "http://localhost/api/expense/ExpenseController.php?action=deleteAttachment&id=uuid-del-attachment"
```

---

## ✅ **Beneficios de la Implementación:**

1. **Consistencia de API:** Todas las operaciones de attachments están disponibles
2. **UX Mejorada:** Los usuarios pueden eliminar archivos específicos sin perder el expense
3. **Flexibilidad:** Múltiples formas de eliminar según el contexto
4. **Mantenimiento:** Código reutilizable entre diferentes endpoints

---

## 🔒 **Características de Seguridad:**

- ✅ Autenticación requerida (`checkAPIAuthentication()`)
- ✅ Validación de parámetros
- ✅ Verificación de existencia del attachment
- ✅ Manejo robusto de errores
- ✅ Logs detallados para debugging
- ✅ Eliminación tanto de B2 como de BD

---

## 🎯 **Estado Actual de la API:**

| Operación | ExpenseController | B2UploadController | Estado |
|-----------|------------------|--------------------|--------|
| Subir archivos | ❌ | ✅ | Completo |
| Listar attachments | ✅ | ✅ | Completo |
| Descargar | ✅ | ✅ | Completo |
| Eliminar individual | ✅ ⭐ | ✅ | **NUEVO** |
| Eliminar con expense | ✅ | ❌ | Completo |

**🎉 API completamente funcional y consistente!**
