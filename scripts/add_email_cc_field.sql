-- Script para agregar campo email_cc a la tabla customers
-- Este campo permite almacenar correos adicionales para copia (CC) al enviar cotizaciones

ALTER TABLE customers ADD COLUMN email_cc VARCHAR(500) NULL AFTER email;

-- El campo email_cc puede contener múltiples correos separados por coma
-- Ejemplo: "correo1@ejemplo.com, correo2@ejemplo.com"
