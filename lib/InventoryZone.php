<?php
/**
 * InventoryZone Class
 * Gestiona las zonas de almacén para inventario
 */

class InventoryZone {
    private $db;
    private ?string $lastError = null;

    public function __construct() {
        $this->db = getDBConnection();
    }

    /**
     * Crea una nueva zona
     */
    public function create(int $companyId, int $warehouseNumber, string $name, ?string $description = null, ?string $color = null, ?int $createdBy = null): int|false {
        try {
            // Primero obtener el máximo sort_order actual
            $sortSql = "SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order
                        FROM inventory_zones
                        WHERE company_id = :company_id AND warehouse_number = :warehouse_number";
            $sortStmt = $this->db->prepare($sortSql);
            $sortStmt->execute([
                ':company_id' => $companyId,
                ':warehouse_number' => $warehouseNumber
            ]);
            $nextOrder = (int) $sortStmt->fetchColumn();

            // Luego insertar la nueva zona
            $sql = "INSERT INTO inventory_zones (company_id, warehouse_number, name, description, color, created_by, sort_order)
                    VALUES (:company_id, :warehouse_number, :name, :description, :color, :created_by, :sort_order)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':company_id' => $companyId,
                ':warehouse_number' => $warehouseNumber,
                ':name' => trim($name),
                ':description' => $description ? trim($description) : null,
                ':color' => $color ?? '#6c757d',
                ':created_by' => $createdBy,
                ':sort_order' => $nextOrder
            ]);

            return (int) $this->db->lastInsertId();

        } catch (PDOException $e) {
            error_log("InventoryZone::create Error: " . $e->getMessage());
            // Guardar el mensaje de error para debug
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Obtiene el último error
     */
    public function getLastError(): ?string {
        return $this->lastError ?? null;
    }

    /**
     * Actualiza una zona
     */
    public function update(int $zoneId, string $name, ?string $description = null, ?string $color = null): bool {
        try {
            $sql = "UPDATE inventory_zones
                    SET name = :name, description = :description, color = :color
                    WHERE id = :zone_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':zone_id' => $zoneId,
                ':name' => trim($name),
                ':description' => $description ? trim($description) : null,
                ':color' => $color ?? '#6c757d'
            ]);

        } catch (PDOException $e) {
            error_log("InventoryZone::update Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Activa/Desactiva una zona
     */
    public function toggleActive(int $zoneId, bool $isActive): bool {
        try {
            $sql = "UPDATE inventory_zones SET is_active = :is_active WHERE id = :zone_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':zone_id' => $zoneId,
                ':is_active' => $isActive ? 1 : 0
            ]);

        } catch (PDOException $e) {
            error_log("InventoryZone::toggleActive Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina una zona (solo si no tiene entradas asociadas)
     */
    public function delete(int $zoneId): bool {
        try {
            // Verificar si tiene entradas
            $checkSql = "SELECT COUNT(*) FROM inventory_entries WHERE zone_id = :zone_id";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([':zone_id' => $zoneId]);

            if ($checkStmt->fetchColumn() > 0) {
                return false; // No se puede eliminar, tiene entradas
            }

            $sql = "DELETE FROM inventory_zones WHERE id = :zone_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([':zone_id' => $zoneId]);

        } catch (PDOException $e) {
            error_log("InventoryZone::delete Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene una zona por ID
     */
    public function getById(int $zoneId): array|false {
        try {
            $sql = "SELECT z.*, u.username as created_by_username
                    FROM inventory_zones z
                    LEFT JOIN users u ON z.created_by = u.id
                    WHERE z.id = :zone_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':zone_id' => $zoneId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryZone::getById Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene zonas por almacén
     */
    public function getByWarehouse(int $companyId, int $warehouseNumber, bool $onlyActive = true): array {
        try {
            $activeFilter = $onlyActive ? "AND z.is_active = 1" : "";

            $sql = "SELECT z.*, u.username as created_by_username,
                           (SELECT COUNT(*) FROM inventory_entries e WHERE e.zone_id = z.id) as entry_count
                    FROM inventory_zones z
                    LEFT JOIN users u ON z.created_by = u.id
                    WHERE z.company_id = :company_id
                    AND z.warehouse_number = :warehouse_number
                    {$activeFilter}
                    ORDER BY z.sort_order ASC, z.name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':company_id' => $companyId,
                ':warehouse_number' => $warehouseNumber
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryZone::getByWarehouse Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene todas las zonas de una empresa
     */
    public function getByCompany(int $companyId, bool $onlyActive = true): array {
        try {
            $activeFilter = $onlyActive ? "AND z.is_active = 1" : "";

            $sql = "SELECT z.*, u.username as created_by_username
                    FROM inventory_zones z
                    LEFT JOIN users u ON z.created_by = u.id
                    WHERE z.company_id = :company_id
                    {$activeFilter}
                    ORDER BY z.warehouse_number ASC, z.sort_order ASC, z.name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':company_id' => $companyId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryZone::getByCompany Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Guarda las zonas seleccionadas por un usuario para una sesión
     */
    public function saveUserZones(int $sessionId, int $userId, array $zoneIds): bool {
        try {
            $this->db->beginTransaction();

            // Eliminar selecciones anteriores
            $deleteSql = "DELETE FROM inventory_session_user_zones
                          WHERE session_id = :session_id AND user_id = :user_id";
            $deleteStmt = $this->db->prepare($deleteSql);
            $deleteStmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId
            ]);

            // Insertar nuevas selecciones
            if (!empty($zoneIds)) {
                $insertSql = "INSERT INTO inventory_session_user_zones (session_id, user_id, zone_id)
                              VALUES (:session_id, :user_id, :zone_id)";
                $insertStmt = $this->db->prepare($insertSql);

                foreach ($zoneIds as $zoneId) {
                    $insertStmt->execute([
                        ':session_id' => $sessionId,
                        ':user_id' => $userId,
                        ':zone_id' => (int) $zoneId
                    ]);
                }
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("InventoryZone::saveUserZones Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene las zonas seleccionadas por un usuario para una sesión
     */
    public function getUserZones(int $sessionId, int $userId): array {
        try {
            $sql = "SELECT z.*
                    FROM inventory_zones z
                    JOIN inventory_session_user_zones suz ON z.id = suz.zone_id
                    WHERE suz.session_id = :session_id AND suz.user_id = :user_id
                    ORDER BY z.sort_order ASC, z.name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryZone::getUserZones Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica si un usuario tiene zonas seleccionadas
     */
    public function hasUserZones(int $sessionId, int $userId): bool {
        try {
            $sql = "SELECT COUNT(*) FROM inventory_session_user_zones
                    WHERE session_id = :session_id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId
            ]);
            return $stmt->fetchColumn() > 0;

        } catch (PDOException $e) {
            error_log("InventoryZone::hasUserZones Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reordena las zonas
     */
    public function reorder(array $zoneIds): bool {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE inventory_zones SET sort_order = :order WHERE id = :zone_id";
            $stmt = $this->db->prepare($sql);

            foreach ($zoneIds as $order => $zoneId) {
                $stmt->execute([
                    ':zone_id' => (int) $zoneId,
                    ':order' => $order + 1
                ]);
            }

            $this->db->commit();
            return true;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("InventoryZone::reorder Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene estadísticas de zonas para una sesión
     */
    public function getSessionZoneStats(int $sessionId): array {
        try {
            $sql = "SELECT
                        z.id,
                        z.name,
                        z.color,
                        z.warehouse_number,
                        COUNT(e.id) as total_entries,
                        COUNT(DISTINCT e.product_code) as unique_products,
                        SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) as matching_count,
                        SUM(CASE WHEN e.difference < 0 THEN 1 ELSE 0 END) as faltantes_count,
                        SUM(CASE WHEN e.difference > 0 THEN 1 ELSE 0 END) as sobrantes_count
                    FROM inventory_zones z
                    LEFT JOIN inventory_entries e ON z.id = e.zone_id AND e.session_id = :session_id
                    WHERE z.id IN (SELECT DISTINCT zone_id FROM inventory_entries WHERE session_id = :session_id2 AND zone_id IS NOT NULL)
                    GROUP BY z.id
                    ORDER BY z.warehouse_number, z.sort_order, z.name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':session_id2' => $sessionId
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryZone::getSessionZoneStats Error: " . $e->getMessage());
            return [];
        }
    }
}
