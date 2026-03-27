-- Corregir el campo id de quotations para que sea AUTO_INCREMENT y PRIMARY KEY
-- Primero agregar PRIMARY KEY si no existe
ALTER TABLE quotations ADD PRIMARY KEY (id);

-- Luego modificar para que sea AUTO_INCREMENT
ALTER TABLE quotations MODIFY COLUMN id INT AUTO_INCREMENT;
