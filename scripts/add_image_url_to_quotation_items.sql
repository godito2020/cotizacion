-- Agregar campo image_url a quotation_items para guardar la imagen del producto en la cotización
ALTER TABLE quotation_items ADD COLUMN image_url VARCHAR(500) NULL AFTER description;
