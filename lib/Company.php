<?php
// cotizacion/lib/Company.php

class Company {
    private $db;

    public function __construct() {
        $this->db = getDBConnection(); // Assumes getDBConnection() is available via init.php
    }

    /**
     * Creates a new company.
     *
     * @param string $name Company name.
     * @param string|null $tax_id Tax ID.
     * @param string|null $address Address.
     * @param string|null $phone Phone number.
     * @param string|null $email Email address.
     * @param string|null $logo_url URL to the company logo.
     * @return int|false The ID of the newly created company, or false on failure.
     */
    public function create(
        string $name,
        ?string $tax_id = null,
        ?string $address = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $logo_url = null
    ): int|false {
        if (empty($name)) {
            error_log("Company::create - Company name cannot be empty.");
            return false;
        }

        try {
            $sql = "INSERT INTO companies (name, tax_id, address, phone, email, logo_url, created_at)
                    VALUES (:name, :tax_id, :address, :phone, :email, :logo_url, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':tax_id', $tax_id);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':logo_url', $logo_url);

            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            } else {
                error_log("Company::create - Failed to execute statement for company: " . $name);
                return false;
            }
        } catch (PDOException $e) {
            error_log("Company::create Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches a company by its ID.
     *
     * @param int $id The company ID.
     * @return array|false An associative array of company details if found, false otherwise.
     */
    public function getById(int $id): array|false {
        try {
            $sql = "SELECT * FROM companies WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Company::getById Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all companies.
     *
     * @return array An array of all companies, each as an associative array.
     */
    public function getAll(): array {
        try {
            $sql = "SELECT * FROM companies ORDER BY name ASC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Company::getAll Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Updates an existing company's details.
     *
     * @param int $id Company ID.
     * @param string $name Company name.
     * @param string|null $tax_id Tax ID.
     * @param string|null $address Address.
     * @param string|null $phone Phone number.
     * @param string|null $email Email address.
     * @param string|null $logo_url URL to the company logo.
     * @return bool True on success, false on failure.
     */
    public function update(
        int $id,
        string $name,
        ?string $tax_id = null,
        ?string $address = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $logo_url = null
    ): bool {
        if (empty($name)) {
            error_log("Company::update - Company name cannot be empty for ID: " . $id);
            return false;
        }

        try {
            $sql = "UPDATE companies SET
                        name = :name,
                        tax_id = :tax_id,
                        address = :address,
                        phone = :phone,
                        email = :email,
                        logo_url = :logo_url
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':tax_id', $tax_id);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':logo_url', $logo_url);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Company::update Error for ID " . $id . ": " . $e->getMessage());
            return false;
        }
    }
}
?>
