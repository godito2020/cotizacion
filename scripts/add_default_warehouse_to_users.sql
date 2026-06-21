-- Asigna un almacén por defecto a cada usuario.
-- Guarda el numero_almacen de desc_almacen (almacén COBOL).
-- NULL = el usuario no tiene almacén asignado (verá todos por defecto).
-- Usado para preseleccionar el filtro de almacén en la búsqueda de productos
-- de cotizaciones (create.php / create_mobile.php) sin restringir otros almacenes.

ALTER TABLE `users`
    ADD COLUMN `default_warehouse` INT NULL DEFAULT NULL AFTER `address`;
