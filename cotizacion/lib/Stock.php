<?php
// cotizacion/lib/Stock.php

class Stock {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    /**
     * Updates stock quantity for a product in a warehouse.
     * If record doesn't exist, it creates one. Otherwise, updates quantity.
     * Ensures product and warehouse belong to the same company by checking their individual company_id.
     *
     * @param int $product_id
     * @param int $warehouse_id
     * @param int $quantity
     * @param int $company_id The company ID of the user performing the action, to verify ownership of product and warehouse.
     * @return bool True on success, false on failure.
     */
    public function updateStock(int $product_id, int $warehouse_id, int $quantity, int $company_id): bool {
        if ($quantity < 0) {
            error_log("Stock::updateStock - Quantity cannot be negative.");
            return false;
        }

        // Verify product belongs to the company
        $productRepo = new Product(); // Autoloaded
        $product = $productRepo->getById($product_id, $company_id);
        if (!$product) {
            error_log("Stock::updateStock - Product ID {$product_id} not found for company ID {$company_id}.");
            return false;
        }

        // Verify warehouse belongs to the company
        $warehouseRepo = new Warehouse(); // Autoloaded
        $warehouse = $warehouseRepo->getById($warehouse_id, $company_id);
        if (!$warehouse) {
            error_log("Stock::updateStock - Warehouse ID {$warehouse_id} not found for company ID {$company_id}.");
            return false;
        }

        try {
            // Using INSERT ... ON DUPLICATE KEY UPDATE for atomicity
            // Assumes `product_id` and `warehouse_id` have a UNIQUE constraint together.
            $sql = "INSERT INTO stock (product_id, warehouse_id, quantity, last_updated)
                    VALUES (:product_id, :warehouse_id, :quantity, NOW())
                    ON DUPLICATE KEY UPDATE quantity = :quantity_update, last_updated = NOW()";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':warehouse_id', $warehouse_id, PDO::PARAM_INT);
            $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
            $stmt->bindParam(':quantity_update', $quantity, PDO::PARAM_INT); // For the UPDATE part

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Stock::updateStock Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets the stock record for a specific product in a specific warehouse.
     *
     * @param int $product_id
     * @param int $warehouse_id
     * @return array|false Stock record (id, product_id, warehouse_id, quantity, last_updated) or false if not found.
     */
    public function getStockRecord(int $product_id, int $warehouse_id): array|false {
        try {
            $sql = "SELECT * FROM stock WHERE product_id = :product_id AND warehouse_id = :warehouse_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':warehouse_id', $warehouse_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Stock::getStockRecord Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets the stock quantity for a specific product in a specific warehouse.
     *
     * @param int $product_id
     * @param int $warehouse_id
     * @return int|null Quantity or null if not found/error.
     */
    public function getStockQuantity(int $product_id, int $warehouse_id): ?int {
        $record = $this->getStockRecord($product_id, $warehouse_id);
        return $record ? (int)$record['quantity'] : null;
    }


    /**
     * Lists stock quantities for a product across all warehouses it's in (for a specific company).
     *
     * @param int $product_id
     * @param int $company_id (to ensure product and warehouses are within the company)
     * @return array List of ['warehouse_id' => id, 'warehouse_name' => name, 'quantity' => q, 'last_updated' => lu]
     */
    public function getProductStockDetails(int $product_id, int $company_id): array {
        // First, verify the product belongs to the company
        $productRepo = new Product();
        if (!$productRepo->getById($product_id, $company_id)) {
            error_log("Stock::getProductStockDetails - Product ID {$product_id} not found for company ID {$company_id}.");
            return [];
        }

        try {
            $sql = "SELECT s.warehouse_id, w.name as warehouse_name, s.quantity, s.last_updated
                    FROM stock s
                    JOIN warehouses w ON s.warehouse_id = w.id
                    WHERE s.product_id = :product_id AND w.company_id = :company_id
                    ORDER BY w.name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Stock::getProductStockDetails Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Lists all products and their quantities in a specific warehouse (for a specific company).
     *
     * @param int $warehouse_id
     * @param int $company_id (to ensure warehouse and products are within the company)
     * @return array List of ['product_id' => id, 'product_name' => name, 'sku' => sku, 'quantity' => q, 'last_updated' => lu]
     */
    public function getWarehouseStockDetails(int $warehouse_id, int $company_id): array {
        // First, verify the warehouse belongs to the company
        $warehouseRepo = new Warehouse();
        if (!$warehouseRepo->getById($warehouse_id, $company_id)) {
            error_log("Stock::getWarehouseStockDetails - Warehouse ID {$warehouse_id} not found for company ID {$company_id}.");
            return [];
        }

        try {
            $sql = "SELECT s.product_id, p.name as product_name, p.sku, s.quantity, s.last_updated
                    FROM stock s
                    JOIN products p ON s.product_id = p.id
                    WHERE s.warehouse_id = :warehouse_id AND p.company_id = :company_id
                    ORDER BY p.name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':warehouse_id', $warehouse_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Stock::getWarehouseStockDetails Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Provides an overview of all products for a company, their total stock, and warehouse breakdown.
     *
     * @param int $company_id
     * @return array An array where each element is a product with its details and a 'stock_levels' sub-array.
     *               Example: [ 'product_id' => 1, 'name' => 'Laptop', 'sku' => 'LP001', 'total_stock' => 15,
     *                          'stock_levels' => [ ['warehouse_name' => 'Main', 'quantity' => 10], ... ] ]
     */
    public function getCompanyStockOverview(int $company_id): array {
        $productRepo = new Product();
        $products = $productRepo->getAllByCompany($company_id);
        $overview = [];

        foreach ($products as $product) {
            $stock_details = $this->getProductStockDetails($product['id'], $company_id);
            $total_stock = 0;
            $levels = [];
            foreach ($stock_details as $detail) {
                $total_stock += $detail['quantity'];
                $levels[] = [
                    'warehouse_id' => $detail['warehouse_id'],
                    'warehouse_name' => $detail['warehouse_name'],
                    'quantity' => $detail['quantity'],
                    'last_updated' => $detail['last_updated']
                ];
            }
            $overview[] = [
                'product_id' => $product['id'],
                'name' => $product['name'],
                'sku' => $product['sku'],
                'price' => $product['price'],
                'description' => $product['description'],
                'total_stock' => $total_stock,
                'stock_levels' => $levels
            ];
        }
        return $overview;
    }
}
?>
