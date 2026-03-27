-- Script para crear tabla de plantillas CotiRapi
-- Fecha: 2026-01-23

CREATE TABLE IF NOT EXISTS cotirapi_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    template_header TEXT,
    template_item TEXT NOT NULL,
    template_footer TEXT,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_company (company_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar plantillas por defecto para todas las empresas
INSERT INTO cotirapi_templates (company_id, name, template_header, template_item, template_footer, is_active, is_default)
SELECT
    c.id as company_id,
    'Plantilla Estándar' as name,
    '🏪 *COTIZACIÓN RÁPIDA*\n━━━━━━━━━━━━━━━━━━━━━\n\n👤 *Cliente:* {CUSTOMER_NAME}\n📅 *Fecha:* {DATE}\n\n━━━━━━━━━━━━━━━━━━━━━\n\n' as template_header,
    '📦 *{ITEM_NUMBER}. {DESCRIPTION}*\n{CODE_LINE}   📊 Cantidad: {QUANTITY}\n   💰 Precio: {CURRENCY} {UNIT_PRICE}\n{DISCOUNT_LINE}   💵 Total: {CURRENCY} {TOTAL}\n\n' as template_item,
    '━━━━━━━━━━━━━━━━━━━━━\n\n💵 *Subtotal:* {CURRENCY} {SUBTOTAL}\n📊 *IGV (18%):* {CURRENCY} {IGV}\n💰 *TOTAL:* {CURRENCY} {GRAND_TOTAL}\n\n━━━━━━━━━━━━━━━━━━━━━\n\n✅ _Precios incluyen IGV_\n📍 _Stock sujeto a disponibilidad_\n💬 _Consultas al WhatsApp_' as template_footer,
    1 as is_active,
    1 as is_default
FROM companies c
WHERE NOT EXISTS (
    SELECT 1 FROM cotirapi_templates WHERE company_id = c.id AND name = 'Plantilla Estándar'
);

-- Insertar plantilla simple alternativa
INSERT INTO cotirapi_templates (company_id, name, template_header, template_item, template_footer, is_active, is_default)
SELECT
    c.id as company_id,
    'Plantilla Simple' as name,
    'COTIZACIÓN\n\nCliente: {CUSTOMER_NAME}\nFecha: {DATE}\n\n' as template_header,
    '{ITEM_NUMBER}. {DESCRIPTION}\nCódigo: {CODE}\nCantidad: {QUANTITY} | Precio: {CURRENCY} {UNIT_PRICE}\nTotal: {CURRENCY} {TOTAL}\n\n' as template_item,
    '-------------------\nSubtotal: {CURRENCY} {SUBTOTAL}\nIGV: {CURRENCY} {IGV}\nTOTAL: {CURRENCY} {GRAND_TOTAL}\n\nPrecios incluyen IGV' as template_footer,
    1 as is_active,
    0 as is_default
FROM companies c
WHERE NOT EXISTS (
    SELECT 1 FROM cotirapi_templates WHERE company_id = c.id AND name = 'Plantilla Simple'
);

-- Insertar plantilla profesional
INSERT INTO cotirapi_templates (company_id, name, template_header, template_item, template_footer, is_active, is_default)
SELECT
    c.id as company_id,
    'Plantilla Profesional' as name,
    '═══════════════════════\n  COTIZACIÓN COMERCIAL\n═══════════════════════\n\n▸ Cliente: {CUSTOMER_NAME}\n▸ Fecha: {DATE}\n\n' as template_header,
    '┌ ITEM {ITEM_NUMBER}\n├─ {DESCRIPTION}\n{CODE_LINE}├─ Cantidad: {QUANTITY} unidades\n├─ Precio unitario: {CURRENCY} {UNIT_PRICE}\n{DISCOUNT_LINE}└─ Subtotal: {CURRENCY} {TOTAL}\n\n' as template_item,
    '═══════════════════════\n  RESUMEN FINANCIERO\n═══════════════════════\n\nSubtotal......: {CURRENCY} {SUBTOTAL}\nIGV (18%).....: {CURRENCY} {IGV}\n───────────────────────\nTOTAL.........: {CURRENCY} {GRAND_TOTAL}\n\n⚠ Condiciones:\n• Precios incluyen IGV\n• Sujeto a stock disponible\n• Válido por 7 días' as template_footer,
    1 as is_active,
    0 as is_default
FROM companies c
WHERE NOT EXISTS (
    SELECT 1 FROM cotirapi_templates WHERE company_id = c.id AND name = 'Plantilla Profesional'
);

-- Confirmar
SELECT 'Tabla cotirapi_templates creada exitosamente' as mensaje;
SELECT COUNT(*) as total_plantillas FROM cotirapi_templates;
