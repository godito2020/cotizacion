<?php
// cotizacion/lib/Quotation.php

class Quotation {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    /**
     * Generates a unique quotation number for a given company.
     * Format: C{company_id}-YYYY-XXXX (e.g., C1-2023-0001)
     * This is a simple sequential number per year per company.
     * More robust solutions might involve company-specific prefixes from settings.
     *
     * @param int $company_id
     * @return string The generated quotation number.
     */
    public function generateQuotationNumber(int $company_id): string {
        $year = date('Y');
        $prefix = "C{$company_id}-{$year}-";

        try {
            $sql = "SELECT quotation_number FROM quotations
                    WHERE company_id = :company_id AND quotation_number LIKE :prefix
                    ORDER BY quotation_number DESC LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->bindValue(':prefix', $prefix . '%');
            $stmt->execute();

            $lastNumber = $stmt->fetchColumn();

            $nextSequence = 1;
            if ($lastNumber) {
                $parts = explode('-', $lastNumber);
                $lastSequence = intval(end($parts));
                $nextSequence = $lastSequence + 1;
            }
            return $prefix . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);

        } catch (PDOException $e) {
            error_log("generateQuotationNumber Error: " . $e->getMessage());
            // Fallback or throw exception - for now, a basic fallback
            return $prefix . "ERR" . time();
        }
    }

    /**
     * Creates a new quotation.
     *
     * @param int $company_id
     * @param int $customer_id
     * @param int $user_id (creator)
     * @param string $quotation_date (YYYY-MM-DD)
     * @param string|null $valid_until (YYYY-MM-DD)
     * @param array $items Each item: ['product_id' => int|null, 'description' => string, 'quantity' => int, 'unit_price' => float, 'discount_percentage' => float (0-100)]
     * @param float|null $global_discount_percentage (0-100)
     * @param string|null $notes
     * @param string|null $terms
     * @param string $status
     * @return int|false New quotation ID or false on failure.
     */
    public function create(
        int $company_id,
        int $customer_id,
        int $user_id,
        string $quotation_date,
        ?string $valid_until,
        array $itemsData,
        ?float $global_discount_percentage = 0.0,
        ?string $notes = null,
        ?string $terms = null,
        string $status = 'Draft'
    ): int|false {
        if (empty($itemsData)) {
            error_log("Quotation::create - No items provided.");
            return false;
        }

        // --- Input Validation (basic) ---
        // Customer check
        $customerRepo = new Customer();
        if (!$customerRepo->getById($customer_id, $company_id)) {
            error_log("Quotation::create - Invalid customer_id {$customer_id} for company_id {$company_id}.");
            return false;
        }
        // User check (optional, user_id is from session usually, but good to be robust)
        $userRepo = new User();
        $user = $userRepo->findById($user_id);
        if (!$user || $user['company_id'] != $company_id) {
             error_log("Quotation::create - Invalid user_id {$user_id} or user does not belong to company_id {$company_id}.");
            return false;
        }

        $quotation_number = $this->generateQuotationNumber($company_id);

        // --- Calculations ---
        $calculated_subtotal = 0;
        $processed_items = [];

        foreach ($itemsData as $item) {
            $item_quantity = (int)($item['quantity'] ?? 0);
            $item_unit_price = (float)($item['unit_price'] ?? 0.0);
            $item_discount_percentage = (float)($item['discount_percentage'] ?? 0.0);

            if ($item_quantity <= 0 || $item_unit_price < 0) {
                error_log("Quotation::create - Invalid quantity or price for an item.");
                return false; // Or skip item / add to error list
            }

            $item_line_subtotal = $item_quantity * $item_unit_price;
            $item_discount_amount = ($item_line_subtotal * $item_discount_percentage) / 100.0;
            $item_line_total = $item_line_subtotal - $item_discount_amount;

            $calculated_subtotal += $item_line_total; // Subtotal is sum of line totals *before* global discount

            $processed_items[] = [
                'product_id' => isset($item['product_id']) && $item['product_id'] ? (int)$item['product_id'] : null,
                'description' => $item['description'] ?? '', // Product name or custom
                'quantity' => $item_quantity,
                'unit_price' => $item_unit_price,
                'discount_percentage' => $item_discount_percentage,
                'discount_amount' => round($item_discount_amount, 2),
                'line_total' => round($item_line_total, 2),
            ];
        }

        $global_discount_percentage_val = $global_discount_percentage ?? 0.0;
        $calculated_global_discount_amount = ($calculated_subtotal * $global_discount_percentage_val) / 100.0;
        $calculated_total = $calculated_subtotal - $calculated_global_discount_amount;

        $this->db->beginTransaction();
        try {
            $sql_quotation = "INSERT INTO quotations
                (company_id, customer_id, user_id, quotation_number, quotation_date, valid_until,
                subtotal, global_discount_percentage, global_discount_amount, total,
                status, notes, terms_and_conditions, created_at)
                VALUES
                (:company_id, :customer_id, :user_id, :quotation_number, :quotation_date, :valid_until,
                :subtotal, :global_discount_percentage, :global_discount_amount, :total,
                :status, :notes, :terms, NOW())";

            $stmt_quotation = $this->db->prepare($sql_quotation);
            $stmt_quotation->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt_quotation->bindParam(':customer_id', $customer_id, PDO::PARAM_INT);
            $stmt_quotation->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_quotation->bindParam(':quotation_number', $quotation_number);
            $stmt_quotation->bindParam(':quotation_date', $quotation_date);
            $stmt_quotation->bindParam(':valid_until', $valid_until); // PDO handles NULL
            $stmt_quotation->bindValue(':subtotal', round($calculated_subtotal, 2));
            $stmt_quotation->bindValue(':global_discount_percentage', round($global_discount_percentage_val, 2));
            $stmt_quotation->bindValue(':global_discount_amount', round($calculated_global_discount_amount, 2));
            $stmt_quotation->bindValue(':total', round($calculated_total, 2));
            $stmt_quotation->bindParam(':status', $status);
            $stmt_quotation->bindParam(':notes', $notes);
            $stmt_quotation->bindParam(':terms', $terms);

            if (!$stmt_quotation->execute()) {
                $this->db->rollBack();
                error_log("Quotation::create - Failed to insert quotation header. Error: " . implode(", ", $stmt_quotation->errorInfo()));
                return false;
            }
            $quotation_id = (int)$this->db->lastInsertId();

            // Insert items
            $sql_item = "INSERT INTO quotation_items
                (quotation_id, product_id, description, quantity, unit_price,
                discount_percentage, discount_amount, line_total) VALUES
                (:quotation_id, :product_id, :description, :quantity, :unit_price,
                :discount_percentage, :discount_amount, :line_total)";
            $stmt_item = $this->db->prepare($sql_item);

            foreach ($processed_items as $p_item) {
                $stmt_item->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
                $stmt_item->bindParam(':product_id', $p_item['product_id']); // PDO handles NULL
                $stmt_item->bindParam(':description', $p_item['description']);
                $stmt_item->bindParam(':quantity', $p_item['quantity'], PDO::PARAM_INT);
                $stmt_item->bindParam(':unit_price', $p_item['unit_price']);
                $stmt_item->bindParam(':discount_percentage', $p_item['discount_percentage']);
                $stmt_item->bindParam(':discount_amount', $p_item['discount_amount']);
                $stmt_item->bindParam(':line_total', $p_item['line_total']);

                if (!$stmt_item->execute()) {
                    $this->db->rollBack();
                     error_log("Quotation::create - Failed to insert quotation item. Error: " . implode(", ", $stmt_item->errorInfo()));
                    return false;
                }
            }

            $this->db->commit();
            return $quotation_id;

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Quotation::create PDOException: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches a quotation by its ID, including its items, ensuring it belongs to the company.
     *
     * @param int $quotation_id
     * @param int $company_id
     * @return array|false Quotation details (header + items array) or false.
     */
    public function getById(int $quotation_id, int $company_id): array|false {
        try {
            $sql_header = "SELECT q.*, c.name as customer_name, u.username as user_username, u.first_name as user_first_name, u.last_name as user_last_name
                           FROM quotations q
                           JOIN customers c ON q.customer_id = c.id
                           JOIN users u ON q.user_id = u.id
                           WHERE q.id = :quotation_id AND q.company_id = :company_id";
            $stmt_header = $this->db->prepare($sql_header);
            $stmt_header->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
            $stmt_header->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt_header->execute();

            $quotation = $stmt_header->fetch(PDO::FETCH_ASSOC);

            if (!$quotation) {
                return false;
            }

            $sql_items = "SELECT qi.*, p.name as product_name, p.sku as product_sku
                          FROM quotation_items qi
                          LEFT JOIN products p ON qi.product_id = p.id
                          WHERE qi.quotation_id = :quotation_id";
            // Note: If product p belongs to a different company, this join might still work but is bad practice.
            // product_id in quotation_items is already implicitly company-scoped if items are added through a company-scoped quotation.
            $stmt_items = $this->db->prepare($sql_items);
            $stmt_items->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
            $stmt_items->execute();

            $quotation['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            return $quotation;

        } catch (PDOException $e) {
            error_log("Quotation::getById Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all quotations for a given company, optionally filtered.
     *
     * @param int $company_id
     * @param array $filters (e.g., ['status' => 'Sent', 'customer_id' => 123]) - Not implemented yet
     * @return array List of quotations (headers only for listing).
     */
    public function getAllByCompany(int $company_id, array $filters = []): array {
        try {
            // Basic query, can be extended with filters
            $sql = "SELECT q.id, q.quotation_number, q.quotation_date, q.total, q.status,
                           c.name as customer_name, u.username as creator_username
                    FROM quotations q
                    JOIN customers c ON q.customer_id = c.id
                    JOIN users u ON q.user_id = u.id
                    WHERE q.company_id = :company_id";

            // TODO: Add filter processing here if $filters is not empty
            // Example: if (!empty($filters['status'])) { $sql .= " AND q.status = :status"; }

            $sql .= " ORDER BY q.quotation_date DESC, q.id DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);

            // TODO: Bind filter parameters here
            // Example: if (!empty($filters['status'])) { $stmt->bindParam(':status', $filters['status']); }

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Quotation::getAllByCompany Error: " . $e->getMessage());
            return [];
        }
    }

    // update, delete, updateStatus methods would follow a similar pattern,
    // ensuring company_id checks and transactions where necessary.
    // These will be implemented as per subtask progression.

}
?>
