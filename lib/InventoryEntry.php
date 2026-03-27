<?php
/**
 * InventoryEntry Class
 * Gestiona las entradas/registros de inventario físico
 */

class InventoryEntry {
    private $db;
    private $dbCobol;

    public function __construct() {
        $this->db = getDBConnection();
        $this->dbCobol = getCobolConnection();
    }

    /**
     * Obtiene el nombre del mes actual en formato de columna
     */
    private function getCurrentMonthColumn(): string {
        $meses = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo',
            4 => 'abril', 5 => 'mayo', 6 => 'junio',
            7 => 'julio', 8 => 'agosto', 9 => 'septiembre',
            10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];
        return $meses[(int)date('n')];
    }

    /**
     * Obtiene el stock del sistema para un producto en un almacén
     */
    public function getSystemStock(string $productCode, int $warehouseNumber): float {
        try {
            $mesActual = $this->getCurrentMonthColumn();

            $sql = "SELECT COALESCE({$mesActual}, 0) AS stock_actual
                    FROM vista_almacenes_anual
                    WHERE codigo = :codigo AND almacen = :almacen";

            $stmt = $this->dbCobol->prepare($sql);
            $stmt->execute([
                ':codigo' => $productCode,
                ':almacen' => $warehouseNumber
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float) ($result['stock_actual'] ?? 0);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getSystemStock Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene información de un producto con stock
     */
    public function getProductWithStock(string $productCode, int $warehouseNumber): array|false {
        try {
            $mesActual = $this->getCurrentMonthColumn();

            $sql = "SELECT
                        codigo AS codigo,
                        descripcion AS descripcion,
                        almacen AS warehouse_number,
                        {$mesActual} AS stock_actual
                    FROM vista_almacenes_anual
                    WHERE codigo = :codigo AND almacen = :almacen";

            $stmt = $this->dbCobol->prepare($sql);
            $stmt->execute([
                ':codigo' => $productCode,
                ':almacen' => $warehouseNumber
            ]);

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getProductWithStock Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca productos en un almacén
     * @param int $withStock 1 = solo productos con stock > 0, 0 = todos
     * Búsqueda flexible: cada palabra debe aparecer en codigo O descripcion
     */
    public function searchProducts(string $query, int $warehouseNumber, int $limit = 50, int $withStock = 1): array {
        try {
            $mesActual = $this->getCurrentMonthColumn();

            // Dividir query en palabras (mínimo 2 caracteres por palabra)
            $words = array_values(array_filter(preg_split('/\s+/', trim($query)), function($word) {
                return strlen($word) >= 2;
            }));

            if (empty($words)) {
                return [];
            }

            // Construir condiciones: cada palabra debe estar en codigo O descripcion
            $conditions = [];
            $params = [':almacen' => $warehouseNumber];

            $paramIndex = 0;
            foreach ($words as $word) {
                $paramName = ":word{$paramIndex}";
                $conditions[] = "(codigo LIKE {$paramName} OR descripcion LIKE {$paramName})";
                $params[$paramName] = '%' . $word . '%';
                $paramIndex++;
            }

            $searchCondition = implode(' AND ', $conditions);

            // Filtro opcional por stock > 0
            $stockFilter = $withStock ? "AND {$mesActual} > 0" : "";

            $sql = "SELECT
                        codigo,
                        descripcion,
                        almacen AS warehouse_number,
                        {$mesActual} AS stock_actual
                    FROM vista_almacenes_anual
                    WHERE almacen = :almacen
                    AND {$searchCondition}
                    {$stockFilter}
                    ORDER BY descripcion ASC
                    LIMIT {$limit}";

            // Debug logging
            error_log("InventoryEntry::searchProducts SQL: " . $sql);
            error_log("InventoryEntry::searchProducts Params: " . json_encode($params));

            $stmt = $this->dbCobol->prepare($sql);
            $stmt->execute($params);

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Normalizar claves a minúsculas
            $normalized = [];
            foreach ($results as $row) {
                $normalizedRow = [];
                foreach ($row as $key => $value) {
                    $normalizedRow[strtolower($key)] = $value;
                }
                $normalized[] = $normalizedRow;
            }

            return $normalized;

        } catch (PDOException $e) {
            error_log("InventoryEntry::searchProducts Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Crea una nueva entrada de inventario
     */
    public function create(
        int $sessionId,
        int $userId,
        int $warehouseNumber,
        string $productCode,
        float $countedQuantity,
        ?string $comments = null,
        ?int $zoneId = null
    ): int|false {
        try {
            // Obtener información del producto y stock del sistema
            $product = $this->getProductWithStock($productCode, $warehouseNumber);
            $systemStock = $product ? (float)$product['stock_actual'] : 0;
            $productDescription = $product ? $product['descripcion'] : null;

            $this->db->beginTransaction();

            $sql = "INSERT INTO inventory_entries
                    (session_id, user_id, warehouse_number, zone_id, product_code, product_description,
                     system_stock, counted_quantity, comments)
                    VALUES
                    (:session_id, :user_id, :warehouse_number, :zone_id, :product_code, :product_description,
                     :system_stock, :counted_quantity, :comments)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId,
                ':warehouse_number' => $warehouseNumber,
                ':zone_id' => $zoneId,
                ':product_code' => $productCode,
                ':product_description' => $productDescription,
                ':system_stock' => $systemStock,
                ':counted_quantity' => $countedQuantity,
                ':comments' => $comments
            ]);

            $entryId = (int) $this->db->lastInsertId();

            // Registrar en historial
            $this->logHistory($entryId, $userId, 'created', null, $countedQuantity, null, $comments);

            $this->db->commit();
            return $entryId;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("InventoryEntry::create Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza una entrada existente
     */
    public function update(int $entryId, int $userId, float $countedQuantity, ?string $comments = null): bool {
        try {
            // Obtener datos anteriores
            $entry = $this->getById($entryId);
            if (!$entry) {
                return false;
            }

            $this->db->beginTransaction();

            $sql = "UPDATE inventory_entries
                    SET counted_quantity = :counted_quantity,
                        comments = :comments,
                        is_edited = TRUE
                    WHERE id = :entry_id";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':entry_id' => $entryId,
                ':counted_quantity' => $countedQuantity,
                ':comments' => $comments
            ]);

            if ($result) {
                // Registrar en historial
                $this->logHistory(
                    $entryId,
                    $userId,
                    'updated',
                    $entry['counted_quantity'],
                    $countedQuantity,
                    $entry['comments'],
                    $comments
                );
            }

            $this->db->commit();
            return $result;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("InventoryEntry::update Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina una entrada (soft delete via historial)
     */
    public function delete(int $entryId, int $userId): bool {
        try {
            $entry = $this->getById($entryId);
            if (!$entry) {
                return false;
            }

            $this->db->beginTransaction();

            // Registrar en historial antes de eliminar
            $this->logHistory(
                $entryId,
                $userId,
                'deleted',
                $entry['counted_quantity'],
                null,
                $entry['comments'],
                null
            );

            $sql = "DELETE FROM inventory_entries WHERE id = :entry_id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([':entry_id' => $entryId]);

            $this->db->commit();
            return $result;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("InventoryEntry::delete Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registra en el historial de cambios
     */
    private function logHistory(
        int $entryId,
        int $userId,
        string $action,
        ?float $oldQuantity,
        ?float $newQuantity,
        ?string $oldComments,
        ?string $newComments
    ): bool {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;

            $sql = "INSERT INTO inventory_entry_history
                    (entry_id, user_id, action, old_counted_quantity, new_counted_quantity,
                     old_comments, new_comments, ip_address)
                    VALUES
                    (:entry_id, :user_id, :action, :old_qty, :new_qty, :old_comments, :new_comments, :ip)";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':entry_id' => $entryId,
                ':user_id' => $userId,
                ':action' => $action,
                ':old_qty' => $oldQuantity,
                ':new_qty' => $newQuantity,
                ':old_comments' => $oldComments,
                ':new_comments' => $newComments,
                ':ip' => $ip
            ]);
        } catch (PDOException $e) {
            error_log("InventoryEntry::logHistory Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene una entrada por ID
     */
    public function getById(int $entryId): array|false {
        try {
            $sql = "SELECT e.*, u.username, u.first_name, u.last_name,
                           z.name as zone_name, z.color as zone_color
                    FROM inventory_entries e
                    JOIN users u ON e.user_id = u.id
                    LEFT JOIN inventory_zones z ON e.zone_id = z.id
                    WHERE e.id = :entry_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':entry_id' => $entryId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getById Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene entradas por sesión
     */
    public function getBySession(int $sessionId, int $page = 1, int $perPage = 50): array {
        try {
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT e.*, u.username, u.first_name, u.last_name,
                           z.name as zone_name, z.color as zone_color
                    FROM inventory_entries e
                    JOIN users u ON e.user_id = u.id
                    LEFT JOIN inventory_zones z ON e.zone_id = z.id
                    WHERE e.session_id = :session_id
                    ORDER BY e.created_at DESC
                    LIMIT {$perPage} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getBySession Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene entradas de un usuario en una sesión
     */
    public function getByUser(int $sessionId, int $userId, int $page = 1, int $perPage = 50): array {
        try {
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT e.*, z.name as zone_name, z.color as zone_color
                    FROM inventory_entries e
                    LEFT JOIN inventory_zones z ON e.zone_id = z.id
                    WHERE e.session_id = :session_id AND e.user_id = :user_id
                    ORDER BY e.created_at DESC
                    LIMIT {$perPage} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getByUser Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta entradas de un usuario en una sesión
     */
    public function countByUser(int $sessionId, int $userId): int {
        try {
            $sql = "SELECT COUNT(*) FROM inventory_entries
                    WHERE session_id = :session_id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId
            ]);
            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("InventoryEntry::countByUser Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene entradas de un producto en una sesión
     */
    public function getByProduct(int $sessionId, string $productCode): array {
        try {
            $sql = "SELECT e.*, u.username, u.first_name, u.last_name,
                           z.name as zone_name, z.color as zone_color
                    FROM inventory_entries e
                    JOIN users u ON e.user_id = u.id
                    LEFT JOIN inventory_zones z ON e.zone_id = z.id
                    WHERE e.session_id = :session_id AND e.product_code = :product_code
                    ORDER BY e.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':product_code' => $productCode
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getByProduct Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el último registro de un usuario para un producto
     */
    public function getLastUserEntry(int $sessionId, int $userId, string $productCode): array|false {
        try {
            $sql = "SELECT e.*
                    FROM inventory_entries e
                    WHERE e.session_id = :session_id
                    AND e.user_id = :user_id
                    AND e.product_code = :product_code
                    ORDER BY e.created_at DESC
                    LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId,
                ':product_code' => $productCode
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getLastUserEntry Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el resumen de conteos de un usuario para un producto (suma total de todas las entradas)
     */
    public function getUserProductSummary(int $sessionId, int $userId, string $productCode): array|false {
        try {
            $sql = "SELECT
                        COUNT(*) as entry_count,
                        SUM(counted_quantity) as total_counted,
                        MAX(created_at) as last_entry_at,
                        MIN(created_at) as first_entry_at
                    FROM inventory_entries
                    WHERE session_id = :session_id
                    AND user_id = :user_id
                    AND product_code = :product_code";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId,
                ':product_code' => $productCode
            ]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result || $result['entry_count'] == 0) {
                return false;
            }

            return [
                'entry_count' => (int)$result['entry_count'],
                'total_counted' => (float)$result['total_counted'],
                'last_entry_at' => $result['last_entry_at'],
                'first_entry_at' => $result['first_entry_at']
            ];

        } catch (PDOException $e) {
            error_log("InventoryEntry::getUserProductSummary Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene todas las entradas de un usuario para un producto específico
     */
    public function getUserProductEntries(int $sessionId, int $userId, string $productCode): array {
        try {
            $sql = "SELECT id, counted_quantity, comments, created_at
                    FROM inventory_entries
                    WHERE session_id = :session_id
                    AND user_id = :user_id
                    AND product_code = :product_code
                    ORDER BY created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId,
                ':product_code' => $productCode
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getUserProductEntries Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene entradas por almacén
     */
    public function getByWarehouse(int $sessionId, int $warehouseNumber, int $page = 1, int $perPage = 50): array {
        try {
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT e.*, u.username, u.first_name, u.last_name,
                           z.name as zone_name, z.color as zone_color
                    FROM inventory_entries e
                    JOIN users u ON e.user_id = u.id
                    LEFT JOIN inventory_zones z ON e.zone_id = z.id
                    WHERE e.session_id = :session_id AND e.warehouse_number = :warehouse_number
                    ORDER BY e.created_at DESC
                    LIMIT {$perPage} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':warehouse_number' => $warehouseNumber
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getByWarehouse Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene discrepancias (faltantes y sobrantes)
     */
    public function getDiscrepancies(int $sessionId, string $type = 'all', int $page = 1, int $perPage = 50): array {
        try {
            $offset = ($page - 1) * $perPage;

            $whereType = match($type) {
                'faltantes' => 'AND e.difference < 0',
                'sobrantes' => 'AND e.difference > 0',
                default => 'AND e.difference != 0'
            };

            $sql = "SELECT e.*, u.username, u.first_name, u.last_name,
                           z.name as zone_name, z.color as zone_color
                    FROM inventory_entries e
                    JOIN users u ON e.user_id = u.id
                    LEFT JOIN inventory_zones z ON e.zone_id = z.id
                    WHERE e.session_id = :session_id {$whereType}
                    ORDER BY ABS(e.difference) DESC
                    LIMIT {$perPage} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getDiscrepancies Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta discrepancias
     */
    public function countDiscrepancies(int $sessionId, string $type = 'all'): int {
        try {
            $whereType = match($type) {
                'faltantes' => 'AND difference < 0',
                'sobrantes' => 'AND difference > 0',
                default => 'AND difference != 0'
            };

            $sql = "SELECT COUNT(*) FROM inventory_entries
                    WHERE session_id = :session_id {$whereType}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("InventoryEntry::countDiscrepancies Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene productos que coinciden con el stock
     */
    public function getMatching(int $sessionId, int $page = 1, int $perPage = 50): array {
        try {
            $offset = ($page - 1) * $perPage;

            $sql = "SELECT e.*, u.username, u.first_name, u.last_name,
                           z.name as zone_name, z.color as zone_color
                    FROM inventory_entries e
                    JOIN users u ON e.user_id = u.id
                    LEFT JOIN inventory_zones z ON e.zone_id = z.id
                    WHERE e.session_id = :session_id AND e.difference = 0
                    ORDER BY e.created_at DESC
                    LIMIT {$perPage} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getMatching Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta productos coincidentes
     */
    public function countMatching(int $sessionId): int {
        try {
            $sql = "SELECT COUNT(*) FROM inventory_entries
                    WHERE session_id = :session_id AND difference = 0";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return (int) $stmt->fetchColumn();

        } catch (PDOException $e) {
            error_log("InventoryEntry::countMatching Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene el historial de ediciones de una entrada
     */
    public function getEntryHistory(int $entryId): array {
        try {
            $sql = "SELECT h.*, u.username, u.first_name, u.last_name
                    FROM inventory_entry_history h
                    JOIN users u ON h.user_id = u.id
                    WHERE h.entry_id = :entry_id
                    ORDER BY h.created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':entry_id' => $entryId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getEntryHistory Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene entradas recientes de una sesión
     */
    public function getRecentEntries(int $sessionId, int $limit = 20): array {
        try {
            $sql = "SELECT e.*, u.username, u.first_name, u.last_name,
                           z.name as zone_name, z.color as zone_color
                    FROM inventory_entries e
                    JOIN users u ON e.user_id = u.id
                    LEFT JOIN inventory_zones z ON e.zone_id = z.id
                    WHERE e.session_id = :session_id
                    ORDER BY e.created_at DESC
                    LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryEntry::getRecentEntries Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene estadísticas del usuario en una sesión
     */
    public function getUserStats(int $sessionId, int $userId): array {
        try {
            $sql = "SELECT
                        COUNT(*) AS total_entries,
                        COUNT(DISTINCT product_code) AS unique_products,
                        SUM(CASE WHEN difference = 0 THEN 1 ELSE 0 END) AS matching_count,
                        SUM(CASE WHEN difference < 0 THEN 1 ELSE 0 END) AS faltantes_count,
                        SUM(CASE WHEN difference > 0 THEN 1 ELSE 0 END) AS sobrantes_count,
                        MIN(created_at) AS first_entry_at,
                        MAX(created_at) AS last_entry_at
                    FROM inventory_entries
                    WHERE session_id = :session_id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (PDOException $e) {
            error_log("InventoryEntry::getUserStats Error: " . $e->getMessage());
            return [];
        }
    }
}
