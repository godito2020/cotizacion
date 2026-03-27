<?php
// cotizacion/lib/Stock.php
// Modelo de stock - Lee desde vista_almacenes_anual de BD COBOL

class Stock {
    private $dbCobol;  // Conexión a BD COBOL (vista_almacenes_anual)
    private $dbLocal;  // Conexión a BD local (desc_almacen)

    public function __construct() {
        $this->dbCobol = getCobolConnection();
        $this->dbLocal = getDBConnection();
    }

    /**
     * Obtiene el nombre del mes actual en formato de columna
     * Ejemplo: enero, febrero, marzo, etc.
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
     * Obtiene el mapeo de números de almacén a nombres desde BD local
     */
    private function getWarehouseNames(): array {
        try {
            $sql = "SELECT numero_almacen, nombre FROM desc_almacen WHERE activo = 1";
            $stmt = $this->dbLocal->query($sql);
            $result = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result[$row['numero_almacen']] = $row['nombre'];
            }
            return $result;
        } catch (PDOException $e) {
            error_log("Stock::getWarehouseNames Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el stock de un producto por almacén desde vista_almacenes_anual
     * Columnas esperadas: codigo, descripcion, almacen, enero, febrero, ..., diciembre
     *
     * @param string $codigo Código del producto
     * @return array Stock por almacén con nombre del almacén
     */
    public function getStockByProduct(string $codigo): array {
        try {
            $mesActual = $this->getCurrentMonthColumn();

            // Obtener datos de COBOL
            $sql = "SELECT
                        codigo,
                        descripcion,
                        almacen as numero_almacen,
                        {$mesActual} as stock_actual
                    FROM vista_almacenes_anual
                    WHERE codigo = :codigo
                    ORDER BY almacen ASC";

            $stmt = $this->dbCobol->prepare($sql);
            $stmt->bindValue(':codigo', $codigo);
            $stmt->execute();
            $stockData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener nombres de almacén desde BD local
            $warehouseNames = $this->getWarehouseNames();

            // Agregar nombres de almacén a los resultados
            foreach ($stockData as &$row) {
                $numAlmacen = $row['numero_almacen'];
                $row['nombre_almacen'] = $warehouseNames[$numAlmacen] ?? 'Almacén ' . $numAlmacen;
            }

            return $stockData;
        } catch (PDOException $e) {
            error_log("Stock::getStockByProduct Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el stock total de un producto (suma de todos los almacenes)
     */
    public function getTotalStock(string $codigo): float {
        try {
            $mesActual = $this->getCurrentMonthColumn();

            $sql = "SELECT COALESCE(SUM({$mesActual}), 0) as total
                    FROM vista_almacenes_anual
                    WHERE codigo = :codigo";

            $stmt = $this->dbCobol->prepare($sql);
            $stmt->bindValue(':codigo', $codigo);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return (float)($result['total'] ?? 0);
        } catch (PDOException $e) {
            error_log("Stock::getTotalStock Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene todos los almacenes con su stock para un producto
     */
    public function getProductStockDetails(string $codigo): array {
        return $this->getStockByProduct($codigo);
    }

    /**
     * Obtiene el stock de todos los productos en un almacén específico
     */
    public function getStockByWarehouse(int $numeroAlmacen): array {
        try {
            $mesActual = $this->getCurrentMonthColumn();

            $sql = "SELECT
                        codigo,
                        descripcion,
                        {$mesActual} as stock_actual
                    FROM vista_almacenes_anual
                    WHERE almacen = :almacen
                    AND {$mesActual} > 0
                    ORDER BY descripcion ASC";

            $stmt = $this->dbCobol->prepare($sql);
            $stmt->bindValue(':almacen', $numeroAlmacen, PDO::PARAM_INT);
            $stmt->execute();
            $stockData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener nombre del almacén
            $warehouseNames = $this->getWarehouseNames();
            $nombreAlmacen = $warehouseNames[$numeroAlmacen] ?? 'Almacén ' . $numeroAlmacen;

            // Agregar nombre a cada registro
            foreach ($stockData as &$row) {
                $row['numero_almacen'] = $numeroAlmacen;
                $row['nombre_almacen'] = $nombreAlmacen;
            }

            return $stockData;
        } catch (PDOException $e) {
            error_log("Stock::getStockByWarehouse Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene la lista de almacenes disponibles desde desc_almacen
     */
    public function getWarehouses(): array {
        try {
            $sql = "SELECT numero_almacen, nombre, direccion, telefono, activo
                    FROM desc_almacen
                    WHERE activo = 1
                    ORDER BY nombre ASC";

            $stmt = $this->dbLocal->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Stock::getWarehouses Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene información de un almacén por su número
     */
    public function getWarehouseByNumber(int $numeroAlmacen): array|false {
        try {
            $sql = "SELECT numero_almacen, nombre, direccion, telefono, activo
                    FROM desc_almacen
                    WHERE numero_almacen = :numero";

            $stmt = $this->dbLocal->prepare($sql);
            $stmt->bindValue(':numero', $numeroAlmacen, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Stock::getWarehouseByNumber Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Agrega o actualiza un almacén en desc_almacen
     */
    public function saveWarehouse(int $numeroAlmacen, string $nombre, ?string $direccion = null, ?string $telefono = null): bool {
        try {
            $sql = "INSERT INTO desc_almacen (numero_almacen, nombre, direccion, telefono)
                    VALUES (:numero, :nombre, :direccion, :telefono)
                    ON DUPLICATE KEY UPDATE
                        nombre = VALUES(nombre),
                        direccion = VALUES(direccion),
                        telefono = VALUES(telefono),
                        activo = 1";

            $stmt = $this->dbLocal->prepare($sql);
            $stmt->bindValue(':numero', $numeroAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':nombre', $nombre);
            $stmt->bindValue(':direccion', $direccion);
            $stmt->bindValue(':telefono', $telefono);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Stock::saveWarehouse Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Desactiva un almacén
     */
    public function deactivateWarehouse(int $numeroAlmacen): bool {
        try {
            $sql = "UPDATE desc_almacen SET activo = 0 WHERE numero_almacen = :numero";
            $stmt = $this->dbLocal->prepare($sql);
            $stmt->bindValue(':numero', $numeroAlmacen, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Stock::deactivateWarehouse Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene resumen de stock por almacén
     */
    public function getWarehouseStockSummary(): array {
        try {
            $mesActual = $this->getCurrentMonthColumn();

            // Obtener datos de COBOL
            $sql = "SELECT
                        almacen as numero_almacen,
                        COUNT(DISTINCT codigo) as total_productos,
                        SUM({$mesActual}) as stock_total
                    FROM vista_almacenes_anual
                    WHERE {$mesActual} > 0
                    GROUP BY almacen
                    ORDER BY almacen ASC";

            $stmt = $this->dbCobol->query($sql);
            $summaryData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener nombres de almacén desde BD local
            $warehouseNames = $this->getWarehouseNames();

            // Agregar nombres
            foreach ($summaryData as &$row) {
                $numAlmacen = $row['numero_almacen'];
                $row['nombre_almacen'] = $warehouseNames[$numAlmacen] ?? 'Almacén ' . $numAlmacen;
            }

            return $summaryData;
        } catch (PDOException $e) {
            error_log("Stock::getWarehouseStockSummary Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene productos con stock bajo en todos los almacenes
     */
    public function getLowStockProducts(int $threshold = 10, int $limit = 20): array {
        try {
            $mesActual = $this->getCurrentMonthColumn();

            $sql = "SELECT
                        codigo,
                        descripcion,
                        almacen as numero_almacen,
                        {$mesActual} as stock_actual
                    FROM vista_almacenes_anual
                    WHERE {$mesActual} > 0
                    AND {$mesActual} <= :threshold
                    ORDER BY {$mesActual} ASC
                    LIMIT :limit";

            $stmt = $this->dbCobol->prepare($sql);
            $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $stockData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener nombres de almacén
            $warehouseNames = $this->getWarehouseNames();

            foreach ($stockData as &$row) {
                $numAlmacen = $row['numero_almacen'];
                $row['nombre_almacen'] = $warehouseNames[$numAlmacen] ?? 'Almacén ' . $numAlmacen;
            }

            return $stockData;
        } catch (PDOException $e) {
            error_log("Stock::getLowStockProducts Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene el histórico de stock de un producto (todos los meses)
     */
    public function getStockHistory(string $codigo, int $numeroAlmacen): array {
        try {
            $sql = "SELECT
                        codigo,
                        descripcion,
                        almacen,
                        enero, febrero, marzo, abril, mayo, junio,
                        julio, agosto, septiembre, octubre, noviembre, diciembre
                    FROM vista_almacenes_anual
                    WHERE codigo = :codigo AND almacen = :almacen";

            $stmt = $this->dbCobol->prepare($sql);
            $stmt->bindValue(':codigo', $codigo);
            $stmt->bindValue(':almacen', $numeroAlmacen, PDO::PARAM_INT);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return [];

            // Formatear como histórico mensual
            $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                      'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

            $history = [];
            foreach ($meses as $index => $mes) {
                $history[] = [
                    'mes' => ucfirst($mes),
                    'numero_mes' => $index + 1,
                    'stock' => (float)($row[$mes] ?? 0)
                ];
            }

            return $history;
        } catch (PDOException $e) {
            error_log("Stock::getStockHistory Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca productos con stock por término de búsqueda
     */
    public function searchProductsWithStock(string $query, int $limit = 50): array {
        try {
            $mesActual = $this->getCurrentMonthColumn();

            $sql = "SELECT
                        codigo,
                        descripcion,
                        almacen as numero_almacen,
                        {$mesActual} as stock_actual
                    FROM vista_almacenes_anual
                    WHERE (LOWER(codigo) LIKE LOWER(:query)
                       OR LOWER(descripcion) LIKE LOWER(:query2))
                    AND {$mesActual} > 0
                    ORDER BY descripcion ASC
                    LIMIT :limit";

            $stmt = $this->dbCobol->prepare($sql);
            $searchTerm = '%' . $query . '%';
            $stmt->bindValue(':query', $searchTerm);
            $stmt->bindValue(':query2', $searchTerm);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $stockData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Obtener nombres de almacén
            $warehouseNames = $this->getWarehouseNames();

            foreach ($stockData as &$row) {
                $numAlmacen = $row['numero_almacen'];
                $row['nombre_almacen'] = $warehouseNames[$numAlmacen] ?? 'Almacén ' . $numAlmacen;
            }

            return $stockData;
        } catch (PDOException $e) {
            error_log("Stock::searchProductsWithStock Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene los nombres de almacén disponibles (de los que tienen stock)
     */
    public function getAvailableWarehouses(): array {
        try {
            $sql = "SELECT numero_almacen, nombre
                    FROM desc_almacen
                    WHERE activo = 1
                    ORDER BY nombre ASC";

            $stmt = $this->dbLocal->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Stock::getAvailableWarehouses Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene todos los almacenes únicos que existen en vista_almacenes_anual de COBOL
     * Útil para saber qué almacenes configurar en desc_almacen
     */
    public function getCobolWarehouses(): array {
        try {
            $mesActual = $this->getCurrentMonthColumn();

            $sql = "SELECT DISTINCT almacen as numero_almacen
                    FROM vista_almacenes_anual
                    WHERE {$mesActual} > 0
                    ORDER BY almacen ASC";

            $stmt = $this->dbCobol->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Stock::getCobolWarehouses Error: " . $e->getMessage());
            return [];
        }
    }
}
