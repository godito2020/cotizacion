# Sistema de Plantillas CotiRapi - Guía de Instalación y Uso

## 📋 Resumen

Se ha implementado un sistema completo de plantillas personalizables para la función **CotiRapi** (Cotización Rápida para WhatsApp).

Ahora los administradores pueden configurar diferentes formatos de presentación en texto plano desde el panel de administración.

---

## 🔧 Instalación

### Paso 1: Ejecutar el instalador

Acceder a la siguiente URL en tu navegador (debes estar autenticado como Administrador del Sistema):

```
https://coti.gsm.pe/public/admin/install_cotirapi_templates.php
```

Este script:
- ✅ Crea la tabla `cotirapi_templates` en la base de datos
- ✅ Inserta 3 plantillas por defecto para cada empresa:
  - **Plantilla Estándar** (con emojis, predeterminada)
  - **Plantilla Simple** (texto plano básico)
  - **Plantilla Profesional** (con caracteres especiales)

---

## 🎨 Gestión de Plantillas

### Acceder al Panel de Plantillas

URL: `https://coti.gsm.pe/public/admin/cotirapi_templates.php`

Desde aquí puedes:
- ✏️ Crear nuevas plantillas
- 📝 Editar plantillas existentes
- 🗑️ Eliminar plantillas (excepto la predeterminada)
- ⭐ Establecer cuál plantilla es la predeterminada
- 🏢 (Solo Sysadmin) Gestionar plantillas de diferentes empresas

---

## 📝 Variables Disponibles

### Variables Generales (Header)
- `{CUSTOMER_NAME}` - Nombre del cliente
- `{DATE}` - Fecha actual
- `{CURRENCY}` - Símbolo de moneda (S/ o $)

### Variables por Item (se repite para cada producto)
- `{ITEM_NUMBER}` - Número de item (1, 2, 3...)
- `{CODE}` - Código del producto
- `{CODE_LINE}` - Línea completa de código (solo si existe código)
- `{DESCRIPTION}` - Descripción del producto
- `{QUANTITY}` - Cantidad
- `{UNIT_PRICE}` - Precio unitario (ya con descuento aplicado)
- `{DISCOUNT_LINE}` - Línea de descuento (solo si hay descuento > 0)
- `{TOTAL}` - Total del item
- `{IMAGE_URL}` - URL de la imagen del producto (solo si existe)
- `{IMAGE_LINE}` - Línea completa con URL de imagen (solo si existe imagen)

### Variables de Totales (Footer)
- `{SUBTOTAL}` - Subtotal sin IGV
- `{IGV}` - Monto de IGV (18%)
- `{GRAND_TOTAL}` - Total general

---

## 🚀 Uso en Cotizaciones

### En create_dev.php (desarrollo)

1. Ingresa los productos de la cotización
2. Haz clic en el botón **"CotiRapi"** (amarillo, con icono de rayo ⚡)
3. El sistema:
   - Carga automáticamente la plantilla predeterminada de tu empresa
   - Reemplaza todas las variables con los datos reales
   - Muestra el texto generado en un modal
4. Opciones disponibles:
   - **Copiar al Portapapeles** - Copia el texto para pegarlo manualmente
   - **Abrir WhatsApp** - Abre WhatsApp Web con el texto pre-cargado

---

## 📂 Archivos Creados/Modificados

### Nuevos Archivos

1. **`v:\coti\scripts\create_cotirapi_templates.sql`**
   - Script SQL con la estructura de la tabla
   - Plantillas por defecto para todas las empresas

2. **`v:\coti\public\admin\cotirapi_templates.php`**
   - Interfaz de administración de plantillas
   - CRUD completo (Crear, Leer, Actualizar, Eliminar)
   - Selector de empresa (para sysadmin)
   - Guía de variables disponibles

3. **`v:\coti\public\admin\install_cotirapi_templates.php`**
   - Instalador automático del sistema
   - Crea tabla e inserta plantillas por defecto
   - Muestra reporte detallado del proceso

4. **`v:\coti\public\api\get_cotirapi_template.php`**
   - API endpoint para obtener la plantilla predeterminada
   - Retorna JSON con los datos de la plantilla
   - Fallback a plantilla básica si no existe ninguna

### Archivos Modificados

1. **`v:\coti\public\quotations\create_dev.php`**
   - Función `generateCotiRapi()` actualizada a async
   - Carga plantillas dinámicamente desde el servidor
   - Reemplazo inteligente de variables
   - Manejo de códigos y descuentos opcionales

---

## 🎯 Ejemplo de Plantilla

### Plantilla Estándar (predeterminada)

**Header:**
```
🏪 *COTIZACIÓN RÁPIDA*
━━━━━━━━━━━━━━━━━━━━━
👤 *Cliente:* {CUSTOMER_NAME}
📅 *Fecha:* {DATE}
━━━━━━━━━━━━━━━━━━━━━
```

**Item:**
```
📦 *{ITEM_NUMBER}. {DESCRIPTION}*
{CODE_LINE}   📊 Cantidad: {QUANTITY}
   💰 Precio: {CURRENCY} {UNIT_PRICE}
{DISCOUNT_LINE}   💵 Total: {CURRENCY} {TOTAL}
```

**Footer:**
```
━━━━━━━━━━━━━━━━━━━━━

💵 *Subtotal:* {CURRENCY} {SUBTOTAL}
📊 *IGV (18%):* {CURRENCY} {IGV}
💰 *TOTAL:* {CURRENCY} {GRAND_TOTAL}

━━━━━━━━━━━━━━━━━━━━━

✅ _Precios incluyen IGV_
📍 _Stock sujeto a disponibilidad_
💬 _Consultas al WhatsApp_
```

---

## 🔐 Permisos

- **Administrador del Sistema**:
  - Puede gestionar plantillas de todas las empresas
  - Selector de empresa visible en el panel

- **Administrador de Empresa**:
  - Solo puede gestionar plantillas de su propia empresa
  - No ve selector de empresa

- **Vendedores**:
  - Usan automáticamente la plantilla predeterminada de su empresa
  - No tienen acceso al panel de gestión

---

## ✅ Próximos Pasos

1. **Ejecutar el instalador** visitando `install_cotirapi_templates.php`
2. **Probar en desarrollo** usando `create_dev.php`
3. **Personalizar plantillas** desde el panel de administración
4. **Una vez confirmado**, replicar cambios a `create.php` en producción

---

## 🐛 Solución de Problemas

### No se muestra ninguna plantilla
- Verificar que se ejecutó el instalador correctamente
- Revisar que existe al menos una plantilla con `is_active = 1`

### Error al cargar plantilla
- Verificar que el archivo `get_cotirapi_template.php` existe
- Revisar permisos de acceso a la API
- Comprobar conexión a base de datos

### Las variables no se reemplazan
- Verificar que las variables están escritas exactamente como `{VARIABLE}`
- Asegurarse de que no hay espacios dentro de las llaves
- Revisar que la plantilla tiene el formato correcto

---

## 📞 Soporte

Si encuentras algún problema, revisa:
- Console del navegador (F12) para errores JavaScript
- Logs del servidor PHP
- Tabla `cotirapi_templates` en la base de datos

---

**Fecha de implementación:** 2026-01-23
**Versión:** 1.0
