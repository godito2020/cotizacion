<?php
// cotizacion/lib/Quotation.php

class Quotation {
    private $db;

    public function __construct() {
        $this->db = getDBConnection(); // Assumes getDBConnection() is available via init.php
    }

    // Get last quoted price for a product to a specific customer
    public function getLastQuotedPrice($productId, $customerId, $companyId) {
        try {
            // First try to get last price for this specific product with the customer (any status)
            $sql = "SELECT qi.unit_price, qi.discount_percentage, q.created_at, q.quotation_number, q.currency
                    FROM quotation_items qi
                    JOIN quotations q ON qi.quotation_id = q.id
                    WHERE q.customer_id = :customer_id
                      AND qi.product_id = :product_id
                      AND q.company_id = :company_id
                      AND q.status != 'Rejected'
                    ORDER BY q.created_at DESC
                    LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'customer_id' => $customerId,
                'product_id' => $productId,
                'company_id' => $companyId
            ]);

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // If no result found, try without customer filter (last price to ANY customer)
            if (!$result) {
                $sql = "SELECT qi.unit_price, qi.discount_percentage, q.created_at, q.quotation_number, q.currency
                        FROM quotation_items qi
                        JOIN quotations q ON qi.quotation_id = q.id
                        WHERE qi.product_id = :product_id
                          AND q.company_id = :company_id
                          AND q.status != 'Rejected'
                        ORDER BY q.created_at DESC
                        LIMIT 1";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'product_id' => $productId,
                    'company_id' => $companyId
                ]);

                $result = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            return $result;
        } catch (PDOException $e) {
            error_log("Error getting last quoted price: " . $e->getMessage());
            return null;
        }
    }

    // Get quotation history for a customer
    public function getCustomerQuotationHistory($customerId, $companyId, $limit = 10) {
        try {
            $sql = "SELECT q.id, q.quotation_number, q.status, q.total, q.created_at,
                           COUNT(qi.id) as item_count
                    FROM quotations q
                    LEFT JOIN quotation_items qi ON q.id = qi.quotation_id
                    WHERE q.customer_id = :customer_id
                      AND q.company_id = :company_id
                    GROUP BY q.id
                    ORDER BY q.created_at DESC
                    LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':customer_id', $customerId, PDO::PARAM_INT);
            $stmt->bindParam(':company_id', $companyId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting customer quotation history: " . $e->getMessage());
            return [];
        }
    }

    // Get product quotation history for a customer
    public function getCustomerProductHistory($customerId, $productId, $companyId, $limit = 5) {
        try {
            $sql = "SELECT qi.unit_price, qi.discount_percentage, qi.quantity,
                           q.quotation_number, q.status, q.created_at, q.currency,
                           COALESCE(p.description, qi.description) as product_description,
                           qi.description as item_description
                    FROM quotation_items qi
                    JOIN quotations q ON qi.quotation_id = q.id
                    LEFT JOIN products p ON qi.product_id = p.id
                    WHERE q.customer_id = :customer_id
                      AND qi.product_id = :product_id
                      AND q.company_id = :company_id
                    ORDER BY q.created_at DESC
                    LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'customer_id' => $customerId,
                'product_id' => $productId,
                'company_id' => $companyId,
                'limit' => $limit
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting customer product history: " . $e->getMessage());
            return [];
        }
    }

    // Get all products quoted in a date range
    public function getProductHistoryByDate($companyId, $dateFrom = null, $dateTo = null, $customerId = null) {
        try {
            $where = ['q.company_id = :company_id'];
            $params = ['company_id' => $companyId];

            if ($dateFrom) {
                $where[] = 'q.quotation_date >= :date_from';
                $params['date_from'] = $dateFrom;
            }

            if ($dateTo) {
                $where[] = 'q.quotation_date <= :date_to';
                $params['date_to'] = $dateTo;
            }

            if ($customerId) {
                $where[] = 'q.customer_id = :customer_id';
                $params['customer_id'] = $customerId;
            }

            $whereClause = implode(' AND ', $where);

            $sql = "SELECT qi.id, qi.description, qi.quantity, qi.unit_price, qi.discount_percentage,
                           q.quotation_number, q.quotation_date, q.status, q.currency,
                           c.name as customer_name, c.tax_id as customer_tax_id,
                           COALESCE(p.code, '') as product_code,
                           COALESCE(p.description, qi.description) as product_description,
                           qi.product_id,
                           (qi.quantity * qi.unit_price * (1 - qi.discount_percentage / 100)) as subtotal
                    FROM quotation_items qi
                    JOIN quotations q ON qi.quotation_id = q.id
                    JOIN customers c ON q.customer_id = c.id
                    LEFT JOIN products p ON qi.product_id = p.id
                    WHERE $whereClause
                    ORDER BY q.quotation_date DESC, q.quotation_number DESC, qi.id ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting product history by date: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get quotations with filters and pagination
     */
    public function getQuotationsWithFilters($companyId, $filters = [], $page = 1, $perPage = 20) {
        try {
            $where = ['q.company_id = :company_id'];
            $params = ['company_id' => $companyId];

            // Status filter
            if (!empty($filters['status'])) {
                $where[] = 'q.status = :status';
                $params['status'] = $filters['status'];
            }

            // Search filter (quotation number or customer name)
            if (!empty($filters['search'])) {
                $where[] = '(q.quotation_number LIKE :search1 OR c.name LIKE :search2)';
                $params['search1'] = '%' . $filters['search'] . '%';
                $params['search2'] = '%' . $filters['search'] . '%';
            }

            // Date filters
            if (!empty($filters['date_from'])) {
                $where[] = 'q.quotation_date >= :date_from';
                $params['date_from'] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $where[] = 'q.quotation_date <= :date_to';
                $params['date_to'] = $filters['date_to'];
            }

            // User filter (if specified)
            if (!empty($filters['user_id'])) {
                $where[] = 'q.user_id = :user_id';
                $params['user_id'] = $filters['user_id'];
            }

            // Customer filter (if specified)
            if (!empty($filters['customer_id'])) {
                $where[] = 'q.customer_id = :customer_id';
                $params['customer_id'] = $filters['customer_id'];
            }

            $whereClause = implode(' AND ', $where);

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM quotations q
                        LEFT JOIN customers c ON q.customer_id = c.id
                        WHERE $whereClause";
            $countStmt = $this->db->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue(':' . $key, $value);
            }
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Calculate pagination
            $totalPages = ceil($total / $perPage);
            $offset = ($page - 1) * $perPage;

            // Get quotations
            $sql = "SELECT q.*, c.name as customer_name, u.username as creator_username
                    FROM quotations q
                    LEFT JOIN customers c ON q.customer_id = c.id
                    LEFT JOIN users u ON q.user_id = u.id
                    WHERE $whereClause
                    ORDER BY q.created_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $quotations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'quotations' => $quotations,
                'total' => $total,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'per_page' => $perPage
            ];
        } catch (PDOException $e) {
            error_log("Error getting quotations with filters: " . $e->getMessage());
            return [
                'quotations' => [],
                'total' => 0,
                'total_pages' => 0,
                'current_page' => $page,
                'per_page' => $perPage
            ];
        }
    }

    /**
     * Get a single quotation by ID with its items
     */
    public function getById($id, $companyId) {
        try {
            // Get quotation data
            $sql = "SELECT q.*, c.name as customer_name, u.first_name as user_first_name, u.last_name as user_last_name
                    FROM quotations q
                    LEFT JOIN customers c ON q.customer_id = c.id
                    LEFT JOIN users u ON q.user_id = u.id
                    WHERE q.id = :id AND q.company_id = :company_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id, 'company_id' => $companyId]);
            $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$quotation) {
                return null;
            }

            // Get quotation items
            $itemsSql = "SELECT qi.*, p.code as product_code, p.description as product_description, p.image_url as product_image_url
                        FROM quotation_items qi
                        LEFT JOIN products p ON qi.product_id = p.id
                        WHERE qi.quotation_id = :quotation_id
                        ORDER BY qi.id";

            $itemsStmt = $this->db->prepare($itemsSql);
            $itemsStmt->execute(['quotation_id' => $id]);
            $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $quotation['items'] = $items;

            return $quotation;
        } catch (PDOException $e) {
            error_log("Error getting quotation by ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update quotation status
     */
    public function updateStatus($id, $companyId, $status) {
        try {
            $sql = "UPDATE quotations SET status = :status, updated_at = NOW() WHERE id = :id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'status' => $status,
                'id' => $id,
                'company_id' => $companyId
            ]);
        } catch (PDOException $e) {
            error_log("Error updating quotation status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate totals for quotation items
     */
    public function calculateTotals($items, $globalDiscountPercentage = 0) {
        $subtotal = 0;
        $processedItems = [];

        foreach ($items as $item) {
            $lineTotal = $item['quantity'] * $item['unit_price'];
            $discountAmount = $lineTotal * ($item['discount_percentage'] / 100);
            $finalLineTotal = $lineTotal - $discountAmount;

            $processedItems[] = array_merge($item, [
                'line_total' => $finalLineTotal,
                'discount_amount' => $discountAmount
            ]);

            $subtotal += $finalLineTotal;
        }

        $globalDiscountAmount = $subtotal * ($globalDiscountPercentage / 100);
        $total = $subtotal - $globalDiscountAmount;

        return [
            'items' => $processedItems,
            'subtotal' => $subtotal,
            'global_discount_amount' => $globalDiscountAmount,
            'total' => $total
        ];
    }

    /**
     * Update quotation
     */
    public function update($id, $companyId, $data) {
        try {
            $this->db->beginTransaction();

            // If igv_mode is 'plus_igv', add 18% IGV to the total
            $igvMode = $data['igv_mode'] ?? 'included';
            $finalTotal = $data['total'];
            if ($igvMode === 'plus_igv') {
                $finalTotal = $data['total'] * 1.18;
            }

            // Update quotation
            $sql = "UPDATE quotations SET
                    customer_id = :customer_id,
                    quotation_date = :quotation_date,
                    valid_until = :valid_until,
                    currency = :currency,
                    igv_mode = :igv_mode,
                    subtotal = :subtotal,
                    global_discount_percentage = :global_discount_percentage,
                    global_discount_amount = :global_discount_amount,
                    total = :total,
                    notes = :notes,
                    terms_and_conditions = :terms_and_conditions,
                    updated_at = NOW()
                    WHERE id = :id AND company_id = :company_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'customer_id' => $data['customer_id'],
                'quotation_date' => $data['quotation_date'],
                'valid_until' => $data['valid_until'],
                'currency' => $data['currency'],
                'igv_mode' => $igvMode,
                'subtotal' => $data['subtotal'],
                'global_discount_percentage' => $data['global_discount_percentage'],
                'global_discount_amount' => $data['global_discount_amount'],
                'total' => $finalTotal,
                'notes' => $data['notes'],
                'terms_and_conditions' => $data['terms_and_conditions'],
                'id' => $id,
                'company_id' => $companyId
            ]);

            // Delete existing items
            $stmt = $this->db->prepare("DELETE FROM quotation_items WHERE quotation_id = :quotation_id");
            $stmt->execute(['quotation_id' => $id]);

            // Insert new items
            $itemSql = "INSERT INTO quotation_items
                       (quotation_id, product_id, description, quantity, unit_price, discount_percentage, line_total, discount_amount)
                       VALUES (:quotation_id, :product_id, :description, :quantity, :unit_price, :discount_percentage, :line_total, :discount_amount)";

            $itemStmt = $this->db->prepare($itemSql);
            foreach ($data['items'] as $item) {
                $itemStmt->execute([
                    'quotation_id' => $id,
                    'product_id' => $item['product_id'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percentage' => $item['discount_percentage'] ?? 0,
                    'line_total' => $item['line_total'],
                    'discount_amount' => $item['discount_amount'] ?? 0
                ]);
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error updating quotation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Duplicate quotation
     */
    public function duplicate($id, $companyId, $userId) {
        try {
            $this->db->beginTransaction();

            // Get original quotation
            $original = $this->getById($id, $companyId);
            if (!$original) {
                return false;
            }

            // Generate new quotation number
            $newNumber = $this->generateQuotationNumber($companyId);

            // Insert new quotation
            $sql = "INSERT INTO quotations
                   (company_id, user_id, customer_id, quotation_number, quotation_date, valid_until, currency,
                    subtotal, global_discount_percentage, global_discount_amount, total, status, notes, terms_and_conditions)
                   VALUES
                   (:company_id, :user_id, :customer_id, :quotation_number, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), :currency,
                    :subtotal, :global_discount_percentage, :global_discount_amount, :total, 'Draft', :notes, :terms_and_conditions)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'company_id' => $companyId,
                'user_id' => $userId,
                'customer_id' => $original['customer_id'],
                'quotation_number' => $newNumber,
                'currency' => $original['currency'],
                'subtotal' => $original['subtotal'],
                'global_discount_percentage' => $original['global_discount_percentage'],
                'global_discount_amount' => $original['global_discount_amount'],
                'total' => $original['total'],
                'notes' => $original['notes'],
                'terms_and_conditions' => $original['terms_and_conditions']
            ]);

            $newId = $this->db->lastInsertId();

            // Copy items
            $itemSql = "INSERT INTO quotation_items
                       (quotation_id, product_id, description, quantity, unit_price, discount_percentage, line_total, discount_amount)
                       SELECT :new_quotation_id, product_id, description, quantity, unit_price, discount_percentage, line_total, discount_amount
                       FROM quotation_items WHERE quotation_id = :original_quotation_id";

            $itemStmt = $this->db->prepare($itemSql);
            $itemStmt->execute([
                'new_quotation_id' => $newId,
                'original_quotation_id' => $id
            ]);

            $this->db->commit();
            return $newId;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error duplicating quotation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate quotation number
     */
    private function generateQuotationNumber($companyId) {
        $year = date('Y');
        $prefix = "C{$companyId}-{$year}-";

        $sql = "SELECT MAX(CAST(SUBSTRING(quotation_number, LENGTH(:prefix) + 1) AS UNSIGNED)) as max_num
                FROM quotations
                WHERE company_id = :company_id AND quotation_number LIKE :pattern";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'company_id' => $companyId,
            'prefix' => $prefix,
            'pattern' => $prefix . '%'
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextNum = ($result['max_num'] ?? 0) + 1;

        return $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Create new quotation
     */
    public function create($companyId, $customerId, $userId, $quotationDate, $validUntil, $items, $globalDiscountPercentage, $notes, $terms, $status, $currency, $paymentCondition = 'cash', $creditDays = null, $igvMode = 'included') {
        try {
            $this->db->beginTransaction();

            // Calculate totals
            $totals = $this->calculateTotals($items, $globalDiscountPercentage);

            // If igv_mode is 'plus_igv', add 18% IGV to the total
            $finalTotal = $totals['total'];
            if ($igvMode === 'plus_igv') {
                $finalTotal = $totals['total'] * 1.18;
            }

            // Generate quotation number
            $quotationNumber = $this->generateQuotationNumber($companyId);

            // Insert quotation
            $sql = "INSERT INTO quotations
                    (company_id, user_id, customer_id, quotation_number, quotation_date, valid_until, currency,
                     igv_mode, payment_condition, credit_days,
                     subtotal, global_discount_percentage, global_discount_amount, total, status, notes, terms_and_conditions)
                    VALUES
                    (:company_id, :user_id, :customer_id, :quotation_number, :quotation_date, :valid_until, :currency,
                     :igv_mode, :payment_condition, :credit_days,
                     :subtotal, :global_discount_percentage, :global_discount_amount, :total, :status, :notes, :terms)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'company_id' => $companyId,
                'user_id' => $userId,
                'customer_id' => $customerId,
                'quotation_number' => $quotationNumber,
                'quotation_date' => $quotationDate,
                'valid_until' => $validUntil,
                'currency' => $currency,
                'igv_mode' => $igvMode,
                'payment_condition' => $paymentCondition,
                'credit_days' => $paymentCondition === 'credit' ? $creditDays : null,
                'subtotal' => $totals['subtotal'],
                'global_discount_percentage' => $globalDiscountPercentage,
                'global_discount_amount' => $totals['global_discount_amount'],
                'total' => $finalTotal,
                'status' => $status,
                'notes' => $notes,
                'terms' => $terms
            ]);

            $quotationId = $this->db->lastInsertId();

            // Insert items
            $itemSql = "INSERT INTO quotation_items
                       (quotation_id, product_id, description, image_url, quantity, unit_price, discount_percentage, line_total, discount_amount)
                       VALUES (:quotation_id, :product_id, :description, :image_url, :quantity, :unit_price, :discount_percentage, :line_total, :discount_amount)";

            $itemStmt = $this->db->prepare($itemSql);
            foreach ($totals['items'] as $item) {
                $itemStmt->execute([
                    'quotation_id' => $quotationId,
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'],
                    'image_url' => $item['image_url'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percentage' => $item['discount_percentage'],
                    'line_total' => $item['line_total'],
                    'discount_amount' => $item['discount_amount']
                ]);
            }

            $this->db->commit();
            return $quotationId;
        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Error creating quotation: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get count of quotations for reporting
     */
    public function getCount($companyId, $startDate = null, $endDate = null, $sellerId = null) {
        try {
            $sql = "SELECT COUNT(*) FROM quotations WHERE company_id = :company_id";
            $params = ['company_id' => $companyId];

            if ($startDate && $endDate) {
                $sql .= " AND DATE(quotation_date) BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            }

            if ($sellerId) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $sellerId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting quotation count: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get count of quotations by status
     */
    public function getCountByStatus($companyId, $startDate = null, $endDate = null, $sellerId = null) {
        try {
            $sql = "SELECT status, COUNT(*) as count FROM quotations WHERE company_id = :company_id";
            $params = ['company_id' => $companyId];

            if ($startDate && $endDate) {
                $sql .= " AND DATE(quotation_date) BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            }

            if ($sellerId) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $sellerId;
            }

            $sql .= " GROUP BY status";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting quotation count by status: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total amount of quotations
     */
    public function getTotalAmount($companyId, $startDate = null, $endDate = null, $sellerId = null) {
        try {
            $sql = "SELECT COALESCE(SUM(total), 0) as total FROM quotations WHERE company_id = :company_id";
            $params = ['company_id' => $companyId];

            if ($startDate && $endDate) {
                $sql .= " AND DATE(quotation_date) BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            }

            if ($sellerId) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $sellerId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (float)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting total amount: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get average amount of quotations
     */
    public function getAverageAmount($companyId, $startDate = null, $endDate = null, $sellerId = null) {
        try {
            $sql = "SELECT COALESCE(AVG(total), 0) as average FROM quotations WHERE company_id = :company_id";
            $params = ['company_id' => $companyId];

            if ($startDate && $endDate) {
                $sql .= " AND DATE(quotation_date) BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            }

            if ($sellerId) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $sellerId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (float)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting average amount: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get count of quotations by month
     */
    public function getCountByMonth($companyId, $startDate = null, $endDate = null, $sellerId = null) {
        try {
            $sql = "SELECT
                        DATE_FORMAT(quotation_date, '%Y-%m') as month,
                        COUNT(*) as count,
                        SUM(total) as total_amount
                    FROM quotations
                    WHERE company_id = :company_id";
            $params = ['company_id' => $companyId];

            if ($startDate && $endDate) {
                $sql .= " AND DATE(quotation_date) BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            }

            if ($sellerId) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $sellerId;
            }

            $sql .= " GROUP BY month ORDER BY month";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting count by month: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get quotations by user
     */
    public function getCountByUser($companyId, $startDate = null, $endDate = null, $sellerId = null) {
        try {
            $sql = "SELECT
                        u.username,
                        u.first_name,
                        u.last_name,
                        COUNT(*) as count,
                        SUM(q.total) as total_amount
                    FROM quotations q
                    JOIN users u ON q.user_id = u.id
                    WHERE q.company_id = :company_id";
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

            $sql .= " GROUP BY u.id ORDER BY count DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting count by user: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get conversion rate (Accepted / Total) as percentage
     */
    public function getConversionRate($companyId, $startDate = null, $endDate = null, $sellerId = null) {
        try {
            $sql = "SELECT
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'Accepted' THEN 1 ELSE 0 END) as accepted
                    FROM quotations
                    WHERE company_id = :company_id";
            $params = ['company_id' => $companyId];

            if ($startDate && $endDate) {
                $sql .= " AND DATE(quotation_date) BETWEEN :start_date AND :end_date";
                $params['start_date'] = $startDate;
                $params['end_date'] = $endDate;
            }

            if ($sellerId) {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $sellerId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['total'] > 0) {
                return round(($result['accepted'] / $result['total']) * 100, 2);
            }
            return 0;
        } catch (PDOException $e) {
            error_log("Error getting conversion rate: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get recent quotations
     */
    public function getRecent($companyId, $limit = 10, $startDate = null, $endDate = null, $sellerId = null) {
        try {
            $sql = "SELECT
                        q.id,
                        q.quotation_number,
                        q.quotation_date,
                        q.status,
                        q.total,
                        q.currency,
                        c.name as customer_name,
                        u.first_name,
                        u.last_name
                    FROM quotations q
                    LEFT JOIN customers c ON q.customer_id = c.id
                    LEFT JOIN users u ON q.user_id = u.id
                    WHERE q.company_id = :company_id";
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

            $sql .= " ORDER BY q.quotation_date DESC LIMIT :limit";

            $stmt = $this->db->prepare($sql);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting recent quotations: " . $e->getMessage());
            return [];
        }
    }
}