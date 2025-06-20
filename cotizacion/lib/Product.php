<?php
// cotizacion/lib/Product.php

class Product {
    private $db;

    public function __construct() {
        $this->db = getDBConnection(); // Assumes getDBConnection() is available via init.php
    }

    /**
     * Checks if a SKU is unique for a given company, optionally excluding a specific product ID.
     *
     * @param string $sku The SKU to check.
     * @param int $company_id The ID of the company.
     * @param int|null $excludeProductId The ID of a product to exclude from the check (used during updates).
     * @return bool True if the SKU is unique, false otherwise.
     */
    public function isSkuUniqueForCompany(string $sku, int $company_id, ?int $excludeProductId = null): bool {
        if (empty($sku)) { // Or decide if empty SKUs are allowed and how they are handled
            return true; // Assuming empty SKU is not subject to uniqueness, or handle as an error elsewhere
        }
        try {
            $sql = "SELECT id FROM products WHERE sku = :sku AND company_id = :company_id";
            if ($excludeProductId !== null) {
                $sql .= " AND id != :exclude_product_id";
            }
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            if ($excludeProductId !== null) {
                $stmt->bindParam(':exclude_product_id', $excludeProductId, PDO::PARAM_INT);
            }
            $stmt->execute();
            return $stmt->fetchColumn() === false;
        } catch (PDOException $e) {
            error_log("Product::isSkuUniqueForCompany Error: " . $e->getMessage());
            // In case of DB error, it's safer to assume not unique or handle error appropriately
            return false;
        }
    }

    /**
     * Creates a new product for a specific company.
     *
     * @param int $company_id
     * @param string $name
     * @param string $sku
     * @param float $price
     * @param string|null $description
     * @return int|false The ID of the newly created product, or false on failure.
     */
    public function create(int $company_id, string $name, string $sku, float $price, ?string $description = null): int|false {
        if (empty($name) || empty($sku) || !is_numeric($price) || $price < 0) {
            error_log("Product::create - Invalid input data.");
            return false;
        }
        if (!$this->isSkuUniqueForCompany($sku, $company_id)) {
            error_log("Product::create - SKU '{$sku}' already exists for company ID {$company_id}.");
            return false; // SKU not unique for this company
        }

        try {
            $sql = "INSERT INTO products (company_id, name, sku, price, description, created_at)
                    VALUES (:company_id, :name, :sku, :price, :description, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':description', $description);

            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            }
            error_log("Product::create - Failed to execute statement for product: " . $name . " SKU: " . $sku);
            return false;
        } catch (PDOException $e) {
            error_log("Product::create Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches a product by its ID, ensuring it belongs to the specified company.
     *
     * @param int $product_id
     * @param int $company_id
     * @return array|false Product details or false if not found or not belonging to the company.
     */
    public function getById(int $product_id, int $company_id): array|false {
        try {
            $sql = "SELECT * FROM products WHERE id = :product_id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Product::getById Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all products for a given company.
     *
     * @param int $company_id
     * @return array An array of products.
     */
    public function getAllByCompany(int $company_id): array {
        try {
            $sql = "SELECT * FROM products WHERE company_id = :company_id ORDER BY name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Product::getAllByCompany Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Updates a product's details, ensuring it belongs to the company.
     *
     * @param int $product_id
     * @param int $company_id
     * @param string $name
     * @param string $sku
     * @param float $price
     * @param string|null $description
     * @return bool True on success, false on failure.
     */
    public function update(int $product_id, int $company_id, string $name, string $sku, float $price, ?string $description = null): bool {
        if (empty($name) || empty($sku) || !is_numeric($price) || $price < 0) {
            error_log("Product::update - Invalid input data for product ID {$product_id}.");
            return false;
        }
        if (!$this->isSkuUniqueForCompany($sku, $company_id, $product_id)) {
            error_log("Product::update - SKU '{$sku}' already exists for another product in company ID {$company_id}.");
            return false; // SKU not unique for this company (excluding current product)
        }

        // Verify product belongs to company before update
        $product = $this->getById($product_id, $company_id);
        if (!$product) {
            error_log("Product::update - Product ID {$product_id} not found or does not belong to company ID {$company_id}.");
            return false;
        }

        try {
            $sql = "UPDATE products SET
                        name = :name,
                        sku = :sku,
                        price = :price,
                        description = :description
                    WHERE id = :product_id AND company_id = :company_id"; // Double check company_id in WHERE
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':sku', $sku);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':description', $description);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Product::update Error for product ID {$product_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a product, ensuring it belongs to the company.
     *
     * @param int $product_id
     * @param int $company_id
     * @return bool True on success, false on failure.
     */
    public function delete(int $product_id, int $company_id): bool {
        // Verify product belongs to company before delete
        $product = $this->getById($product_id, $company_id);
        if (!$product) {
            error_log("Product::delete - Product ID {$product_id} not found or does not belong to company ID {$company_id}.");
            return false;
        }

        try {
            // Consider consequences: stock items related to this product might need to be handled (e.g., ON DELETE CASCADE for stock.product_id).
            // The current schema has ON DELETE CASCADE for stock.product_id, so stock records will be deleted.
            // Also, quotation_items.product_id is ON DELETE SET NULL.
            $sql = "DELETE FROM products WHERE id = :product_id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Product::delete Error for product ID {$product_id}: " . $e->getMessage());
            return false;
        }
    }
}
?>
