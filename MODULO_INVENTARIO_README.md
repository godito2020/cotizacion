# Módulo de Inventario Físico - COTI

## Estado: COMPLETADO (Pendiente ejecutar SQL)

**Fecha de implementación:** 2026-02-05
**Desarrollador:** Claude AI (Opus 4.5)

---

## Descripción General

Módulo para gestión de inventarios físicos colaborativos en tiempo real. Permite a múltiples usuarios realizar conteos simultáneos en diferentes almacenes, con supervisión en tiempo real y generación de reportes.

### Características Principales

- ✅ Dos perfiles de usuario (Supervisor y Usuario Inventario)
- ✅ Control de sesiones (abrir/cerrar inventario)
- ✅ Selección obligatoria de almacén
- ✅ Búsqueda de productos con stock del sistema (COBOL)
- ✅ Registro múltiple del mismo producto
- ✅ Historial de ediciones con trazabilidad
- ✅ Monitor en tiempo real (polling cada 3 segundos)
- ✅ Reportes: coincidentes, faltantes, sobrantes
- ✅ Ranking de usuarios por precisión
- ✅ Exportación a Excel

---

## URLs de Acceso

| Entorno | URL |
|---------|-----|
| Local | `http://localhost/coti/inventario` |
| Producción | `https://coti.gsm.pe/inventario` |

---

## Archivos Creados

### 1. Script SQL (NO EJECUTADO AÚN)

```
scripts/install_inventory_module.sql
```

**Crea:**
- 2 roles nuevos: `Supervisor Inventario`, `Usuario Inventario`
- 5 tablas:
  - `inventory_sessions` - Sesiones de inventario
  - `inventory_session_warehouses` - Almacenes por sesión
  - `inventory_entries` - Registros de conteo
  - `inventory_entry_history` - Historial de ediciones
  - `inventory_session_users` - Asignación de usuarios
- 3 vistas SQL para reportes

### 2. Clases PHP (lib/)

| Archivo | Descripción |
|---------|-------------|
| `lib/InventorySession.php` | Gestión de sesiones (crear, cerrar, verificar permisos) |
| `lib/InventoryEntry.php` | CRUD de entradas, búsqueda de productos, discrepancias |
| `lib/InventoryReports.php` | Estadísticas, rankings, exportación Excel |

### 3. APIs (public/inventario/api/)

| Endpoint | Método | Descripción |
|----------|--------|-------------|
| `search_products.php` | GET | Buscar productos con stock |
| `register_entry.php` | POST | Registrar conteo |
| `update_entry.php` | POST | Editar entrada existente |
| `get_entries.php` | GET | Obtener entradas (propias o todas) |
| `get_session_status.php` | GET | Estado de sesión activa |
| `get_realtime_data.php` | GET | Datos para monitor en tiempo real |
| `open_session.php` | POST | Crear nueva sesión (admin) |
| `close_session.php` | POST | Cerrar sesión (admin) |
| `export_excel.php` | GET | Exportar a Excel |

### 4. Vistas Usuario Inventario (public/inventario/)

| Archivo | Descripción |
|---------|-------------|
| `index.php` | Redirección según rol |
| `select_warehouse.php` | Selección obligatoria de almacén |
| `dashboard.php` | Panel principal con búsqueda |
| `my_entries.php` | Historial de mis registros |
| `export.php` | Exportar mis datos a Excel |

### 5. Vistas Panel Admin (public/inventario/admin/)

| Archivo | Descripción |
|---------|-------------|
| `index.php` | Dashboard del supervisor |
| `realtime_monitor.php` | Monitor en tiempo real |
| `reports.php` | Reportes y ranking de usuarios |
| `sessions.php` | Historial de sesiones |

### 6. Archivo Modificado

```
config/permissions.php
```

**Cambios:**
- Agregados 2 nuevos roles con permisos
- Agregados métodos helper:
  - `canAccessInventoryPanel()`
  - `canManageInventorySessions()`
  - `canRegisterInventory()`
  - `canViewAllInventory()`
  - `canGenerateInventoryReports()`

---

## Pasos para Completar el Despliegue

### Paso 1: Ejecutar el Script SQL

Conectarse al servidor MySQL local y ejecutar:

```sql
SOURCE z:/coti/scripts/install_inventory_module.sql;
```

O copiar el contenido del archivo y ejecutarlo en phpMyAdmin/MySQL Workbench.

### Paso 2: Verificar Creación de Tablas

```sql
SHOW TABLES LIKE 'inventory%';
```

Debe mostrar:
- inventory_sessions
- inventory_session_warehouses
- inventory_entries
- inventory_entry_history
- inventory_session_users

### Paso 3: Verificar Creación de Roles

```sql
SELECT * FROM roles WHERE role_name LIKE '%Inventario%';
```

Debe mostrar:
- Supervisor Inventario
- Usuario Inventario

### Paso 4: Asignar Roles a Usuarios

Desde el panel de administración existente, asignar los roles:
- **Supervisor Inventario**: Para administradores que controlan las sesiones
- **Usuario Inventario**: Para operadores que registran conteos

### Paso 5: Probar el Módulo

1. Login como Supervisor Inventario
2. Ir a `/inventario` → Panel de Admin
3. Crear nueva sesión de inventario
4. Seleccionar almacenes a inventariar
5. Login como Usuario Inventario (otra sesión)
6. Ir a `/inventario` → Seleccionar almacén
7. Buscar productos y registrar conteos
8. Verificar en monitor tiempo real (admin)

---

## Estructura de Base de Datos

### inventory_sessions
```sql
- id (PK)
- company_id (FK → companies)
- name
- description
- status (Open/Closed/Cancelled)
- created_by (FK → users)
- opened_at
- closed_at
- closed_by (FK → users)
- close_notes
```

### inventory_entries
```sql
- id (PK)
- session_id (FK → inventory_sessions)
- user_id (FK → users)
- warehouse_number
- product_code
- product_description
- system_stock
- counted_quantity
- difference (calculado)
- comments
- is_edited
- created_at
- updated_at
```

---

## Flujos de Usuario

### Usuario Inventario
```
Login → Verificar sesión activa → Seleccionar almacén →
Dashboard → Buscar producto → Registrar conteo →
Ver mis registros → Editar si necesario → Exportar
```

### Supervisor Inventario
```
Login → Panel Admin → Crear sesión → Seleccionar almacenes →
Monitor tiempo real → Ver progreso → Ver discrepancias →
Generar reportes → Cerrar sesión → Exportar resultados
```

---

## Tecnologías Utilizadas

- **Backend:** PHP 8.0+
- **Base de datos:** MySQL (local) + COBOL (stock)
- **Frontend:** Bootstrap 5.3, Font Awesome 6.4
- **AJAX:** Fetch API con JSON
- **Excel:** PhpSpreadsheet (ya instalado)
- **Tiempo real:** Polling cada 3 segundos

---

## Notas Importantes

1. **Stock se lee de COBOL**: La tabla `vista_almacenes_anual` con columnas por mes (enero, febrero, etc.)

2. **Almacenes de BD local**: Tabla `desc_almacen` con `numero_almacen` y `nombre`

3. **Múltiples registros permitidos**: Un usuario puede registrar el mismo producto varias veces (historial completo)

4. **Sesión única por empresa**: Solo puede haber una sesión de inventario abierta a la vez por empresa

5. **Sin ejecución de SQL**: El script SQL NO ha sido ejecutado. Debe hacerse manualmente en el servidor MySQL local.

---

## Posibles Mejoras Futuras

- [ ] Asignación de usuarios específicos a almacenes
- [ ] Notificaciones push cuando se cierra la sesión
- [ ] Comparación con inventarios anteriores
- [ ] Integración con módulo de cotizaciones (stock en tiempo real)
- [ ] App móvil nativa con escáner de código de barras
- [ ] Cierre automático de sesión por tiempo

---

## Contacto / Soporte

Para continuar el desarrollo desde otra PC, cargar este archivo en el contexto del chat y mencionar que se desea continuar con el módulo de inventario.

**Último estado:** Código completado, pendiente ejecutar SQL en base de datos.
