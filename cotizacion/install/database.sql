-- SQL Dump for Sistema de Cotizaciones
-- version 5.2.1 (Comentario para recordar la versión de MySQL del usuario)
-- Host: localhost
-- Generation Time: [FECHA_ACTUAL]
-- PHP Version: [PHP_VERSION]

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00"; -- Se recomienda configurar la zona horaria correcta en PHP/Aplicación

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `cotizador_db` (Nombre por defecto, el instalador puede cambiarlo)
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `empresas`
--
CREATE TABLE IF NOT EXISTS `empresas` (
  `id_empresa` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_comercial` VARCHAR(255) NOT NULL,
  `razon_social` VARCHAR(255) DEFAULT NULL,
  `ruc` VARCHAR(11) UNIQUE DEFAULT NULL,
  `direccion` TEXT DEFAULT NULL,
  `telefono` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `logo_url` VARCHAR(255) DEFAULT NULL, -- Ruta al archivo del logo
  `sitio_web` VARCHAR(255) DEFAULT NULL,
  `terminos_cotizacion` TEXT DEFAULT NULL, -- Términos y condiciones por defecto para cotizaciones
  `moneda_defecto` VARCHAR(3) DEFAULT 'PEN', -- PEN, USD, etc.
  `igv_porcentaje` DECIMAL(5,2) DEFAULT 18.00, -- Porcentaje de IGV/IVA
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Datos por defecto para la tabla `empresas` (opcional, para la primera empresa si es multi-empresa "lite")
-- Si es un sistema donde una instalación es para UNA empresa, esta tabla podría tener solo una fila
-- o los datos de la empresa podrían estar en la tabla `configuraciones`.
-- Para multi-empresa real, cada usuario/cotización se vincularía a una `id_empresa`.
-- Por ahora, asumiremos una configuración de "empresa principal" que se gestiona.
INSERT INTO `empresas` (`id_empresa`, `nombre_comercial`, `razon_social`, `ruc`, `igv_porcentaje`) VALUES (1, 'Mi Empresa (Configurar)', 'Razón Social (Configurar)', '12345678901', 18.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` INT AUTO_INCREMENT PRIMARY KEY,
  `id_empresa` INT DEFAULT 1, -- Si se quiere vincular usuarios a una empresa específica. Para un solo panel, puede ser fijo o NULL.
  `nombre_completo` VARCHAR(150) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol` ENUM('admin', 'vendedor', 'almacenista') NOT NULL DEFAULT 'vendedor', -- Roles del sistema
  `activo` BOOLEAN DEFAULT TRUE,
  `telefono` VARCHAR(50) DEFAULT NULL,
  `ultimo_login` DATETIME DEFAULT NULL,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_empresa`) REFERENCES `empresas`(`id_empresa`) ON DELETE SET NULL -- O CASCADE, dependiendo de la lógica
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--
CREATE TABLE IF NOT EXISTS `clientes` (
  `id_cliente` INT AUTO_INCREMENT PRIMARY KEY,
  `tipo_documento` ENUM('DNI', 'RUC', 'CE', 'PASAPORTE', 'OTRO') NOT NULL DEFAULT 'RUC',
  `numero_documento` VARCHAR(20) NOT NULL,
  `nombre_razon_social` VARCHAR(255) NOT NULL,
  `nombre_comercial` VARCHAR(255) DEFAULT NULL, -- Para RUCs
  `direccion` TEXT DEFAULT NULL,
  `direccion_fiscal` TEXT DEFAULT NULL, -- Para RUCs
  `email` VARCHAR(100) DEFAULT NULL,
  `telefono` VARCHAR(50) DEFAULT NULL,
  `contacto_principal` VARCHAR(150) DEFAULT NULL,
  `notas` TEXT DEFAULT NULL,
  `origen_datos` ENUM('MANUAL', 'SUNAT', 'RENIEC') DEFAULT 'MANUAL', -- Para saber si se obtuvo de API
  `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `activo` BOOLEAN DEFAULT TRUE,
  UNIQUE KEY `idx_tipo_numero_documento` (`tipo_documento`, `numero_documento`)
  -- `id_empresa_asociada` INT DEFAULT 1, -- Si los clientes son por empresa en un sistema multi-empresa real
  -- FOREIGN KEY (`id_empresa_asociada`) REFERENCES `empresas`(`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `almacenes`
--
CREATE TABLE IF NOT EXISTS `almacenes` (
  `id_almacen` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_almacen` VARCHAR(100) NOT NULL,
  `direccion` TEXT DEFAULT NULL,
  `responsable` VARCHAR(150) DEFAULT NULL,
  `activo` BOOLEAN DEFAULT TRUE,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_nombre_almacen` (`nombre_almacen`)
  -- `id_empresa` INT DEFAULT 1,
  -- FOREIGN KEY (`id_empresa`) REFERENCES `empresas`(`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--
CREATE TABLE IF NOT EXISTS `productos` (
  `id_producto` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo_producto` VARCHAR(50) DEFAULT NULL, -- SKU o código interno
  `nombre_producto` VARCHAR(255) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `unidad_medida` VARCHAR(50) DEFAULT 'Unidad', -- Unidad, Caja, Metro, Kg, etc.
  `precio_compra` DECIMAL(10,2) DEFAULT 0.00, -- Precio de compra (referencial)
  `precio_venta_base` DECIMAL(10,2) NOT NULL, -- Precio de venta antes de descuentos/impuestos
  `moneda` VARCHAR(3) DEFAULT 'PEN', -- Moneda del precio_venta_base
  `incluye_igv_en_precio_base` BOOLEAN DEFAULT TRUE, -- Si el precio_venta_base ya incluye IGV
  `imagen_url` VARCHAR(255) DEFAULT NULL,
  `notas_internas` TEXT DEFAULT NULL,
  `activo` BOOLEAN DEFAULT TRUE,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `idx_codigo_producto` (`codigo_producto`)
  -- `id_categoria` INT DEFAULT NULL, -- Para futura categorización
  -- `id_marca` INT DEFAULT NULL, -- Para futura gestión de marcas
  -- `id_empresa` INT DEFAULT 1,
  -- FOREIGN KEY (`id_empresa`) REFERENCES `empresas`(`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `producto_almacen` (Stock)
--
CREATE TABLE IF NOT EXISTS `producto_almacen` (
  `id_producto_almacen` INT AUTO_INCREMENT PRIMARY KEY,
  `id_producto` INT NOT NULL,
  `id_almacen` INT NOT NULL,
  `stock_actual` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `stock_minimo` DECIMAL(10,2) DEFAULT 0.00,
  `ubicacion_especifica` VARCHAR(100) DEFAULT NULL, -- Ej: Estante A, Fila 3
  `ultima_actualizacion_stock` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id_producto`) ON DELETE CASCADE,
  FOREIGN KEY (`id_almacen`) REFERENCES `almacenes`(`id_almacen`) ON DELETE CASCADE,
  UNIQUE KEY `idx_producto_almacen_unico` (`id_producto`, `id_almacen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizaciones`
--
CREATE TABLE IF NOT EXISTS `cotizaciones` (
  `id_cotizacion` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo_cotizacion` VARCHAR(50) UNIQUE NOT NULL, -- Ej: COT-2023-0001
  `id_cliente` INT NOT NULL,
  `id_usuario_creador` INT NOT NULL, -- Usuario que creó la cotización
  `id_empresa` INT DEFAULT 1, -- Empresa que emite la cotización
  `fecha_emision` DATE NOT NULL,
  `fecha_validez` DATE DEFAULT NULL,
  `moneda` VARCHAR(3) NOT NULL DEFAULT 'PEN',
  `subtotal_bruto` DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- Suma de (precio_unitario * cantidad) de detalles
  `descuento_global_tipo` ENUM('NINGUNO','PORCENTAJE', 'MONTO_FIJO') DEFAULT 'NINGUNO',
  `descuento_global_valor` DECIMAL(10,2) DEFAULT 0.00,
  `monto_descuento_global` DECIMAL(12,2) DEFAULT 0.00, -- Calculado a partir del subtotal_bruto y el tipo/valor
  `subtotal_neto` DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- subtotal_bruto - monto_descuento_global
  `monto_igv` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `porcentaje_igv_aplicado` DECIMAL(5,2) DEFAULT 18.00,
  `total_cotizacion` DECIMAL(12,2) NOT NULL DEFAULT 0.00, -- subtotal_neto + monto_igv
  `estado` ENUM('BORRADOR', 'ENVIADA', 'ACEPTADA', 'RECHAZADA', 'ANULADA', 'VENCIDA') NOT NULL DEFAULT 'BORRADOR',
  `observaciones_publicas` TEXT DEFAULT NULL, -- Para el cliente
  `observaciones_internas` TEXT DEFAULT NULL, -- Para uso interno
  `terminos_condiciones` TEXT DEFAULT NULL, -- Puede heredar de la empresa o ser específicos
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`id_cliente`) REFERENCES `clientes`(`id_cliente`),
  FOREIGN KEY (`id_usuario_creador`) REFERENCES `usuarios`(`id_usuario`),
  FOREIGN KEY (`id_empresa`) REFERENCES `empresas`(`id_empresa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizacion_detalles`
--
CREATE TABLE IF NOT EXISTS `cotizacion_detalles` (
  `id_detalle_cotizacion` INT AUTO_INCREMENT PRIMARY KEY,
  `id_cotizacion` INT NOT NULL,
  `id_producto` INT NOT NULL,
  `descripcion_producto_cot` VARCHAR(255) DEFAULT NULL, -- Puede ser el nombre del producto o una descripción personalizada para esta línea
  `codigo_producto_cot` VARCHAR(50) DEFAULT NULL, -- Código del producto al momento de cotizar
  `unidad_medida_cot` VARCHAR(50) DEFAULT 'Unidad',
  `cantidad` DECIMAL(10,2) NOT NULL,
  `precio_unitario_base` DECIMAL(10,2) NOT NULL, -- Precio del producto antes de descuentos de línea. Si el producto ya tiene IGV, este precio lo incluye.
  `incluye_igv_producto` BOOLEAN DEFAULT TRUE, -- Indica si el precio_unitario_base del producto ya incluye IGV (heredado de producto.incluye_igv_en_precio_base)
  `descuento_linea_tipo` ENUM('NINGUNO','PORCENTAJE', 'MONTO_FIJO_UNITARIO', 'MONTO_FIJO_TOTAL') DEFAULT 'NINGUNO', -- Descuento específico para esta línea
  `descuento_linea_valor` DECIMAL(10,2) DEFAULT 0.00,
  `monto_descuento_linea` DECIMAL(10,2) DEFAULT 0.00, -- Monto total de descuento para esta línea (cantidad * descuento_monto_fijo_unitario O (precio_unitario_base * cantidad) * porcentaje)
  `precio_unitario_final_linea` DECIMAL(10,2) NOT NULL, -- Precio unitario después del descuento de línea
  `subtotal_linea` DECIMAL(12,2) NOT NULL, -- (cantidad * precio_unitario_final_linea)
  `notas_linea` TEXT DEFAULT NULL,
  FOREIGN KEY (`id_cotizacion`) REFERENCES `cotizaciones`(`id_cotizacion`) ON DELETE CASCADE,
  FOREIGN KEY (`id_producto`) REFERENCES `productos`(`id_producto`) -- ON DELETE RESTRICT o SET NULL si se quiere mantener el detalle aunque se borre el producto maestro
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuraciones`
--
CREATE TABLE IF NOT EXISTS `configuraciones` (
  `id_config` INT AUTO_INCREMENT PRIMARY KEY,
  `clave_config` VARCHAR(100) NOT NULL UNIQUE,
  `valor_config` TEXT DEFAULT NULL,
  `descripcion_config` VARCHAR(255) DEFAULT NULL,
  `tipo_dato` ENUM('TEXTO', 'NUMERO', 'BOOLEANO', 'JSON', 'ENCRIPTADO', 'TEXTAREA') DEFAULT 'TEXTO',
  `grupo_config` VARCHAR(50) DEFAULT 'General', -- Para agrupar en el panel de config: General, API, Email, etc.
  `editable_panel` BOOLEAN DEFAULT TRUE, -- Si se puede editar desde el panel de administración
  `fecha_modificacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Valores por defecto para `configuraciones` (ejemplos)
--
INSERT INTO `configuraciones` (`clave_config`, `valor_config`, `descripcion_config`, `tipo_dato`, `grupo_config`, `editable_panel`) VALUES
('APP_NAME', 'Sistema de Cotizaciones', 'Nombre de la Aplicación que aparece en títulos y correos', 'TEXTO', 'General', TRUE),
('DEFAULT_CURRENCY_SYMBOL', 'S/', 'Símbolo de la moneda por defecto (ej: S/, $)', 'TEXTO', 'General', TRUE),
('DEFAULT_CURRENCY_CODE', 'PEN', 'Código de moneda por defecto (ej: PEN, USD)', 'TEXTO', 'General', TRUE),
('DEFAULT_IGV_PERCENTAGE', '18.00', 'Porcentaje de IGV por defecto (ej: 18.00)', 'NUMERO', 'Impuestos', TRUE),
('TERMS_AND_CONDITIONS_COTIZACION', '1. Precios expresados en Nuevos Soles.\n2. Validez de la oferta: 15 días.\n3. Tiempo de entrega: a coordinar.', 'Términos y condiciones por defecto para las cotizaciones', 'TEXTAREA', 'Cotizaciones', TRUE),
('SUNAT_API_RUC_ENDPOINT', '', 'Endpoint de la API de SUNAT para consulta RUC', 'TEXTO', 'API', TRUE),
('SUNAT_API_RUC_TOKEN', '', 'Token para la API de SUNAT RUC (si es necesario)', 'ENCRIPTADO', 'API', TRUE),
('RENIEC_API_DNI_ENDPOINT', '', 'Endpoint de la API de RENIEC para consulta DNI', 'TEXTO', 'API', TRUE),
('RENIEC_API_DNI_TOKEN', '', 'Token para la API de RENIEC DNI (si es necesario)', 'ENCRIPTADO', 'API', TRUE),
('EMAIL_MAILER_METHOD', 'smtp', 'Método de envío de correo (smtp, mail, sendmail)', 'TEXTO', 'Email', TRUE),
('EMAIL_SMTP_HOST', 'smtp.example.com', 'Servidor SMTP para envío de correos', 'TEXTO', 'Email', TRUE),
('EMAIL_SMTP_PORT', '587', 'Puerto SMTP (ej: 587, 465, 25)', 'NUMERO', 'Email', TRUE),
('EMAIL_SMTP_USER', 'noreply@example.com', 'Usuario SMTP', 'TEXTO', 'Email', TRUE),
('EMAIL_SMTP_PASS', '', 'Contraseña SMTP', 'ENCRIPTADO', 'Email', TRUE),
('EMAIL_SMTP_SECURE', 'tls', 'Seguridad SMTP (tls, ssl, o vacío)', 'TEXTO', 'Email', TRUE),
('EMAIL_FROM_ADDRESS', 'noreply@example.com', 'Dirección de correo remitente para cotizaciones', 'TEXTO', 'Email', TRUE),
('EMAIL_FROM_NAME', 'Sistema de Cotizaciones', 'Nombre del remitente para correos de cotizaciones', 'TEXTO', 'Email', TRUE),
('LOGO_EMPRESA_PATH', 'public/img/logo_default.png', 'Ruta al logo de la empresa principal', 'TEXTO', 'General', FALSE), -- Se gestiona desde el panel de empresa
('COTIZACION_CODE_PREFIX', 'COT-', 'Prefijo para los códigos de cotización', 'TEXTO', 'Cotizaciones', TRUE),
('COTIZACION_NEXT_NUMBER', '1', 'Siguiente número correlativo para cotizaciones', 'NUMERO', 'Cotizaciones', FALSE);


--
-- Índices adicionales para mejorar rendimiento
--
ALTER TABLE `usuarios` ADD INDEX `idx_usuario_email` (`email`);
ALTER TABLE `clientes` ADD INDEX `idx_cliente_numero_documento_val` (`numero_documento`); -- Ya está en unique key, pero explícito
ALTER TABLE `productos` ADD INDEX `idx_producto_nombre` (`nombre_producto`);
ALTER TABLE `cotizaciones` ADD INDEX `idx_cotizacion_fecha_emision` (`fecha_emision`);
ALTER TABLE `cotizaciones` ADD INDEX `idx_cotizacion_cliente` (`id_cliente`);
ALTER TABLE `cotizaciones` ADD INDEX `idx_cotizacion_estado` (`estado`);

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
