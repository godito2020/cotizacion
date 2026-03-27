-- Corregir el campo id de quotation_approval_tokens para que sea AUTO_INCREMENT y PRIMARY KEY
ALTER TABLE quotation_approval_tokens ADD PRIMARY KEY (id);
ALTER TABLE quotation_approval_tokens MODIFY COLUMN id INT AUTO_INCREMENT;
