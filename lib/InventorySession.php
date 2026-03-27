<?php
/**
 * InventorySession Class
 * Gestiona las sesiones de inventario físico
 */

class InventorySession {
    private $db;
    private $dbCobol;

    public function __construct() {
        $this->db = getDBConnection();
        $this->dbCobol = getCobolConnection();
    }

    /**
     * Crea una nueva sesión de inventario
     */
    public function create(int $companyId, int $createdBy, string $name, ?string $description, array $warehouseNumbers): int|false {
        try {
            $this->db->beginTransaction();

            // Verificar que no haya otra sesión abierta
            if ($this->getActiveSession($companyId)) {
                throw new Exception('Ya existe una sesión de inventario abierta para esta empresa');
            }

            // Crear la sesión
            $sql = "INSERT INTO inventory_sessions (company_id, name, description, status, created_by, opened_at)
                    VALUES (:company_id, :name, :description, 'Open', :created_by, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':company_id' => $companyId,
                ':name' => $name,
                ':description' => $description,
                ':created_by' => $createdBy
            ]);

            $sessionId = (int) $this->db->lastInsertId();

            // Agregar almacenes a la sesión
            $stock = new Stock();
            foreach ($warehouseNumbers as $warehouseNumber) {
                $warehouse = $stock->getWarehouseByNumber($warehouseNumber);
                $warehouseName = $warehouse ? $warehouse['nombre'] : 'Almacén ' . $warehouseNumber;

                $this->addWarehouse($sessionId, $warehouseNumber, $warehouseName);
            }

            $this->db->commit();
            return $sessionId;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("InventorySession::create Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Agrega un almacén a una sesión
     */
    public function addWarehouse(int $sessionId, int $warehouseNumber, string $warehouseName): bool {
        try {
            $sql = "INSERT INTO inventory_session_warehouses (session_id, warehouse_number, warehouse_name)
                    VALUES (:session_id, :warehouse_number, :warehouse_name)
                    ON DUPLICATE KEY UPDATE warehouse_name = VALUES(warehouse_name)";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':session_id' => $sessionId,
                ':warehouse_number' => $warehouseNumber,
                ':warehouse_name' => $warehouseName
            ]);
        } catch (PDOException $e) {
            error_log("InventorySession::addWarehouse Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cierra una sesión de inventario
     */
    public function close(int $sessionId, int $closedBy, ?string $notes = null): bool {
        try {
            $sql = "UPDATE inventory_sessions
                    SET status = 'Closed',
                        closed_at = NOW(),
                        closed_by = :closed_by,
                        close_notes = :notes
                    WHERE id = :session_id AND status = 'Open'";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':session_id' => $sessionId,
                ':closed_by' => $closedBy,
                ':notes' => $notes
            ]);
        } catch (PDOException $e) {
            error_log("InventorySession::close Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Reabre una sesión de inventario cerrada
     */
    public function reopen(int $sessionId, int $reopenedBy, string $reason): array {
        try {
            // Obtener la sesión
            $session = $this->getById($sessionId);
            if (!$session) {
                return ['success' => false, 'message' => 'Sesión no encontrada'];
            }

            // Verificar que esté cerrada
            if ($session['status'] !== 'Closed') {
                return ['success' => false, 'message' => 'Solo se pueden reactivar sesiones cerradas'];
            }

            // Verificar que no haya otra sesión abierta
            if ($this->getActiveSession($session['company_id'])) {
                return ['success' => false, 'message' => 'Ya existe otra sesión de inventario abierta. Ciérrela primero.'];
            }

            // Reabrir la sesión
            $sql = "UPDATE inventory_sessions
                    SET status = 'Open',
                        closed_at = NULL,
                        closed_by = NULL,
                        close_notes = NULL,
                        reopened_at = NOW(),
                        reopened_by = :reopened_by,
                        reopen_reason = :reason
                    WHERE id = :session_id AND status = 'Closed'";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':session_id' => $sessionId,
                ':reopened_by' => $reopenedBy,
                ':reason' => $reason
            ]);

            if ($result && $stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Sesión reactivada correctamente'];
            }

            return ['success' => false, 'message' => 'No se pudo reactivar la sesión'];

        } catch (PDOException $e) {
            error_log("InventorySession::reopen Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error al reactivar la sesión'];
        }
    }

    /**
     * Cancela una sesión de inventario
     */
    public function cancel(int $sessionId, int $userId, ?string $reason = null): bool {
        try {
            $sql = "UPDATE inventory_sessions
                    SET status = 'Cancelled',
                        closed_at = NOW(),
                        closed_by = :user_id,
                        close_notes = :reason
                    WHERE id = :session_id AND status = 'Open'";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId,
                ':reason' => $reason
            ]);
        } catch (PDOException $e) {
            error_log("InventorySession::cancel Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene una sesión por ID
     */
    public function getById(int $sessionId): array|false {
        try {
            $sql = "SELECT s.*,
                           creator.username AS created_by_username,
                           creator.first_name AS created_by_first_name,
                           creator.last_name AS created_by_last_name,
                           closer.username AS closed_by_username,
                           reopener.username AS reopened_by_username
                    FROM inventory_sessions s
                    LEFT JOIN users creator ON s.created_by = creator.id
                    LEFT JOIN users closer ON s.closed_by = closer.id
                    LEFT JOIN users reopener ON s.reopened_by = reopener.id
                    WHERE s.id = :session_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                $session['warehouses'] = $this->getSessionWarehouses($sessionId);
            }

            return $session;
        } catch (PDOException $e) {
            error_log("InventorySession::getById Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene la sesión activa de una empresa
     */
    public function getActiveSession(int $companyId): array|false {
        try {
            $sql = "SELECT s.*,
                           creator.username AS created_by_username
                    FROM inventory_sessions s
                    LEFT JOIN users creator ON s.created_by = creator.id
                    WHERE s.company_id = :company_id AND s.status = 'Open'
                    LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':company_id' => $companyId]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($session) {
                $session['warehouses'] = $this->getSessionWarehouses($session['id']);
            }

            return $session;
        } catch (PDOException $e) {
            error_log("InventorySession::getActiveSession Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene lista de sesiones de una empresa
     */
    public function getSessions(int $companyId, ?string $status = null, int $page = 1, int $perPage = 20): array {
        try {
            $offset = ($page - 1) * $perPage;
            $params = [':company_id' => $companyId];

            $whereStatus = '';
            if ($status) {
                $whereStatus = 'AND s.status = :status';
                $params[':status'] = $status;
            }

            $sql = "SELECT s.*,
                           creator.username AS created_by_username,
                           closer.username AS closed_by_username,
                           reopener.username AS reopened_by_username,
                           (SELECT COUNT(*) FROM inventory_entries e WHERE e.session_id = s.id) AS total_entries,
                           (SELECT COUNT(DISTINCT e.user_id) FROM inventory_entries e WHERE e.session_id = s.id) AS total_users
                    FROM inventory_sessions s
                    LEFT JOIN users creator ON s.created_by = creator.id
                    LEFT JOIN users closer ON s.closed_by = closer.id
                    LEFT JOIN users reopener ON s.reopened_by = reopener.id
                    WHERE s.company_id = :company_id {$whereStatus}
                    ORDER BY s.opened_at DESC
                    LIMIT {$perPage} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventorySession::getSessions Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta total de sesiones
     */
    public function countSessions(int $companyId, ?string $status = null): int {
        try {
            $params = [':company_id' => $companyId];
            $whereStatus = '';

            if ($status) {
                $whereStatus = 'AND status = :status';
                $params[':status'] = $status;
            }

            $sql = "SELECT COUNT(*) FROM inventory_sessions
                    WHERE company_id = :company_id {$whereStatus}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("InventorySession::countSessions Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene los almacenes de una sesión
     */
    public function getSessionWarehouses(int $sessionId): array {
        try {
            $sql = "SELECT warehouse_number, warehouse_name
                    FROM inventory_session_warehouses
                    WHERE session_id = :session_id
                    ORDER BY warehouse_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("InventorySession::getSessionWarehouses Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica si una sesión está abierta
     */
    public function isOpen(int $sessionId): bool {
        try {
            $sql = "SELECT status FROM inventory_sessions WHERE id = :session_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            $status = $stmt->fetchColumn();
            return $status === 'Open';
        } catch (PDOException $e) {
            error_log("InventorySession::isOpen Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un usuario puede registrar en una sesión
     */
    public function canUserRegister(int $sessionId, int $userId, int $warehouseNumber): bool {
        // Primero verificar que la sesión esté abierta
        if (!$this->isOpen($sessionId)) {
            return false;
        }

        // Verificar que el almacén esté en la sesión
        $warehouses = $this->getSessionWarehouses($sessionId);
        $warehouseInSession = false;
        foreach ($warehouses as $wh) {
            if ((int)$wh['warehouse_number'] === $warehouseNumber) {
                $warehouseInSession = true;
                break;
            }
        }

        if (!$warehouseInSession) {
            return false;
        }

        // Verificar si hay restricciones por usuario
        try {
            $sql = "SELECT COUNT(*) FROM inventory_session_users
                    WHERE session_id = :session_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);

            // Si no hay usuarios asignados, cualquiera puede registrar
            if ($stmt->fetchColumn() == 0) {
                return true;
            }

            // Si hay usuarios asignados, verificar si el usuario está asignado
            $sql = "SELECT * FROM inventory_session_users
                    WHERE session_id = :session_id
                    AND user_id = :user_id
                    AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId
            ]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assignment) {
                return false;
            }

            // Si tiene almacén asignado, verificar que coincida
            if ($assignment['assigned_warehouse_number'] !== null) {
                return (int)$assignment['assigned_warehouse_number'] === $warehouseNumber;
            }

            return true;

        } catch (PDOException $e) {
            error_log("InventorySession::canUserRegister Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Asigna un usuario a una sesión
     */
    public function assignUser(int $sessionId, int $userId, int $assignedBy, ?int $warehouseNumber = null): bool {
        try {
            $sql = "INSERT INTO inventory_session_users
                    (session_id, user_id, assigned_warehouse_number, assigned_by, is_active)
                    VALUES (:session_id, :user_id, :warehouse_number, :assigned_by, 1)
                    ON DUPLICATE KEY UPDATE
                        assigned_warehouse_number = VALUES(assigned_warehouse_number),
                        is_active = 1";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId,
                ':warehouse_number' => $warehouseNumber,
                ':assigned_by' => $assignedBy
            ]);
        } catch (PDOException $e) {
            error_log("InventorySession::assignUser Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene usuarios asignados a una sesión
     */
    public function getAssignedUsers(int $sessionId): array {
        try {
            $sql = "SELECT su.*, u.username, u.first_name, u.last_name,
                           assigner.username AS assigned_by_username
                    FROM inventory_session_users su
                    JOIN users u ON su.user_id = u.id
                    LEFT JOIN users assigner ON su.assigned_by = assigner.id
                    WHERE su.session_id = :session_id AND su.is_active = 1
                    ORDER BY u.username ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("InventorySession::getAssignedUsers Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene estadísticas de una sesión
     */
    public function getSessionStats(int $sessionId): array {
        try {
            $sql = "SELECT
                        COUNT(*) AS total_entries,
                        COUNT(DISTINCT user_id) AS total_users,
                        COUNT(DISTINCT product_code) AS total_products,
                        COUNT(DISTINCT warehouse_number) AS total_warehouses,
                        SUM(CASE WHEN difference = 0 THEN 1 ELSE 0 END) AS matching_count,
                        SUM(CASE WHEN difference < 0 THEN 1 ELSE 0 END) AS faltantes_count,
                        SUM(CASE WHEN difference > 0 THEN 1 ELSE 0 END) AS sobrantes_count,
                        MIN(created_at) AS first_entry_at,
                        MAX(created_at) AS last_entry_at
                    FROM inventory_entries
                    WHERE session_id = :session_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (PDOException $e) {
            error_log("InventorySession::getSessionStats Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene estadísticas por almacén de una sesión
     */
    public function getStatsByWarehouse(int $sessionId): array {
        try {
            $sql = "SELECT
                        e.warehouse_number,
                        sw.warehouse_name,
                        COUNT(*) AS total_entries,
                        COUNT(DISTINCT e.user_id) AS total_users,
                        COUNT(DISTINCT e.product_code) AS total_products,
                        SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) AS matching_count,
                        SUM(CASE WHEN e.difference < 0 THEN 1 ELSE 0 END) AS faltantes_count,
                        SUM(CASE WHEN e.difference > 0 THEN 1 ELSE 0 END) AS sobrantes_count
                    FROM inventory_entries e
                    JOIN inventory_session_warehouses sw
                        ON e.session_id = sw.session_id AND e.warehouse_number = sw.warehouse_number
                    WHERE e.session_id = :session_id
                    GROUP BY e.warehouse_number, sw.warehouse_name
                    ORDER BY sw.warehouse_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventorySession::getStatsByWarehouse Error: " . $e->getMessage());
            return [];
        }
    }
}
