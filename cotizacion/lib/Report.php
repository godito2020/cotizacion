<?php
// cotizacion/lib/Report.php

class Report {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    /**
     * Get a summary of quotations over a date range.
     *
     * @param int $company_id
     * @param string $date_from (YYYY-MM-DD)
     * @param string $date_to (YYYY-MM-DD)
     * @param string|null $status Optional status to filter by.
     * @return array
     */
    public function getQuotationsSummary(int $company_id, string $date_from, string $date_to, ?string $status = null): array {
        try {
            $sql = "SELECT
                        DATE(quotation_date) as date,
                        COUNT(*) as count,
                        SUM(total) as total_value
                    FROM quotations
                    WHERE company_id = :company_id
                      AND quotation_date BETWEEN :date_from AND :date_to";

            if ($status !== null && $status !== '') {
                $sql .= " AND status = :status";
            }

            $sql .= " GROUP BY DATE(quotation_date) ORDER BY DATE(quotation_date) ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $stmt->bindParam(':date_from', $date_from);
            $stmt->bindParam(':date_to', $date_to);
            if ($status !== null && $status !== '') {
                $stmt->bindParam(':status', $status);
            }
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Report::getQuotationsSummary Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get sales aggregated by customer.
     *
     * @param int $company_id
     * @param string $date_from (YYYY-MM-DD)
     * @param string $date_to (YYYY-MM-DD)
     * @param array $statuses Array of quotation statuses to consider as sales.
     * @return array
     */
    public function getSalesByCustomer(int $company_id, string $date_from, string $date_to, array $statuses = ['Aceptada', 'Facturada']): array {
        if (empty($statuses)) return [];
        try {
            $status_placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $sql = "SELECT
                        c.name as customer_name,
                        SUM(q.total) as total_sales,
                        COUNT(q.id) as quotation_count
                    FROM quotations q
                    JOIN customers c ON q.customer_id = c.id
                    WHERE q.company_id = ?
                      AND q.quotation_date BETWEEN ? AND ?
                      AND q.status IN ($status_placeholders)
                    GROUP BY c.id, c.name
                    ORDER BY total_sales DESC";

            $params = array_merge([$company_id, $date_from, $date_to], $statuses);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Report::getSalesByCustomer Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get sales aggregated by product.
     *
     * @param int $company_id
     * @param string $date_from (YYYY-MM-DD)
     * @param string $date_to (YYYY-MM-DD)
     * @param array $statuses Array of quotation statuses to include items from.
     * @return array
     */
    public function getSalesByProduct(int $company_id, string $date_from, string $date_to, array $statuses = ['Aceptada', 'Facturada']): array {
        if (empty($statuses)) return [];
        try {
            $status_placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $sql = "SELECT
                        p.name as product_name,
                        p.sku as product_sku,
                        SUM(qi.quantity) as total_quantity_sold,
                        SUM(qi.line_total) as total_value_sold
                    FROM quotation_items qi
                    JOIN quotations q ON qi.quotation_id = q.id
                    JOIN products p ON qi.product_id = p.id
                    WHERE q.company_id = ?
                      AND q.quotation_date BETWEEN ? AND ?
                      AND q.status IN ($status_placeholders)
                      AND qi.product_id IS NOT NULL
                    GROUP BY p.id, p.name, p.sku
                    ORDER BY total_value_sold DESC";

            $params = array_merge([$company_id, $date_from, $date_to], $statuses);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Report::getSalesByProduct Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get sales aggregated by salesperson.
     *
     * @param int $company_id
     * @param string $date_from (YYYY-MM-DD)
     * @param string $date_to (YYYY-MM-DD)
     * @param array $statuses Array of quotation statuses to consider as sales.
     * @return array
     */
    public function getSalesBySalesperson(int $company_id, string $date_from, string $date_to, array $statuses = ['Aceptada', 'Facturada']): array {
        if (empty($statuses)) return [];
        try {
            $status_placeholders = implode(',', array_fill(0, count($statuses), '?'));
            $sql = "SELECT
                        CONCAT(u.first_name, ' ', u.last_name) as salesperson_name,
                        u.username as salesperson_username,
                        SUM(q.total) as total_sales,
                        COUNT(q.id) as quotation_count
                    FROM quotations q
                    JOIN users u ON q.user_id = u.id
                    WHERE q.company_id = ?
                      AND q.quotation_date BETWEEN ? AND ?
                      AND q.status IN ($status_placeholders)
                    GROUP BY u.id, u.username, salesperson_name
                    ORDER BY total_sales DESC";

            $params = array_merge([$company_id, $date_from, $date_to], $statuses);
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Report::getSalesBySalesperson Error: " . $e->getMessage());
            return [];
        }
    }
}
?>
