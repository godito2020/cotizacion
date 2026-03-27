-- Script para agregar campo company_status a la tabla customers
-- Este campo almacena el estado de la empresa del cliente (ACTIVO, BAJA, etc.)

ALTER TABLE customers ADD COLUMN company_status VARCHAR(50) NULL AFTER tax_id;
