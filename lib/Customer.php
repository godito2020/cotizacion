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
        int $user_id,
        string $name,
        ?string $contact_person = null,
        ?string $email = null,
        ?string $email_cc = null,
        ?string $phone = null,
        ?string $address = null,
        ?string $tax_id = null,
        ?string $company_status = null
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
            $sql = "INSERT INTO customers (company_id, user_id, name, contact_person, email, email_cc, phone, address, tax_id, company_status, created_at)
                    VALUES (:company_id, :user_id, :name, :contact_person, :email, :email_cc, :phone, :address, :tax_id, :company_status, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':contact_person', $contact_person);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':email_cc', $email_cc);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':tax_id', $tax_id);
            $stmt->bindParam(':company_status', $company_status);

            $result = $stmt->execute();
            if ($result) {
                return (int)$this->db->lastInsertId();
            }
            $errorInfo = $stmt->errorInfo();
            $errorMsg = "DB Error: " . json_encode($errorInfo);
            error_log("Customer::create - Failed for: " . $name . " - " . $errorMsg);
            $_SESSION['db_error'] = $errorMsg;
            return false;
        } catch (PDOException $e) {
            $errorMsg = "PDO Error: " . $e->getMessage();
            error_log("Customer::create - " . $errorMsg);
            $_SESSION['db_error'] = $errorMsg;
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
     * Fetches a customer by ID without company filter (for admin/master users).
     */
    public function getByIdGlobal(int $customer_id): array|false {
        try {
            $sql = "SELECT c.*, co.name as company_name
                    FROM customers c
                    LEFT JOIN companies co ON c.company_id = co.id
                    WHERE c.id = :customer_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer::getByIdGlobal Error: " . $e->getMessage());
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
            $sql = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as owner_name
                    FROM customers c
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.company_id = :company_id ORDER BY c.name ASC";
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
     * Fetches all customers from all companies (for master/admin users).
     *
     * @return array An array of customers.
     */
    public function getAll(): array {
        try {
            $sql = "SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as owner_name,
                           co.name as company_name
                    FROM customers c
                    LEFT JOIN users u ON c.user_id = u.id
                    LEFT JOIN companies co ON c.company_id = co.id
                    ORDER BY c.name ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer::getAll Error: " . $e->getMessage());
            return [];
        }
    }

     /**
      * Updates a customer's details, ensuring it belongs to the company.
      *
      * @param int $customer_id
      * @param int $company_id
      * @param int $user_id
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
         int $user_id,
         string $name,
         ?string $contact_person = null,
         ?string $email = null,
         ?string $email_cc = null,
         ?string $phone = null,
         ?string $address = null,
         ?string $tax_id = null,
         ?string $company_status = null
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
                        user_id = :user_id,
                        name = :name,
                        contact_person = :contact_person,
                        email = :email,
                        email_cc = :email_cc,
                        phone = :phone,
                        address = :address,
                        tax_id = :tax_id,
                        company_status = :company_status
                    WHERE id = :customer_id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':contact_person', $contact_person);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':email_cc', $email_cc);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':tax_id', $tax_id);
            $stmt->bindParam(':company_status', $company_status);

            $result = $stmt->execute();
            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                error_log("Customer::update - Failed for ID {$customer_id}: " . json_encode($errorInfo));
            }
            return $result;
        } catch (PDOException $e) {
            error_log("Customer::update PDO Error for ID {$customer_id}: " . $e->getMessage() . " - Code: " . $e->getCode());
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

    /**
     * Get total count of customers for a company
     */
    public function getCount($companyId) {
        try {
            $sql = "SELECT COUNT(*) FROM customers WHERE company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['company_id' => $companyId]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Customer::getCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get count of new customers within a date range
     */
    public function getNewCustomersCount($companyId, $startDate, $endDate) {
        try {
            $sql = "SELECT COUNT(*) FROM customers
                    WHERE company_id = :company_id
                    AND DATE(created_at) BETWEEN :start_date AND :end_date";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'company_id' => $companyId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Customer::getNewCustomersCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get count of active customers (customers with quotations in date range)
     */
    public function getActiveCustomersCount($companyId, $startDate, $endDate, $sellerId = null) {
        try {
            $sql = "SELECT COUNT(DISTINCT q.customer_id) FROM quotations q
                    WHERE q.company_id = :company_id
                    AND DATE(q.quotation_date) BETWEEN :start_date AND :end_date";
            $params = [
                'company_id' => $companyId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ];

            if ($sellerId) {
                $sql .= " AND q.user_id = :user_id";
                $params['user_id'] = $sellerId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Customer::getActiveCustomersCount Error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get count of customers by type
     */
    public function getCountByType($companyId) {
        try {
            $sql = "SELECT customer_type, COUNT(*) as count FROM customers
                    WHERE company_id = :company_id
                    GROUP BY customer_type";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['company_id' => $companyId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer::getCountByType Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get recent customers
     */
    public function getRecent($companyId, $limit = 10) {
        try {
            $sql = "SELECT id, name, email, tax_id, contact_person, phone, created_at
                    FROM customers
                    WHERE company_id = :company_id
                    ORDER BY created_at DESC
                    LIMIT :limit";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer::getRecent Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get top customers by quotation amount
     */
    public function getTopCustomers($companyId, $startDate = null, $endDate = null, $limit = 10, $sellerId = null) {
        try {
            $sql = "SELECT
                        c.id,
                        c.name,
                        c.email,
                        c.tax_id,
                        c.contact_person,
                        c.phone,
                        COUNT(q.id) as quotation_count,
                        SUM(q.total) as total_amount,
                        AVG(q.total) as average_amount
                    FROM customers c
                    LEFT JOIN quotations q ON c.id = q.customer_id
                    WHERE c.company_id = :company_id";

            $params = ['company_id' => $companyId];

            if ($startDate && $endDate) {
                $sql .= " AND DATE(q.quotation_date) BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            }

            if ($sellerId) {
                $sql .= " AND q.user_id = :user_id";
                $params['user_id'] = $sellerId;
            }

            $sql .= " GROUP BY c.id
                     ORDER BY total_amount DESC
                     LIMIT :limit";

            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer::getTopCustomers Error: " . $e->getMessage());
            return [];
        }
    }
}
?>
