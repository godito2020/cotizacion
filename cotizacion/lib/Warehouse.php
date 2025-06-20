<?php
// cotizacion/lib/Warehouse.php

class Warehouse {
    private $db;

    public function __construct() {
        $this->db = getDBConnection(); // Assumes getDBConnection() is available via init.php
    }

    /**
     * Creates a new warehouse for a specific company.
     *
     * @param int $company_id
     * @param string $name
     * @param string|null $location
     * @return int|false The ID of the newly created warehouse, or false on failure.
     */
    public function create(int $company_id, string $name, ?string $location = null): int|false {
        if (empty($name)) {
            error_log("Warehouse::create - Warehouse name cannot be empty.");
            return false;
        }

        try {
            $sql = "INSERT INTO warehouses (company_id, name, location, created_at)
                    VALUES (:company_id, :name, :location, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':location', $location);

            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            }
            error_log("Warehouse::create - Failed to execute statement for warehouse: " . $name);
            return false;
        } catch (PDOException $e) {
            error_log("Warehouse::create Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches a warehouse by its ID, ensuring it belongs to the specified company.
     *
     * @param int $warehouse_id
     * @param int $company_id
     * @return array|false Warehouse details or false if not found or not belonging to the company.
     */
    public function getById(int $warehouse_id, int $company_id): array|false {
        try {
            $sql = "SELECT * FROM warehouses WHERE id = :warehouse_id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':warehouse_id', $warehouse_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Warehouse::getById Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all warehouses for a given company.
     *
     * @param int $company_id
     * @return array An array of warehouses.
     */
    public function getAllByCompany(int $company_id): array {
        try {
            $sql = "SELECT * FROM warehouses WHERE company_id = :company_id ORDER BY name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Warehouse::getAllByCompany Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Updates a warehouse's details, ensuring it belongs to the company.
     *
     * @param int $warehouse_id
     * @param int $company_id
     * @param string $name
     * @param string|null $location
     * @return bool True on success, false on failure.
     */
    public function update(int $warehouse_id, int $company_id, string $name, ?string $location = null): bool {
        if (empty($name)) {
            error_log("Warehouse::update - Warehouse name cannot be empty for ID {$warehouse_id}.");
            return false;
        }

        // Verify warehouse belongs to company before update
        $warehouse = $this->getById($warehouse_id, $company_id);
        if (!$warehouse) {
            error_log("Warehouse::update - Warehouse ID {$warehouse_id} not found or does not belong to company ID {$company_id}.");
            return false;
        }

        try {
            $sql = "UPDATE warehouses SET
                        name = :name,
                        location = :location
                    WHERE id = :warehouse_id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':warehouse_id', $warehouse_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT); // Ensure for safety
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':location', $location);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Warehouse::update Error for warehouse ID {$warehouse_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a warehouse, ensuring it belongs to the company.
     *
     * @param int $warehouse_id
     * @param int $company_id
     * @return bool True on success, false on failure.
     */
    public function delete(int $warehouse_id, int $company_id): bool {
        // Verify warehouse belongs to company before delete
        $warehouse = $this->getById($warehouse_id, $company_id);
        if (!$warehouse) {
            error_log("Warehouse::delete - Warehouse ID {$warehouse_id} not found or does not belong to company ID {$company_id}.");
            return false;
        }

        try {
            // Consider consequences: stock items related to this warehouse.
            // The current schema has ON DELETE CASCADE for stock.warehouse_id.
            $sql = "DELETE FROM warehouses WHERE id = :warehouse_id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':warehouse_id', $warehouse_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT); // Ensure for safety
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Warehouse::delete Error for warehouse ID {$warehouse_id}: " . $e->getMessage());
            // Catch foreign key constraint violations if stock items exist and ON DELETE CASCADE is not set or fails
            if ($e->getCode() == '23000') { // Integrity constraint violation
                 error_log("Warehouse::delete - Cannot delete warehouse ID {$warehouse_id} as it may have stock records associated.");
            }
            return false;
        }
    }

    /**
     * Finds a warehouse by its name within a specific company.
     *
     * @param string $name The name of the warehouse.
     * @param int $company_id The ID of the company.
     * @return array|false Warehouse record if found, false otherwise.
     */
    public function findByName(string $name, int $company_id): array|false {
        if (empty($name)) {
            return false;
        }
        try {
            $sql = "SELECT * FROM warehouses WHERE name = :name AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Warehouse::findByName Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
