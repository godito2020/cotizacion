<?php
/**
 * InventoryReports Class
 * Genera reportes y estadísticas de inventario
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InventoryReports {
    private $db;
    private $dbCobol;

    public function __construct() {
        $this->db = getDBConnection();
        $this->dbCobol = getCobolConnection();
    }

    /**
     * Obtiene resumen general de una sesión
     */
    public function getSessionSummary(int $sessionId): array {
        try {
            $sql = "SELECT
                        s.id,
                        s.name,
                        s.status,
                        s.opened_at,
                        s.closed_at,
                        creator.username AS created_by,
                        closer.username AS closed_by,
                        COUNT(DISTINCT e.id) AS total_entries,
                        COUNT(DISTINCT e.user_id) AS total_users,
                        COUNT(DISTINCT e.product_code) AS total_products,
                        COUNT(DISTINCT e.warehouse_number) AS total_warehouses,
                        SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) AS matching_count,
                        SUM(CASE WHEN e.difference < 0 THEN 1 ELSE 0 END) AS faltantes_count,
                        SUM(CASE WHEN e.difference > 0 THEN 1 ELSE 0 END) AS sobrantes_count,
                        MIN(e.created_at) AS first_entry_at,
                        MAX(e.created_at) AS last_entry_at
                    FROM inventory_sessions s
                    LEFT JOIN users creator ON s.created_by = creator.id
                    LEFT JOIN users closer ON s.closed_by = closer.id
                    LEFT JOIN inventory_entries e ON s.id = e.session_id
                    WHERE s.id = :session_id
                    GROUP BY s.id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (PDOException $e) {
            error_log("InventoryReports::getSessionSummary Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene progreso por almacén
     */
    public function getProgressByWarehouse(int $sessionId): array {
        try {
            $sql = "SELECT
                        sw.warehouse_number,
                        sw.warehouse_name,
                        COUNT(DISTINCT e.product_code) AS counted_products,
                        COUNT(e.id) AS total_entries,
                        SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) AS matching,
                        SUM(CASE WHEN e.difference < 0 THEN 1 ELSE 0 END) AS faltantes,
                        SUM(CASE WHEN e.difference > 0 THEN 1 ELSE 0 END) AS sobrantes
                    FROM inventory_session_warehouses sw
                    LEFT JOIN inventory_entries e ON sw.session_id = e.session_id
                        AND sw.warehouse_number = e.warehouse_number
                    WHERE sw.session_id = :session_id
                    GROUP BY sw.warehouse_number, sw.warehouse_name
                    ORDER BY sw.warehouse_name";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryReports::getProgressByWarehouse Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene estadísticas de todos los usuarios
     */
    public function getAllUserStats(int $sessionId): array {
        try {
            $sql = "SELECT
                        e.user_id,
                        u.username,
                        CONCAT(u.first_name, ' ', u.last_name) AS full_name,
                        COUNT(*) AS total_entries,
                        COUNT(DISTINCT e.product_code) AS unique_products,
                        SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) AS matching_count,
                        SUM(CASE WHEN e.difference != 0 THEN 1 ELSE 0 END) AS discrepancy_count,
                        ROUND(SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS accuracy_percentage,
                        MIN(e.created_at) AS first_entry_at,
                        MAX(e.created_at) AS last_entry_at,
                        TIMESTAMPDIFF(MINUTE, MIN(e.created_at), MAX(e.created_at)) AS active_minutes
                    FROM inventory_entries e
                    JOIN users u ON e.user_id = u.id
                    WHERE e.session_id = :session_id
                    GROUP BY e.user_id
                    ORDER BY total_entries DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryReports::getAllUserStats Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene estadísticas de un usuario específico
     */
    public function getUserStats(int $sessionId, int $userId): array {
        try {
            $sql = "SELECT
                        COUNT(*) AS total_entries,
                        COUNT(DISTINCT product_code) AS unique_products,
                        SUM(CASE WHEN difference = 0 THEN 1 ELSE 0 END) AS matching_count,
                        SUM(CASE WHEN difference < 0 THEN 1 ELSE 0 END) AS faltantes_count,
                        SUM(CASE WHEN difference > 0 THEN 1 ELSE 0 END) AS sobrantes_count,
                        ROUND(SUM(CASE WHEN difference = 0 THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(*), 0), 2) AS accuracy_percentage,
                        MIN(created_at) AS first_entry_at,
                        MAX(created_at) AS last_entry_at,
                        TIMESTAMPDIFF(MINUTE, MIN(created_at), MAX(created_at)) AS active_minutes
                    FROM inventory_entries
                    WHERE session_id = :session_id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (PDOException $e) {
            error_log("InventoryReports::getUserStats Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene ranking de usuarios por precisión
     */
    public function getUserRanking(int $sessionId): array {
        try {
            $sql = "SELECT
                        e.user_id,
                        u.username,
                        CONCAT(u.first_name, ' ', u.last_name) AS full_name,
                        COUNT(*) AS total_entries,
                        SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) AS matching_count,
                        ROUND(SUM(CASE WHEN e.difference = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS accuracy_percentage,
                        TIMESTAMPDIFF(MINUTE, MIN(e.created_at), MAX(e.created_at)) AS active_minutes,
                        ROUND(COUNT(*) / NULLIF(TIMESTAMPDIFF(MINUTE, MIN(e.created_at), MAX(e.created_at)), 0), 2) AS entries_per_minute
                    FROM inventory_entries e
                    JOIN users u ON e.user_id = u.id
                    WHERE e.session_id = :session_id
                    GROUP BY e.user_id
                    ORDER BY accuracy_percentage DESC, total_entries DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryReports::getUserRanking Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene resumen de discrepancias
     */
    public function getDiscrepancySummary(int $sessionId): array {
        try {
            $sql = "SELECT
                        SUM(CASE WHEN difference = 0 THEN 1 ELSE 0 END) AS matching_count,
                        SUM(CASE WHEN difference < 0 THEN 1 ELSE 0 END) AS faltantes_count,
                        SUM(CASE WHEN difference > 0 THEN 1 ELSE 0 END) AS sobrantes_count,
                        SUM(CASE WHEN difference < 0 THEN ABS(difference) ELSE 0 END) AS total_faltante_qty,
                        SUM(CASE WHEN difference > 0 THEN difference ELSE 0 END) AS total_sobrante_qty
                    FROM inventory_entries
                    WHERE session_id = :session_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        } catch (PDOException $e) {
            error_log("InventoryReports::getDiscrepancySummary Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene datos para tiempo real
     */
    public function getRealTimeData(int $sessionId): array {
        $session = new InventorySession();
        $entry = new InventoryEntry();

        $sessionData = $session->getById($sessionId);
        $stats = $session->getSessionStats($sessionId);
        $byWarehouse = $this->getProgressByWarehouse($sessionId);
        $recentEntries = $entry->getRecentEntries($sessionId, 10);

        // Calcular usuarios activos (con actividad en los últimos 5 minutos)
        $activeUsers = $this->getActiveUsers($sessionId, 5);

        return [
            'session' => [
                'id' => $sessionData['id'] ?? null,
                'name' => $sessionData['name'] ?? '',
                'status' => $sessionData['status'] ?? '',
                'opened_at' => $sessionData['opened_at'] ?? null
            ],
            'progress' => [
                'total_entries' => (int)($stats['total_entries'] ?? 0),
                'total_users' => (int)($stats['total_users'] ?? 0),
                'total_products' => (int)($stats['total_products'] ?? 0)
            ],
            'discrepancies' => [
                'matching' => (int)($stats['matching_count'] ?? 0),
                'faltantes' => (int)($stats['faltantes_count'] ?? 0),
                'sobrantes' => (int)($stats['sobrantes_count'] ?? 0)
            ],
            'by_warehouse' => $byWarehouse,
            'recent_entries' => array_map(function($e) {
                return [
                    'id' => $e['id'],
                    'username' => $e['username'],
                    'product_code' => $e['product_code'],
                    'product_description' => $e['product_description'],
                    'difference' => (float)$e['difference'],
                    'created_at' => $e['created_at']
                ];
            }, $recentEntries),
            'active_users' => count($activeUsers)
        ];
    }

    /**
     * Obtiene usuarios activos en los últimos N minutos
     */
    private function getActiveUsers(int $sessionId, int $minutes = 5): array {
        try {
            $sql = "SELECT DISTINCT e.user_id, u.username
                    FROM inventory_entries e
                    JOIN users u ON e.user_id = u.id
                    WHERE e.session_id = :session_id
                    AND e.created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':minutes' => $minutes
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("InventoryReports::getActiveUsers Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Exporta inventario a Excel
     */
    public function exportToExcel(int $sessionId, string $type = 'complete', ?int $userId = null): string {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Obtener datos de sesión
        $session = new InventorySession();
        $sessionData = $session->getById($sessionId);
        $sessionName = $sessionData['name'] ?? 'Inventario';

        // Estilos comunes
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];

        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];

        // Título
        $sheet->setCellValue('A1', 'INVENTARIO FÍSICO');
        $sheet->setCellValue('A2', 'Sesión: ' . $sessionName);
        $sheet->setCellValue('A3', 'Fecha de exportación: ' . date('d/m/Y H:i'));
        $sheet->mergeCells('A1:J1');
        $sheet->mergeCells('A2:J2');
        $sheet->mergeCells('A3:J3');
        $sheet->getStyle('A1')->applyFromArray($titleStyle);

        // Según el tipo de reporte
        switch ($type) {
            case 'user':
                $this->addUserEntriesSheet($sheet, $sessionId, $userId, $headerStyle);
                $filename = "Inventario_Usuario_{$userId}_" . date('Y-m-d_His') . ".xlsx";
                break;

            case 'discrepancies':
                $this->addDiscrepanciesSheet($sheet, $sessionId, $headerStyle);
                $filename = "Inventario_Discrepancias_" . date('Y-m-d_His') . ".xlsx";
                break;

            case 'matching':
                $this->addMatchingSheet($sheet, $sessionId, $headerStyle);
                $filename = "Inventario_Coincidentes_" . date('Y-m-d_His') . ".xlsx";
                break;

            case 'summary':
                $this->addSummarySheet($sheet, $sessionId, $headerStyle);
                $filename = "Inventario_Resumen_" . date('Y-m-d_His') . ".xlsx";
                break;

            default: // complete
                $this->addCompleteSheet($sheet, $sessionId, $headerStyle);
                $filename = "Inventario_Completo_" . date('Y-m-d_His') . ".xlsx";
        }

        // Auto-size columnas
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Guardar archivo temporal
        $tempDir = sys_get_temp_dir();
        $filePath = $tempDir . DIRECTORY_SEPARATOR . $filename;

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        return $filePath;
    }

    /**
     * Agrega hoja con entradas de un usuario
     */
    private function addUserEntriesSheet($sheet, int $sessionId, int $userId, array $headerStyle): void {
        $entry = new InventoryEntry();
        $entries = $entry->getByUser($sessionId, $userId, 1, 10000);

        $sheet->setTitle('Registros');

        $row = 5;
        $headers = ['Código', 'Descripción', 'Almacén', 'Zona', 'Stock Sistema', 'Cantidad Contada', 'Diferencia', 'Comentarios', 'Fecha/Hora'];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $header);
        }
        $sheet->getStyle("A{$row}:I{$row}")->applyFromArray($headerStyle);

        $row++;
        foreach ($entries as $e) {
            $sheet->setCellValueByColumnAndRow(1, $row, $e['product_code']);
            $sheet->setCellValueByColumnAndRow(2, $row, $e['product_description']);
            $sheet->setCellValueByColumnAndRow(3, $row, $e['warehouse_number']);
            $sheet->setCellValueByColumnAndRow(4, $row, $e['zone_name'] ?? '-');
            $sheet->setCellValueByColumnAndRow(5, $row, $e['system_stock']);
            $sheet->setCellValueByColumnAndRow(6, $row, $e['counted_quantity']);
            $sheet->setCellValueByColumnAndRow(7, $row, $e['difference']);
            $sheet->setCellValueByColumnAndRow(8, $row, $e['comments']);
            $sheet->setCellValueByColumnAndRow(9, $row, $e['created_at']);
            $row++;
        }

        // Agregar segunda hoja con productos múltiples
        $this->addMultipleEntriesSheet($sheet->getParent(), $sessionId, $userId, $headerStyle);
    }

    /**
     * Agrega hoja con productos ingresados más de una vez (resumen tipo tabla dinámica)
     */
    private function addMultipleEntriesSheet($spreadsheet, int $sessionId, int $userId, array $headerStyle): void {
        // Obtener productos con múltiples entradas
        $multipleEntries = $this->getProductsWithMultipleEntries($sessionId, $userId);

        if (empty($multipleEntries)) {
            return; // No hay productos con múltiples entradas
        }

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Múltiples Conteos');

        // Título
        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];

        $sheet2->setCellValue('A1', 'PRODUCTOS CON MÚLTIPLES CONTEOS');
        $sheet2->setCellValue('A2', 'Artículos inventariados en diferentes ubicaciones');
        $sheet2->mergeCells('A1:F1');
        $sheet2->mergeCells('A2:F2');
        $sheet2->getStyle('A1')->applyFromArray($titleStyle);

        $row = 4;
        $headers = ['Código', 'Descripción', 'Stock Sistema', 'Conteos Individuales', 'Total Contado', 'Diferencia Total'];

        foreach ($headers as $col => $header) {
            $sheet2->setCellValueByColumnAndRow($col + 1, $row, $header);
        }
        $sheet2->getStyle("A{$row}:F{$row}")->applyFromArray($headerStyle);

        $row++;
        foreach ($multipleEntries as $product) {
            // Formatear conteos individuales
            $conteos = implode(' + ', array_column($product['entries'], 'counted_quantity'));

            $sheet2->setCellValueByColumnAndRow(1, $row, $product['product_code']);
            $sheet2->setCellValueByColumnAndRow(2, $row, $product['product_description']);
            $sheet2->setCellValueByColumnAndRow(3, $row, $product['system_stock']);
            $sheet2->setCellValueByColumnAndRow(4, $row, $conteos);
            $sheet2->setCellValueByColumnAndRow(5, $row, $product['total_counted']);
            $sheet2->setCellValueByColumnAndRow(6, $row, $product['total_counted'] - $product['system_stock']);

            // Colorear diferencia
            $diff = $product['total_counted'] - $product['system_stock'];
            if ($diff == 0) {
                $sheet2->getStyle("F{$row}")->getFont()->getColor()->setRGB('00AA00');
            } elseif ($diff < 0) {
                $sheet2->getStyle("F{$row}")->getFont()->getColor()->setRGB('FF0000');
            } else {
                $sheet2->getStyle("F{$row}")->getFont()->getColor()->setRGB('FF8800');
            }

            $row++;
        }

        // Auto-size columnas
        foreach (range('A', 'F') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Obtiene productos con múltiples entradas de un usuario
     */
    private function getProductsWithMultipleEntries(int $sessionId, int $userId): array {
        try {
            // Primero obtener los códigos de productos con más de una entrada
            $sql = "SELECT
                        product_code,
                        product_description,
                        system_stock,
                        COUNT(*) as entry_count,
                        SUM(counted_quantity) as total_counted
                    FROM inventory_entries
                    WHERE session_id = :session_id AND user_id = :user_id
                    GROUP BY product_code, product_description, system_stock
                    HAVING COUNT(*) > 1
                    ORDER BY product_code";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':session_id' => $sessionId,
                ':user_id' => $userId
            ]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Para cada producto, obtener los conteos individuales
            foreach ($products as &$product) {
                $sqlEntries = "SELECT counted_quantity, comments, created_at
                               FROM inventory_entries
                               WHERE session_id = :session_id
                               AND user_id = :user_id
                               AND product_code = :product_code
                               ORDER BY created_at ASC";

                $stmtEntries = $this->db->prepare($sqlEntries);
                $stmtEntries->execute([
                    ':session_id' => $sessionId,
                    ':user_id' => $userId,
                    ':product_code' => $product['product_code']
                ]);
                $product['entries'] = $stmtEntries->fetchAll(PDO::FETCH_ASSOC);
            }

            return $products;

        } catch (PDOException $e) {
            error_log("InventoryReports::getProductsWithMultipleEntries Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene productos con múltiples entradas de TODA la sesión (todos los usuarios)
     */
    public function getAllProductsWithMultipleEntries(int $sessionId): array {
        try {
            $sql = "SELECT
                        product_code,
                        product_description,
                        warehouse_number,
                        system_stock,
                        COUNT(*) as entry_count,
                        SUM(counted_quantity) as total_counted,
                        GROUP_CONCAT(DISTINCT u.username SEPARATOR ', ') as users,
                        GROUP_CONCAT(DISTINCT z.name SEPARATOR ', ') as zones
                    FROM inventory_entries e
                    JOIN users u ON e.user_id = u.id
                    LEFT JOIN inventory_zones z ON e.zone_id = z.id
                    WHERE e.session_id = :session_id
                    GROUP BY product_code, product_description, warehouse_number, system_stock
                    HAVING COUNT(*) > 1
                    ORDER BY entry_count DESC, product_code";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':session_id' => $sessionId]);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Para cada producto, obtener los conteos individuales
            foreach ($products as &$product) {
                $sqlEntries = "SELECT e.counted_quantity, e.comments, e.created_at, u.username,
                                      z.name as zone_name
                               FROM inventory_entries e
                               JOIN users u ON e.user_id = u.id
                               LEFT JOIN inventory_zones z ON e.zone_id = z.id
                               WHERE e.session_id = :session_id
                               AND e.product_code = :product_code
                               AND e.warehouse_number = :warehouse_number
                               ORDER BY e.created_at ASC";

                $stmtEntries = $this->db->prepare($sqlEntries);
                $stmtEntries->execute([
                    ':session_id' => $sessionId,
                    ':product_code' => $product['product_code'],
                    ':warehouse_number' => $product['warehouse_number']
                ]);
                $product['entries'] = $stmtEntries->fetchAll(PDO::FETCH_ASSOC);
                $product['difference'] = (float)$product['total_counted'] - (float)$product['system_stock'];
            }

            return $products;

        } catch (PDOException $e) {
            error_log("InventoryReports::getAllProductsWithMultipleEntries Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Agrega hoja de múltiples conteos para reportes admin (todos los usuarios)
     */
    private function addAllMultipleEntriesSheet($spreadsheet, int $sessionId, array $headerStyle): void {
        $multipleEntries = $this->getAllProductsWithMultipleEntries($sessionId);

        if (empty($multipleEntries)) {
            return;
        }

        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Múltiples Conteos');

        $titleStyle = [
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];

        $sheet2->setCellValue('A1', 'PRODUCTOS CON MÚLTIPLES CONTEOS');
        $sheet2->setCellValue('A2', 'Artículos contados más de una vez (suma de todos los conteos)');
        $sheet2->mergeCells('A1:H1');
        $sheet2->mergeCells('A2:H2');
        $sheet2->getStyle('A1')->applyFromArray($titleStyle);

        $row = 4;
        $headers = ['Código', 'Descripción', 'Almacén', 'Zonas', 'Stock Sistema', 'Conteos', 'Total Contado', 'Diferencia'];

        foreach ($headers as $col => $header) {
            $sheet2->setCellValueByColumnAndRow($col + 1, $row, $header);
        }
        $sheet2->getStyle("A{$row}:H{$row}")->applyFromArray($headerStyle);

        $row++;
        foreach ($multipleEntries as $product) {
            $conteos = implode(' + ', array_column($product['entries'], 'counted_quantity'));

            $sheet2->setCellValueByColumnAndRow(1, $row, $product['product_code']);
            $sheet2->setCellValueByColumnAndRow(2, $row, $product['product_description']);
            $sheet2->setCellValueByColumnAndRow(3, $row, $product['warehouse_number']);
            $sheet2->setCellValueByColumnAndRow(4, $row, $product['zones'] ?? '-');
            $sheet2->setCellValueByColumnAndRow(5, $row, $product['system_stock']);
            $sheet2->setCellValueByColumnAndRow(6, $row, $conteos . " ({$product['entry_count']}x)");
            $sheet2->setCellValueByColumnAndRow(7, $row, $product['total_counted']);
            $sheet2->setCellValueByColumnAndRow(8, $row, $product['difference']);

            // Colorear diferencia
            if ($product['difference'] == 0) {
                $sheet2->getStyle("H{$row}")->getFont()->getColor()->setRGB('00AA00');
            } elseif ($product['difference'] < 0) {
                $sheet2->getStyle("H{$row}")->getFont()->getColor()->setRGB('FF0000');
            } else {
                $sheet2->getStyle("H{$row}")->getFont()->getColor()->setRGB('FF8800');
            }

            $row++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet2->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Agrega hoja con discrepancias
     */
    private function addDiscrepanciesSheet($sheet, int $sessionId, array $headerStyle): void {
        $entry = new InventoryEntry();
        $entries = $entry->getDiscrepancies($sessionId, 'all', 1, 10000);

        $sheet->setTitle('Discrepancias');

        $row = 5;
        $headers = ['Código', 'Descripción', 'Almacén', 'Zona', 'Stock Sistema', 'Cantidad Contada', 'Diferencia', 'Usuario', 'Fecha/Hora'];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $header);
        }
        $sheet->getStyle("A{$row}:I{$row}")->applyFromArray($headerStyle);

        $row++;
        foreach ($entries as $e) {
            $sheet->setCellValueByColumnAndRow(1, $row, $e['product_code']);
            $sheet->setCellValueByColumnAndRow(2, $row, $e['product_description']);
            $sheet->setCellValueByColumnAndRow(3, $row, $e['warehouse_number']);
            $sheet->setCellValueByColumnAndRow(4, $row, $e['zone_name'] ?? '-');
            $sheet->setCellValueByColumnAndRow(5, $row, $e['system_stock']);
            $sheet->setCellValueByColumnAndRow(6, $row, $e['counted_quantity']);
            $sheet->setCellValueByColumnAndRow(7, $row, $e['difference']);
            $sheet->setCellValueByColumnAndRow(8, $row, $e['username']);
            $sheet->setCellValueByColumnAndRow(9, $row, $e['created_at']);
            $row++;
        }

        // Agregar hoja de múltiples conteos
        $this->addAllMultipleEntriesSheet($sheet->getParent(), $sessionId, $headerStyle);
    }

    /**
     * Agrega hoja con productos coincidentes
     */
    private function addMatchingSheet($sheet, int $sessionId, array $headerStyle): void {
        $entry = new InventoryEntry();
        $entries = $entry->getMatching($sessionId, 1, 10000);

        $sheet->setTitle('Coincidentes');

        $row = 5;
        $headers = ['Código', 'Descripción', 'Almacén', 'Zona', 'Stock Sistema', 'Cantidad Contada', 'Usuario', 'Fecha/Hora'];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $header);
        }
        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray($headerStyle);

        $row++;
        foreach ($entries as $e) {
            $sheet->setCellValueByColumnAndRow(1, $row, $e['product_code']);
            $sheet->setCellValueByColumnAndRow(2, $row, $e['product_description']);
            $sheet->setCellValueByColumnAndRow(3, $row, $e['warehouse_number']);
            $sheet->setCellValueByColumnAndRow(4, $row, $e['zone_name'] ?? '-');
            $sheet->setCellValueByColumnAndRow(5, $row, $e['system_stock']);
            $sheet->setCellValueByColumnAndRow(6, $row, $e['counted_quantity']);
            $sheet->setCellValueByColumnAndRow(7, $row, $e['username']);
            $sheet->setCellValueByColumnAndRow(8, $row, $e['created_at']);
            $row++;
        }

        // Agregar hoja de múltiples conteos
        $this->addAllMultipleEntriesSheet($sheet->getParent(), $sessionId, $headerStyle);
    }

    /**
     * Agrega hoja con resumen
     */
    private function addSummarySheet($sheet, int $sessionId, array $headerStyle): void {
        $summary = $this->getSessionSummary($sessionId);
        $userStats = $this->getAllUserStats($sessionId);

        $row = 5;

        // Resumen general
        $sheet->setCellValue("A{$row}", 'RESUMEN GENERAL');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $sheet->setCellValue("A{$row}", 'Total de registros:');
        $sheet->setCellValue("B{$row}", $summary['total_entries'] ?? 0);
        $row++;

        $sheet->setCellValue("A{$row}", 'Productos contados:');
        $sheet->setCellValue("B{$row}", $summary['total_products'] ?? 0);
        $row++;

        $sheet->setCellValue("A{$row}", 'Usuarios participantes:');
        $sheet->setCellValue("B{$row}", $summary['total_users'] ?? 0);
        $row++;

        $sheet->setCellValue("A{$row}", 'Coincidentes:');
        $sheet->setCellValue("B{$row}", $summary['matching_count'] ?? 0);
        $row++;

        $sheet->setCellValue("A{$row}", 'Faltantes:');
        $sheet->setCellValue("B{$row}", $summary['faltantes_count'] ?? 0);
        $row++;

        $sheet->setCellValue("A{$row}", 'Sobrantes:');
        $sheet->setCellValue("B{$row}", $summary['sobrantes_count'] ?? 0);
        $row += 2;

        // Estadísticas por usuario
        $sheet->setCellValue("A{$row}", 'ESTADÍSTICAS POR USUARIO');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $headers = ['Usuario', 'Registros', 'Productos', 'Coincidentes', 'Discrepancias', 'Precisión %', 'Tiempo (min)'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $header);
        }
        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray($headerStyle);
        $row++;

        foreach ($userStats as $user) {
            $sheet->setCellValueByColumnAndRow(1, $row, $user['username']);
            $sheet->setCellValueByColumnAndRow(2, $row, $user['total_entries']);
            $sheet->setCellValueByColumnAndRow(3, $row, $user['unique_products']);
            $sheet->setCellValueByColumnAndRow(4, $row, $user['matching_count']);
            $sheet->setCellValueByColumnAndRow(5, $row, $user['discrepancy_count']);
            $sheet->setCellValueByColumnAndRow(6, $row, $user['accuracy_percentage'] . '%');
            $sheet->setCellValueByColumnAndRow(7, $row, $user['active_minutes']);
            $row++;
        }
    }

    /**
     * Agrega hoja completa
     */
    private function addCompleteSheet($sheet, int $sessionId, array $headerStyle): void {
        $entry = new InventoryEntry();
        $entries = $entry->getBySession($sessionId, 1, 10000);

        $row = 5;
        $headers = ['Código', 'Descripción', 'Almacén', 'Zona', 'Stock Sistema', 'Cantidad Contada', 'Diferencia', 'Estado', 'Usuario', 'Fecha/Hora'];

        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, $row, $header);
        }
        $sheet->getStyle("A{$row}:J{$row}")->applyFromArray($headerStyle);

        $row++;
        foreach ($entries as $e) {
            $diff = (float)$e['difference'];
            $status = $diff == 0 ? 'Coincide' : ($diff < 0 ? 'Faltante' : 'Sobrante');

            $sheet->setCellValueByColumnAndRow(1, $row, $e['product_code']);
            $sheet->setCellValueByColumnAndRow(2, $row, $e['product_description']);
            $sheet->setCellValueByColumnAndRow(3, $row, $e['warehouse_number']);
            $sheet->setCellValueByColumnAndRow(4, $row, $e['zone_name'] ?? '-');
            $sheet->setCellValueByColumnAndRow(5, $row, $e['system_stock']);
            $sheet->setCellValueByColumnAndRow(6, $row, $e['counted_quantity']);
            $sheet->setCellValueByColumnAndRow(7, $row, $e['difference']);
            $sheet->setCellValueByColumnAndRow(8, $row, $status);
            $sheet->setCellValueByColumnAndRow(9, $row, $e['username']);
            $sheet->setCellValueByColumnAndRow(10, $row, $e['created_at']);
            $row++;
        }

        // Agregar hoja de múltiples conteos
        $this->addAllMultipleEntriesSheet($sheet->getParent(), $sessionId, $headerStyle);
    }

    /**
     * Descarga el archivo Excel
     */
    public function downloadExcel(string $filePath, string $filename): void {
        if (!file_exists($filePath)) {
            throw new Exception('Archivo no encontrado');
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: max-age=0');

        readfile($filePath);
        unlink($filePath);
        exit;
    }
}
