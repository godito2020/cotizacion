-- Script para agregar campo igv_mode a la tabla quotations
-- Este campo almacena si el precio incluye IGV o si se debe agregar el 18%
-- Valores posibles: 'included' (precio incluye IGV) o 'plus_igv' (se agrega 18% al total)

ALTER TABLE quotations ADD COLUMN igv_mode VARCHAR(20) DEFAULT 'included' AFTER currency;

-- Actualizar cotizaciones existentes para que tengan el valor por defecto
UPDATE quotations SET igv_mode = 'included' WHERE igv_mode IS NULL;
