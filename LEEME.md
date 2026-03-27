# Sistema de Gestión de Cotizaciones

## Descripción del Sistema

Sistema completo de gestión de cotizaciones multi-empresa desarrollado en PHP con MySQL. Permite gestionar cotizaciones, clientes, productos, inventario, facturación y generar reportes detallados.

## Características Implementadas

### 1. **Autenticación y Roles**
- Sistema de login con autenticación segura
- 4 Roles de usuario:
  - **Administrador del Sistema**: Acceso total, gestiona empresas, configuración general, API, SMTP
  - **Administrador de Empresa**: Gestiona su empresa, usuarios y configuraciones específicas
  - **Vendedor**: Crea y gestiona cotizaciones, clientes y productos
  - **Facturación**: Acceso exclusivo al módulo de facturación, sin acceso a otros módulos

### 2. **Gestión de Cotizaciones**
- Crear, editar y eliminar cotizaciones
- Búsqueda y filtrado avanzado por estado, fecha, cliente
- Vista móvil responsive
- Estados: Borrador, Enviada, Aceptada, Rechazada, Facturada
- Generación de PDF con diseño profesional
- Envío por correo electrónico y WhatsApp
- Historial de precios por producto y cliente
- Sistema de notificaciones

### 3. **Gestión de Clientes**
- Registro de clientes (personas y empresas)
- Búsqueda por nombre, RUC/DNI, email
- Historial de cotizaciones por cliente
- Vista móvil responsive

### 4. **Gestión de Productos e Inventario**
- Catálogo de productos con código, descripción, marca
- Gestión de múltiples almacenes
- Control de stock por almacén
- Alertas de stock bajo
- Búsqueda y filtros avanzados
- Importación desde API de proveedores

### 5. **Sistema de Facturación**
- Módulo completo de facturación
- Flujo de trabajo:
  - Vendedor solicita facturación de cotización aceptada
  - Usuario de Facturación procesa la solicitud
  - Aprueba con número de factura o rechaza con motivo
  - Estados: Pendiente, En Proceso, Facturado, Rechazado
- Historial de facturación para vendedores
- Panel exclusivo para rol de Facturación
- Notificaciones automáticas

### 6. **Sistema de Reportes**
- **Dashboard General**: Estadísticas globales de la empresa
- **Reporte de Cotizaciones**:
  - Total, por estado, por mes, por usuario
  - Tasa de conversión
  - Cotizaciones recientes
- **Reporte de Clientes**:
  - Total, nuevos, activos
  - Top clientes por monto
  - Clientes por tipo
- **Reporte de Productos**:
  - Total, con stock, stock bajo
  - Productos más cotizados
  - Productos por marca
- **Filtros Disponibles**:
  - Rango de fechas
  - Tipo de reporte
  - **Filtro por vendedor**: Ver reportes de todos los vendedores o de uno específico
- **Exportación**: Excel y PDF

### 7. **Panel de Administración**
- Gestión de usuarios y asignación de roles múltiples
- Configuración de empresa (datos, logo, favicon)
- Configuración de API para importación de productos
- Configuración de SMTP para envío de correos
- Gestión de almacenes
- Diseño moderno con cards y permisos por rol

### 8. **Notificaciones**
- Sistema de notificaciones en tiempo real
- Notificaciones de facturación
- Alertas de stock bajo
- Contador de notificaciones no leídas

### 9. **Multi-tema**
- Tema claro y oscuro
- Cambio dinámico sin recargar página
- Persistencia de preferencia por usuario

### 10. **Responsive Design**
- Vistas móviles optimizadas para cotizaciones y clientes
- Interfaz adaptativa Bootstrap 5
- Iconos Font Awesome

## Estructura del Proyecto

```
cotizacion/
├── config/
│   ├── config.php                 # Configuración de base de datos
│   └── permissions.php            # Sistema de permisos por rol
├── includes/
│   ├── init.php                   # Inicialización del sistema
│   └── functions.php              # Funciones auxiliares
├── lib/                           # Modelos (clases PHP)
│   ├── Auth.php                   # Autenticación
│   ├── Company.php                # Gestión de empresas
│   ├── User.php                   # Gestión de usuarios
│   ├── Customer.php               # Gestión de clientes
│   ├── Product.php                # Gestión de productos
│   ├── Quotation.php              # Gestión de cotizaciones
│   ├── Stock.php                  # Gestión de inventario
│   ├── BillingManager.php         # Gestión de facturación
│   └── Notification.php           # Sistema de notificaciones
├── public/                        # Archivos públicos
│   ├── login.php                  # Página de login
│   ├── dashboard_simple.php       # Dashboard principal
│   ├── admin/                     # Panel de administración
│   │   ├── index.php              # Dashboard admin
│   │   ├── companies.php          # Gestión de empresas
│   │   ├── users.php              # Gestión de usuarios
│   │   ├── settings.php           # Configuración de empresa
│   │   ├── api_settings.php       # Configuración de API
│   │   └── smtp_settings.php      # Configuración de SMTP
│   ├── quotations/                # Módulo de cotizaciones
│   │   ├── index.php              # Lista de cotizaciones
│   │   ├── index_mobile.php       # Vista móvil
│   │   ├── create.php             # Crear cotización
│   │   ├── edit.php               # Editar cotización
│   │   ├── view.php               # Ver detalle
│   │   └── generate_pdf.php       # Generar PDF
│   ├── customers/                 # Módulo de clientes
│   │   ├── index.php              # Lista de clientes
│   │   ├── index_mobile.php       # Vista móvil
│   │   ├── create.php             # Crear cliente
│   │   └── edit.php               # Editar cliente
│   ├── products/                  # Módulo de productos
│   │   ├── index.php              # Lista de productos
│   │   ├── create.php             # Crear producto
│   │   ├── edit.php               # Editar producto
│   │   └── import_from_api.php    # Importar desde API
│   ├── billing/                   # Módulo de facturación
│   │   ├── pending.php            # Solicitudes pendientes
│   │   ├── process.php            # Procesar solicitud
│   │   ├── request.php            # Solicitar facturación
│   │   └── history.php            # Historial de facturación
│   ├── reports/                   # Módulo de reportes
│   │   ├── index.php              # Vista principal de reportes
│   │   ├── dashboard_report.php   # Reporte dashboard
│   │   ├── quotations_report.php  # Reporte de cotizaciones
│   │   ├── customers_report.php   # Reporte de clientes
│   │   ├── products_report.php    # Reporte de productos
│   │   └── export.php             # Exportación a Excel/PDF
│   └── assets/                    # Recursos estáticos
│       ├── css/
│       └── js/
├── uploads/                       # Archivos subidos
│   ├── company/                   # Logos y favicons
│   └── quotations/                # PDFs de cotizaciones
├── vendor/                        # Dependencias Composer
├── install_billing_system.php     # Instalador del sistema de facturación
└── PERMISOS_Y_ROLES.md           # Documentación de permisos

```

## Requisitos del Sistema

### Software Requerido
- PHP 8.0 o superior
- MySQL 5.7 o superior
- Apache o Nginx con mod_rewrite habilitado
- Composer (para dependencias)

### Extensiones PHP Necesarias
- PDO
- pdo_mysql
- mbstring
- gd (para generación de imágenes)
- json
- fileinfo

### Dependencias Composer
- PhpSpreadsheet (para exportación a Excel)
- TCPDF o similar (para generación de PDF)

## Instalación

### 1. Clonar/Copiar el Proyecto
```bash
# Copiar archivos al directorio web
cp -r cotizacion/ /ruta/servidor/web/
```

### 2. Configurar Base de Datos
```bash
# Editar archivo de configuración
nano config/config.php
```

Configurar credenciales:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'cotizador');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
define('BASE_URL', 'http://tudominio.com/cotizacion/public');
```

### 3. Crear Base de Datos
```sql
CREATE DATABASE cotizador CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Importar esquema inicial (usar el script SQL de instalación original)

### 4. Instalar Sistema de Facturación
```bash
# Acceder desde navegador
http://tudominio.com/cotizacion/install_billing_system.php
```

Esto creará:
- Tabla `quotation_billing_tracking`
- Columnas en `quotations`: `billing_status`, `invoice_number`
- Rol "Facturación"
- Índices necesarios

### 5. Instalar Dependencias Composer
```bash
cd /ruta/servidor/web/cotizacion
composer install
```

### 6. Configurar Permisos
```bash
# Dar permisos de escritura a directorios
chmod -R 755 uploads/
chmod -R 755 public/quotations/
```

### 7. Configurar Roles y Permisos

Los roles ya están creados en la base de datos:
- Administrador del Sistema (ID: 1)
- Administrador de Empresa (ID: 2)
- Vendedor (ID: 3)
- Facturación (ID: 4)

Asignar roles a usuarios desde: `Admin Panel > Usuarios > Editar Usuario`

## Configuración Inicial

### 1. Primer Acceso
- Usuario: `admin`
- Contraseña: (la configurada en la instalación inicial)

### 2. Configurar Empresa
`Admin Panel > Configuración de Empresa`
- Nombre de empresa
- RUC/Tax ID
- Dirección, teléfono, email
- Logo y favicon

### 3. Configurar SMTP (opcional)
`Admin Panel > Configuración de Correo`
- Servidor SMTP
- Puerto
- Usuario y contraseña
- Habilitar TLS/SSL

### 4. Configurar API de Productos (opcional)
`Admin Panel > Configuración de API`
- URL del API
- Token de autenticación
- Mapeo de campos

### 5. Crear Usuarios
`Admin Panel > Gestión de Usuarios`
- Crear usuarios para cada rol
- Asignar múltiples roles si es necesario
- Vendedores pueden tener permiso para ver todas las cotizaciones

### 6. Crear Almacenes
`Admin Panel > Inventario > Almacenes`
- Crear al menos un almacén principal

## Uso del Sistema

### Para Vendedores
1. Registrar clientes nuevos
2. Importar o crear productos
3. Crear cotizaciones seleccionando productos
4. Enviar cotización por email/WhatsApp
5. Marcar cotización como Aceptada
6. Solicitar facturación desde la cotización aceptada
7. Ver historial de facturación

### Para Usuario de Facturación
1. Login automático redirige a panel de facturación
2. Ver solicitudes pendientes con estadísticas
3. Procesar solicitudes:
   - Aprobar: Ingresar número de factura
   - Rechazar: Indicar motivo
4. Solo tiene acceso al módulo de facturación

### Para Administradores
1. Acceso completo al sistema
2. Ver reportes globales o por vendedor
3. Gestionar usuarios y permisos
4. Configurar parámetros del sistema
5. Exportar reportes a Excel/PDF

## Características de Seguridad

- Contraseñas hasheadas con PHP password_hash()
- Protección contra SQL Injection (PDO con prepared statements)
- Protección contra XSS (htmlspecialchars en todas las salidas)
- Control de sesiones con timeout
- Validación de permisos en cada página
- Sistema de roles granular

## Tareas Pendientes / Mejoras Futuras

### Alta Prioridad
- [ ] Implementar sistema de backup automático de base de datos
- [ ] Agregar logs de auditoría (quién hizo qué y cuándo)
- [ ] Implementar recuperación de contraseña por email
- [ ] Agregar validación de RUC/DNI con API de SUNAT/RENIEC (Perú)
- [ ] Implementar caché para mejorar rendimiento de reportes

### Media Prioridad
- [ ] Agregar calendario de seguimiento de cotizaciones
- [ ] Implementar recordatorios automáticos para cotizaciones pendientes
- [ ] Agregar firma digital en PDFs
- [ ] Implementar sistema de comisiones para vendedores
- [ ] Agregar gráficos interactivos en reportes (Chart.js)
- [ ] Implementar API REST para integraciones externas

### Baja Prioridad
- [ ] Agregar chat interno entre usuarios
- [ ] Implementar sistema de tareas/recordatorios
- [ ] Agregar soporte multi-idioma (i18n)
- [ ] Implementar dashboard personalizable por usuario
- [ ] Agregar templates personalizables para PDFs de cotizaciones

## Problemas Conocidos

### Resueltos
- ✅ Error de navbar en páginas de facturación (solucionado con navbar inline)
- ✅ Roles en inglés causaban problemas de permisos (cambiados a español)
- ✅ Admin perdía acceso al cambiar roles (implementado sistema de permisos)
- ✅ Métodos faltantes en modelos para reportes (todos implementados)
- ✅ PhpSpreadsheet métodos incorrectos en exportación (corregidos)
- ✅ Filtro de vendedor en reportes no existía (implementado completamente)

### Por Resolver
- [ ] Optimizar consultas SQL de reportes con muchos datos (agregar índices adicionales)
- [ ] Mejorar rendimiento de carga en vista móvil con muchos registros (implementar paginación)
- [ ] Validar formato de números de teléfono al guardar
- [ ] Agregar opción para eliminar notificaciones antiguas automáticamente

## Mantenimiento

### Backup
Realizar backups periódicos de:
- Base de datos MySQL
- Directorio `uploads/`
- Archivo `config/config.php`

```bash
# Backup de base de datos
mysqldump -u usuario -p cotizador > backup_$(date +%Y%m%d).sql

# Backup de archivos
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz uploads/
```

### Actualización
Antes de actualizar:
1. Realizar backup completo
2. Probar en entorno de desarrollo
3. Revisar cambios en base de datos
4. Actualizar dependencias de Composer si es necesario

### Monitoreo
- Revisar logs de errores PHP regularmente
- Monitorear espacio en disco (directorio uploads/)
- Verificar que el envío de emails funcione correctamente
- Revisar rendimiento de consultas lentas

## Solución de Problemas

### Error: "No se puede conectar a la base de datos"
- Verificar credenciales en `config/config.php`
- Verificar que MySQL esté corriendo
- Verificar permisos del usuario de base de datos

### Error: "Permiso denegado al subir archivos"
- Verificar permisos del directorio `uploads/`
- Verificar que el usuario del servidor web tenga permisos de escritura

### Error: "No se pueden enviar emails"
- Verificar configuración SMTP en Admin Panel
- Verificar que el servidor permita conexiones SMTP salientes
- Probar credenciales de email manualmente

### Reportes no cargan o muy lentos
- Verificar índices en base de datos
- Reducir rango de fechas
- Filtrar por vendedor específico en lugar de todos

## Soporte Técnico

### Documentación Adicional
- Ver archivo `PERMISOS_Y_ROLES.md` para detalles del sistema de permisos
- Revisar comentarios en código para detalles de implementación

### Contacto
Para soporte o consultas sobre el sistema, contactar al equipo de desarrollo.

## Licencia
[Definir licencia del proyecto]

## Créditos
Desarrollado con:
- PHP
- MySQL
- Bootstrap 5
- Font Awesome
- PhpSpreadsheet
- TCPDF

---

**Versión**: 1.0.0
**Última actualización**: Octubre 2025
**Estado**: Listo para producción (con tareas pendientes opcionales)
