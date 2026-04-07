-- Fix: activity_logs.id necesita AUTO_INCREMENT
-- Sin esto, los INSERT fallan con: "Field 'id' doesn't have a default value"
-- Esto afecta el logging de actividades de WhatsApp y otras operaciones

ALTER TABLE `activity_logs` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
