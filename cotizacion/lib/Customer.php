<?php
// cotizacion/lib/Customer.php

class Customer {
    private $db;

    public function __construct() {
        $this->db = getDBConnection(); // Assumes getDBConnection() is available via init.php
    }

    /**
     * Creates a new customer for a specific company.
     *
     * @param int $company_id
     * @param string $name
     * @param string|null $contact_person
     * @param string|null $email
     * @param string|null $phone
     * @param string|null $address
     * @param string|null $tax_id
     * @return int|false The ID of the newly created customer, or false on failure.
     */
    public function create(
        int $company_id,
        string $name,
        ?string $contact_person = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $address = null,
        ?string $tax_id = null
    ): int|false {
        if (empty($name)) {
            error_log("Customer::create - Customer name cannot be empty.");
            return false;
        }
        // Optional: Validate email format if provided
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("Customer::create - Invalid email format for: " . $email);
            // Decide if this should be a hard fail or just logged
            // For now, let's allow it but it should be caught by form validation.
        }

        // Optional: Check for duplicate tax_id within the same company
        if ($tax_id && $this->findByTaxId($tax_id, $company_id)) {
            error_log("Customer::create - Tax ID '{$tax_id}' already exists for company ID {$company_id}.");
            // This should ideally be handled at the form level to give user feedback.
            // Returning false here might be too abrupt without context to the user.
            // For now, we'll allow the DB unique constraint (if any) or proceed.
            // The schema does not have a unique constraint on (tax_id, company_id) for customers currently.
        }

        try {
            $sql = "INSERT INTO customers (company_id, name, contact_person, email, phone, address, tax_id, created_at)
                    VALUES (:company_id, :name, :contact_person, :email, :phone, :address, :tax_id, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':contact_person', $contact_person);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':tax_id', $tax_id);

            if ($stmt->execute()) {
                return (int)$this->db->lastInsertId();
            }
            error_log("Customer::create - Failed to execute statement for customer: " . $name);
            return false;
        } catch (PDOException $e) {
            error_log("Customer::create Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches a customer by its ID, ensuring it belongs to the specified company.
     *
     * @param int $customer_id
     * @param int $company_id
     * @return array|false Customer details or false if not found or not belonging to the company.
     */
    public function getById(int $customer_id, int $company_id): array|false {
        try {
            $sql = "SELECT * FROM customers WHERE id = :customer_id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer::getById Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all customers for a given company.
     *
     * @param int $company_id
     * @return array An array of customers.
     */
    public function getAllByCompany(int $company_id): array {
        try {
            $sql = "SELECT * FROM customers WHERE company_id = :company_id ORDER BY name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer::getAllByCompany Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Updates a customer's details, ensuring it belongs to the company.
     *
     * @param int $customer_id
     * @param int $company_id
     * @param string $name
     * @param string|null $contact_person
     * @param string|null $email
     * @param string|null $phone
     * @param string|null $address
     * @param string|null $tax_id
     * @return bool True on success, false on failure.
     */
    public function update(
        int $customer_id,
        int $company_id,
        string $name,
        ?string $contact_person = null,
        ?string $email = null,
        ?string $phone = null,
        ?string $address = null,
        ?string $tax_id = null
    ): bool {
        if (empty($name)) {
            error_log("Customer::update - Customer name cannot be empty for ID {$customer_id}.");
            return false;
        }

        // Verify customer belongs to company before update
        $customer = $this->getById($customer_id, $company_id);
        if (!$customer) {
            error_log("Customer::update - Customer ID {$customer_id} not found or does not belong to company ID {$company_id}.");
            return false;
        }

        // Optional: Check for duplicate tax_id within the same company, excluding current customer
        if ($tax_id) {
            $existing_customer_by_tax = $this->findByTaxId($tax_id, $company_id);
            if ($existing_customer_by_tax && $existing_customer_by_tax['id'] != $customer_id) {
                 error_log("Customer::update - Tax ID '{$tax_id}' already exists for another customer (ID {$existing_customer_by_tax['id']}) in company ID {$company_id}.");
                 // This should be handled at form level.
            }
        }


        try {
            $sql = "UPDATE customers SET
                        name = :name,
                        contact_person = :contact_person,
                        email = :email,
                        phone = :phone,
                        address = :address,
                        tax_id = :tax_id
                    WHERE id = :customer_id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT); // Ensure for safety
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':contact_person', $contact_person);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':tax_id', $tax_id);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Customer::update Error for customer ID {$customer_id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a customer, ensuring it belongs to the company.
     *
     * @param int $customer_id
     * @param int $company_id
     * @return bool True on success, false on failure.
     */
    public function delete(int $customer_id, int $company_id): bool {
        // Verify customer belongs to company
        if (!$this->getById($customer_id, $company_id)) {
            error_log("Customer::delete - Customer ID {$customer_id} not found or does not belong to company ID {$company_id}.");
            return false;
        }

        try {
            // Current schema: quotations.customer_id is ON DELETE RESTRICT.
            // So, if a customer has quotations, deletion will fail at DB level.
            $sql = "DELETE FROM customers WHERE id = :customer_id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Customer::delete Error for customer ID {$customer_id}: " . $e->getMessage());
            if ($e->getCode() == '23000') { // Integrity constraint violation
                 error_log("Customer::delete - Cannot delete customer ID {$customer_id} as they may have existing quotations.");
            }
            return false;
        }
    }

    /**
     * Finds a customer by their Tax ID within a specific company.
     *
     * @param string $tax_id The Tax ID to search for.
     * @param int $company_id The ID of the company.
     * @return array|false An associative array of customer details if found, false otherwise.
     */
    public function findByTaxId(string $tax_id, int $company_id): array|false {
        if (empty($tax_id)) {
            return false;
        }
        try {
            $sql = "SELECT * FROM customers WHERE tax_id = :tax_id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':tax_id', $tax_id);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer::findByTaxId Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
