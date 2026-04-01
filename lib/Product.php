<?php
// cotizacion/lib/Product.php
// Modelo de productos - Lee desde vista_productos de BD COBOL

class Product {
    private $dbCobol;  // Conexión a BD COBOL (vista_productos)
    private $dbLocal;  // Conexión a BD local (imagenes, cotizaciones)

    public function __construct() {
        $this->dbCobol = getCobolConnection();
        $this->dbLocal = getDBConnection();
    }

    /**
     * Obtiene todos los productos desde vista_productos de COBOL
     * Columnas esperadas: codigo, descripcion, saldo, precio
     */
    public function getAll(): array {
        try {
            $sql = "SELECT
                        codigo,
                        descripcion,
                        saldo,
                        precio
                    FROM vista_productos
                    ORDER BY descripcion ASC";
            $stmt = $this->dbCobol->query($sql);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Agregar imágenes de BD local en una sola consulta (evita N+1)
            $this->attachImages($products);

            return $products;
        } catch (PDOException $e) {
            error_log("Product::getAll Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Carga imágenes para un array de productos en una sola consulta (evita N+1).
     */
    private function attachImages(array &$products): void {
        if (empty($products)) return;

        $codes = array_column($products, 'codigo');
        $placeholders = implode(',', array_fill(0, count($codes), '?'));

        try {
            $sql = "SELECT codigo_producto, imagen_url FROM imagenes
                    WHERE codigo_producto IN ($placeholders) AND imagen_principal = 1";
            $stmt = $this->dbLocal->prepare($sql);
            $stmt->execute($codes);
            $images = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [codigo => imagen_url]

            foreach ($products as &$product) {
                $product['imagen_url'] = $images[$product['codigo']] ?? null;
            }
        } catch (PDOException $e) {
            error_log("Product::attachImages Error: " . $e->getMessage());
            foreach ($products as &$product) {
                $product['imagen_url'] = null;
            }
        }
    }

    /**
     * Busca productos por código o descripción
     * Permite búsqueda por múltiples palabras: "11r22.5 giti" busca productos que contengan AMBAS palabras
     */
    public function search(string $query, int $limit = 50): array {
        try {
            // Separar query en palabras y limpiar
            $words = preg_split('/\s+/', trim($query));
            $words = array_filter($words, fn($w) => strlen($w) >= 2);

            if (empty($words)) {
                return [];
            }

            // Construir condiciones para cada palabra (deben estar TODAS)
            $conditions = [];
            $params = [];
            $i = 0;

            foreach ($words as $word) {
                $paramName = ":word{$i}";
                $conditions[] = "(LOWER(codigo) LIKE LOWER({$paramName}) OR LOWER(descripcion) LIKE LOWER({$paramName}))";
                $params[$paramName] = '%' . $word . '%';
                $i++;
            }

            $whereClause = implode(' AND ', $conditions);

            $sql = "SELECT
                        codigo,
                        descripcion,
                        saldo,
                        precio
                    FROM vista_productos
                    WHERE {$whereClause}
                    ORDER BY descripcion ASC
                    LIMIT {$limit}";

            $stmt = $this->dbCobol->prepare($sql);
            foreach ($params as $param => $value) {
                $stmt->bindValue($param, $value);
            }
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $products;
        } catch (PDOException $e) {
            error_log("Product::search Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene un producto por su código
     */
    public function getByCode(string $codigo): array|false {
        try {
            $sql = "SELECT
                        codigo,
                        descripcion,
                        saldo,
                        precio
                    FROM vista_productos
                    WHERE codigo = :codigo";

            $stmt = $this->dbCobol->prepare($sql);
            $stmt->bindValue(':codigo', $codigo);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                $product['imagen_url'] = $this->getProductImage($product['codigo']);
            }

            return $product;
        } catch (PDOException $e) {
            error_log("Product::getByCode Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene la imagen principal de un producto
     */
    public function getProductImage(string $codigo): ?string {
        try {
            $sql = "SELECT imagen_url
                    FROM imagenes
                    WHERE codigo_producto = :codigo
                    AND imagen_principal = 1
                    LIMIT 1";

            $stmt = $this->dbLocal->prepare($sql);
            $stmt->bindValue(':codigo', $codigo);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? $result['imagen_url'] : null;
        } catch (PDOException $e) {
            error_log("Product::getProductImage Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtiene todas las imágenes de un producto
     */
    public function getProductImages(string $codigo): array {
        try {
            $sql = "SELECT id, imagen_url, imagen_principal, orden
                    FROM imagenes
                    WHERE codigo_producto = :codigo
                    ORDER BY imagen_principal DESC, orden ASC";

            $stmt = $this->dbLocal->prepare($sql);
            $stmt->bindValue(':codigo', $codigo);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Product::getProductImages Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Agrega una imagen a un producto
     */
    public function addImage(string $codigo, string $imagenUrl, bool $principal = false): int|false {
        try {
            // Si es principal, quitar principal de otras
            if ($principal) {
                $sql = "UPDATE imagenes SET imagen_principal = 0 WHERE codigo_producto = :codigo";
                $stmt = $this->dbLocal->prepare($sql);
                $stmt->bindValue(':codigo', $codigo);
                $stmt->execute();
            }

            $sql = "INSERT INTO imagenes (codigo_producto, imagen_url, imagen_principal)
                    VALUES (:codigo, :imagen_url, :principal)";

            $stmt = $this->dbLocal->prepare($sql);
            $stmt->bindValue(':codigo', $codigo);
            $stmt->bindValue(':imagen_url', $imagenUrl);
            $stmt->bindValue(':principal', $principal ? 1 : 0, PDO::PARAM_INT);

            if ($stmt->execute()) {
                return (int)$this->dbLocal->lastInsertId();
            }
            return false;
        } catch (PDOException $e) {
            error_log("Product::addImage Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina una imagen
     */
    public function deleteImage(int $imageId): bool {
        try {
            $sql = "DELETE FROM imagenes WHERE id = :id";
            $stmt = $this->dbLocal->prepare($sql);
            $stmt->bindValue(':id', $imageId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Product::deleteImage Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Establece una imagen como principal
     */
    public function setMainImage(string $codigo, int $imageId): bool {
        try {
            // Quitar principal de todas las imágenes del producto
            $sql = "UPDATE imagenes SET imagen_principal = 0 WHERE codigo_producto = :codigo";
            $stmt = $this->dbLocal->prepare($sql);
            $stmt->bindValue(':codigo', $codigo);
            $stmt->execute();

            // Establecer la nueva principal
            $sql = "UPDATE imagenes SET imagen_principal = 1 WHERE id = :id AND codigo_producto = :codigo";
            $stmt = $this->dbLocal->prepare($sql);
            $stmt->bindValue(':id', $imageId, PDO::PARAM_INT);
            $stmt->bindValue(':codigo', $codigo);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Product::setMainImage Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cuenta total de productos
     */
    public function getCount(): int {
        try {
            $sql = "SELECT COUNT(*) FROM vista_productos";
            $stmt = $this->dbCobol->query($sql);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Product::getCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene productos con stock bajo (saldo < umbral)
     */
    public function getLowStockProducts(int $threshold = 10, int $limit = 20): array {
        try {
            $sql = "SELECT
                        codigo,
                        descripcion,
                        saldo,
                        precio
                    FROM vista_productos
                    WHERE saldo > 0 AND saldo <= :threshold
                    ORDER BY saldo ASC
                    LIMIT :limit";

            $stmt = $this->dbCobol->prepare($sql);
            $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Product::getLowStockProducts Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene productos con stock
     */
    public function getProductsWithStock(int $limit = 100): array {
        try {
            $sql = "SELECT
                        codigo,
                        descripcion,
                        saldo,
                        precio
                    FROM vista_productos
                    WHERE saldo > 0
                    ORDER BY descripcion ASC
                    LIMIT :limit";

            $stmt = $this->dbCobol->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->attachImages($products);

            return $products;
        } catch (PDOException $e) {
            error_log("Product::getProductsWithStock Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta productos con stock disponible
     */
    public function getCountWithStock(): int {
        try {
            $sql = "SELECT COUNT(*) FROM vista_productos WHERE saldo > 0";
            $stmt = $this->dbCobol->query($sql);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Product::getCountWithStock Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Cuenta productos con stock bajo
     */
    public function getLowStockCount(int $threshold = 10): int {
        try {
            $sql = "SELECT COUNT(*) FROM vista_productos WHERE saldo > 0 AND saldo <= :threshold";
            $stmt = $this->dbCobol->prepare($sql);
            $stmt->bindValue(':threshold', $threshold, PDO::PARAM_INT);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Product::getLowStockCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene los productos más cotizados
     * Usa tabla quotation_items de BD local
     */
    public function getMostQuotedProducts($startDate = null, $endDate = null, $limit = 10): array {
        try {
            $sql = "SELECT
                        qi.description as description,
                        COUNT(qi.id) as times_quoted,
                        SUM(qi.quantity) as total_quantity,
                        AVG(qi.unit_price) as avg_price
                    FROM quotation_items qi
                    INNER JOIN quotations q ON qi.quotation_id = q.id";

            $params = [];

            if ($startDate && $endDate) {
                $sql .= " WHERE DATE(q.quotation_date) BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            }

            $sql .= " GROUP BY qi.description
                      ORDER BY times_quoted DESC
                      LIMIT :limit";

            $stmt = $this->dbLocal->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Extract code from description format "[CODE] Description"
            foreach ($results as &$row) {
                $row['code'] = '';
                $row['brand'] = '';
                $row['total_stock'] = 0;
                if (preg_match('/^\[([^\]]+)\]\s*(.+)$/', $row['description'], $matches)) {
                    $row['code'] = $matches[1];
                    $row['description'] = $matches[2];
                }
            }
            unset($row);

            return $results;
        } catch (PDOException $e) {
            error_log("Product::getMostQuotedProducts Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta productos agrupados por marca
     */
    public function getCountByBrand(): array {
        try {
            $sql = "SELECT
                        COALESCE(marca, 'Sin marca') as brand,
                        COUNT(*) as count
                    FROM vista_productos
                    GROUP BY marca
                    ORDER BY count DESC";
            $stmt = $this->dbCobol->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Product::getCountByBrand Error: " . $e->getMessage());
            return [];
        }
    }
}
?>
