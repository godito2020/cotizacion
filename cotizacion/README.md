# Sistema de Cotizaciones Pro

Este es un sistema de cotizaciones multi-empresa (aunque actualmente configurado para una empresa principal por instalación) desarrollado en PHP y MySQL. Permite la gestión de productos, stock por almacén, clientes, usuarios y la creación y seguimiento de cotizaciones.

## Características Principales

*   Gestión de Múltiples Empresas (infraestructura base, foco actual en una empresa por instancia).
*   Registro de Productos con gestión de stock detallado por almacén.
*   Importación de Productos y Stock desde archivos CSV (formato Excel).
*   Registro y Gestión de Clientes con placeholder para consulta a SUNAT/RENIEC (Perú).
*   Gestión de Usuarios con roles (Administrador, Vendedor, Almacenista).
*   Módulo de Almacenes.
*   Creación y Gestión de Cotizaciones:
    *   Adición dinámica de productos.
    *   Cálculo de precios, descuentos (por línea y globales) e impuestos (IGV).
    *   Generación de PDF (placeholder).
    *   Envío por Email (placeholder).
    *   Compartir por WhatsApp (placeholder).
*   Panel de Configuración General:
    *   Datos de la empresa (logo, RUC, dirección, etc.).
    *   Configuración de APIs, correo, moneda, impuestos.
*   Diseño Responsivo para uso en dispositivos móviles.
*   Script de Instalación para configuración inicial de la base de datos y administrador.

## Requisitos del Servidor

*   PHP 7.2 o superior (recomendado 7.4+).
*   MySQL 5.2.1 o superior (recomendado 5.7+ o MariaDB 10.2+).
*   Extensiones PHP:
    *   `mysqli` (para la conexión a la base de datos).
    *   `pdo_mysql` (recomendado, aunque la implementación actual usa mysqli).
    *   `gd` (para manipulación de imágenes, ej: logos).
    *   `fileinfo` (para validación de tipos de archivo).
    *   `openssl` (para `password_hash` y funciones de encriptación si se usan).
    *   `json` (comúnmente habilitada, usada para AJAX).
    *   `mbstring` (para manejo de strings multibyte, útil con UTF-8).
*   Servidor Web (Apache con `mod_rewrite` o Nginx).
*   (Opcional, para importación Excel avanzada) Acceso a Composer para instalar librerías como `PhpSpreadsheet`. La versión actual incluye una simulación para CSV.

## Instalación

1.  **Descargar/Clonar el Proyecto:**
    *   Coloque los archivos del proyecto en el directorio raíz de su servidor web (ej: `htdocs`, `www`, `public_html`) o en un subdirectorio.

2.  **Crear la Base de Datos:**
    *   Cree una base de datos MySQL vacía (ej: `cotizador_db`) con cotejamiento `utf8mb4_unicode_ci`.
    *   Cree un usuario de base de datos con permisos para esta base de datos.

3.  **Ejecutar el Instalador Web:**
    *   Abra su navegador y navegue a la carpeta `install/` dentro de la URL donde colocó el proyecto. Ejemplo: `http://localhost/cotizacion/install/` o `http://suservidor.com/install/`.
    *   Siga los pasos del instalador:
        *   **Paso 1:** Verificación de requisitos del servidor.
        *   **Paso 2:** Ingrese los detalles de conexión a su base de datos (host, nombre de BD, usuario, contraseña).
        *   **Paso 3:** El instalador creará las tablas necesarias (usando `install/database.sql`).
        *   **Paso 4:** Configure el usuario administrador principal del sistema.
        *   **Paso 5:** ¡Instalación completada!

4.  **¡IMPORTANTE - Seguridad Post-Instalación!**
    *   Una vez completada la instalación, **elimine o renombre la carpeta `install/`** de su servidor. Esto es crucial para la seguridad de su sistema.

5.  **Permisos de Carpeta:**
    *   Asegúrese de que las siguientes carpetas tengan permisos de escritura para el servidor web. En cPanel, puede usar el "Administrador de Archivos" para establecer permisos (generalmente `755` para directorios y `644` para archivos). Para las carpetas de subida, `755` o `775` pueden ser necesarios si el servidor ejecuta PHP como un usuario diferente al dueño de los archivos:
        *   `uploads/logos/` (para los logos de empresa)
        *   `uploads/productos/` (para las imágenes de productos)
        *   `uploads/temp_import/` (usada temporalmente durante la importación de Excel/CSV)
        *   `config/` (esta carpeta necesita permisos de escritura **solo durante el proceso de instalación** para que `install.php` pueda crear el archivo `config.php`. Después de la instalación, se recomienda revertir los permisos de `config/` a solo lectura para el propietario y el servidor, por ejemplo `chmod 644 config.php` y `chmod 755 config/` o más restrictivo si es posible).

6.  **Configuración de PHP:**
    *   A través de su cPanel ("Seleccionar Versión de PHP" o "MultiPHP Manager"), asegúrese de que está utilizando una versión de PHP compatible (7.2 o superior) y que las extensiones requeridas (mysqli, gd, fileinfo, openssl, json, mbstring) están habilitadas.
    *   **Límites de PHP:** Para funcionalidades como la importación de archivos grandes o la generación de reportes/PDFs complejos, podría ser necesario ajustar directivas de PHP como `memory_limit`, `max_execution_time`, `upload_max_filesize` y `post_max_size` desde el editor de configuración PHP de cPanel.

## Acceso al Sistema

*   **Panel de Administración:** `http://su_url_base/admin.php`
*   **Login:** `http://su_url_base/login.php`

    Use las credenciales del administrador creadas durante la instalación.

## Uso Básico

1.  **Configuración Inicial:**
    *   Acceda al panel de administración.
    *   Vaya a `Configuración > Datos de la Empresa` y complete toda la información de su empresa, incluyendo el logo.
    *   Vaya a `Configuración > Configuraciones del Sistema` y revise/ajuste las opciones de correo, APIs (si las va a usar), moneda por defecto, porcentaje de IGV, etc.

2.  **Gestión de Datos Maestros:**
    *   **Almacenes:** Registre los almacenes físicos donde gestionará stock.
    *   **Productos:** Cree sus productos manualmente o impórtelos desde un archivo CSV (vía `Productos > Importar desde Excel`). Asegúrese de asignar stock a los almacenes correspondientes.
    *   **Clientes:** Registre a sus clientes.
    *   **Usuarios:** Cree cuentas para otros usuarios si es necesario (vendedores, almacenistas).

3.  **Crear Cotizaciones:**
    *   Vaya a `Cotizaciones > Crear Nueva Cotización`.
    *   Seleccione el cliente, complete los datos de la cabecera.
    *   Añada productos al detalle, especificando cantidades y posibles descuentos por línea.
    *   Aplique descuentos globales si es necesario.
    *   Guarde la cotización. Podrá verla, editarla (si está en borrador), generar PDF, etc.

## Desarrollo y Contribuciones (Placeholder)

*   Este proyecto es un desarrollo base.
*   Para contribuir:
    1.  Fork el repositorio.
    2.  Cree una nueva rama (`git checkout -b feature/nueva-funcionalidad`).
    3.  Realice sus cambios.
    4.  Commit sus cambios (`git commit -am 'Añade nueva funcionalidad'`).
    5.  Push a la rama (`git push origin feature/nueva-funcionalidad`).
    6.  Cree un nuevo Pull Request.

## Licencia (Placeholder)

Este proyecto se distribuye bajo la licencia MIT (o la que se defina).
