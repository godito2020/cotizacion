-- Agregar campos de condición de pago a quotations
ALTER TABLE quotations
ADD COLUMN payment_condition VARCHAR(20) DEFAULT 'cash' AFTER currency,
ADD COLUMN credit_days INT DEFAULT NULL AFTER payment_condition;

-- payment_condition: 'cash' (efectivo) o 'credit' (crédito)
-- credit_days: 30, 60, 90, 120 días (solo aplica si payment_condition = 'credit')
