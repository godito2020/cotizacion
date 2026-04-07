-- ============================================
-- Script: Agregar campo de dirección a tabla users
-- Fecha: 2026-04-06
-- Descripción: Permite a cada usuario/vendedor tener su propia
--              dirección de tienda para mostrar en PDFs de cotización
-- ============================================

-- Agregar columna address a la tabla users
ALTER TABLE `users`
ADD COLUMN `address` VARCHAR(255) NULL DEFAULT NULL
COMMENT 'Dirección personalizada del vendedor/tienda. Si es NULL, se usa la dirección de la empresa'
AFTER `signature_url`;

-- Verificar la estructura actualizada
DESCRIBE users;
