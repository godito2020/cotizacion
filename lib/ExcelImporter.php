<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class ExcelImporter {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function importProducts($filePath, $companyId, $currency = 'USD') {
        try {
            // Memory optimizations

            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(true); // Allow empty cells

            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Get the highest row number that contains data
            $highestRow = $worksheet->getHighestDataRow();
            $highestColumn = $worksheet->getHighestDataColumn();

            // Limit to prevent memory issues (increased to 5000)
            if ($highestRow > 5000) {
                return [
                    'success' => false,
                    'message' => 'File too large. Maximum 5000 rows allowed. Found: ' . ($highestRow - 1) . ' data rows.'
                ];
            }

            // Read headers first
            $headerRange = 'A1:' . $highestColumn . '1';
            $headers = $worksheet->rangeToArray($headerRange)[0];

            // Map column headers to expected fields
            $columnMap = $this->mapColumns($headers);

            if (!$columnMap) {
                return [
                    'success' => false,
                    'message' => 'Required columns not found in Excel file'
                ];
            }

            $imported = 0;
            $updated = 0;
            $errors = [];
            $batchSize = 50; // Process in batches to save memory

            // Process rows in batches
            for ($startRow = 2; $startRow <= $highestRow; $startRow += $batchSize) {
                $endRow = min($startRow + $batchSize - 1, $highestRow);
                $range = 'A' . $startRow . ':' . $highestColumn . $endRow;
                $batchRows = $worksheet->rangeToArray($range);

                foreach ($batchRows as $batchIndex => $row) {
                    $rowNumber = $startRow + $batchIndex;

                    try {
                        $productData = $this->mapRowToProduct($row, $columnMap);

                        if (empty($productData['code']) || empty($productData['description'])) {
                            $errors[] = "Row $rowNumber: Missing required fields (CODE or DESCRIPTION)";
                            continue;
                        }

                        $result = $this->saveProduct($companyId, $productData, $currency);

                        if ($result['success']) {
                            if ($result['action'] === 'inserted') {
                                $imported++;
                            } else {
                                $updated++;
                            }

                            // If product has balance (SALDO), also update warehouse stock for "GENERAL" warehouse
                            if (isset($productData['balance']) && $productData['balance'] > 0) {
                                $this->saveWarehouseStock($result['id'], $companyId, 'GENERAL', $productData['balance']);
                            }
                        } else {
                            $errors[] = "Row $rowNumber: " . $result['message'];
                        }

                    } catch (Exception $e) {
                        $errors[] = "Row $rowNumber: " . $e->getMessage();
                    }
                }

                // Clear memory after each batch
                unset($batchRows);
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            // Clean up
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return [
                'success' => true,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors,
                'total_rows' => $highestRow - 1
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error reading Excel file: ' . $e->getMessage()
            ];
        }
    }

    public function importWarehouseStock($filePath, $companyId) {
        try {
            // Memory optimizations

            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(true); // Allow empty cells

            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Get the highest row number that contains data
            $highestRow = $worksheet->getHighestDataRow();
            $highestColumn = $worksheet->getHighestDataColumn();

            // Limit to prevent memory issues (increased to 5000)
            if ($highestRow > 5000) {
                return [
                    'success' => false,
                    'message' => 'File too large. Maximum 5000 rows allowed. Found: ' . ($highestRow - 1) . ' data rows.'
                ];
            }

            // Read headers first
            $headerRange = 'A1:' . $highestColumn . '1';
            $headers = $worksheet->rangeToArray($headerRange)[0];

            // Map warehouse columns
            $warehouseColumns = $this->getWarehouseColumns($headers);

            if (empty($warehouseColumns)) {
                return [
                    'success' => false,
                    'message' => 'No warehouse columns found in Excel file'
                ];
            }

            $processed = 0;
            $errors = [];
            $batchSize = 50; // Process in batches to save memory

            // Process rows in batches
            for ($startRow = 2; $startRow <= $highestRow; $startRow += $batchSize) {
                $endRow = min($startRow + $batchSize - 1, $highestRow);
                $range = 'A' . $startRow . ':' . $highestColumn . $endRow;
                $batchRows = $worksheet->rangeToArray($range);

                foreach ($batchRows as $batchIndex => $row) {
                    $rowNumber = $startRow + $batchIndex;

                    try {
                        $articleCode = $row[0] ?? ''; // Assuming first column is Article code

                        if (empty($articleCode)) {
                            $errors[] = "Row $rowNumber: Missing article code";
                            continue;
                        }

                        // Find product by code
                        $product = $this->getProductByCode($companyId, $articleCode);

                        if (!$product) {
                            $errors[] = "Row $rowNumber: Product with code '$articleCode' not found";
                            continue;
                        }

                        // Process warehouse stock for this product
                        foreach ($warehouseColumns as $colIndex => $warehouseName) {
                            $stockQuantity = floatval($row[$colIndex] ?? 0);

                            $this->saveWarehouseStock($product['id'], $companyId, $warehouseName, $stockQuantity);
                        }

                        $processed++;

                    } catch (Exception $e) {
                        $errors[] = "Row $rowNumber: " . $e->getMessage();
                    }
                }

                // Clear memory after each batch
                unset($batchRows);
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }

            // Clean up
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return [
                'success' => true,
                'processed' => $processed,
                'warehouses' => count($warehouseColumns),
                'errors' => $errors,
                'total_rows' => $highestRow - 1
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error reading Excel file: ' . $e->getMessage()
            ];
        }
    }

    private function mapColumns($headers) {
        $expectedColumns = [
            'CODIGO' => 'code',
            'DESCRIPCION' => 'description',
            'MARCA' => 'brand',
            'SALDO' => 'balance',
            'PREMIUM' => 'premium_price',
            'PRECIO' => 'regular_price',
            'ULTCOSTO' => 'last_cost',
            'FECULTCOS' => 'last_cost_date',
            'IMAGEN' => 'image_url',
            'IMAGE_URL' => 'image_url',
            'URL_IMAGEN' => 'image_url',
            'FOTO' => 'image_url',
            'PICTURE' => 'image_url'
        ];

        $columnMap = [];

        foreach ($headers as $index => $header) {
            $header = strtoupper(trim($header));
            if (isset($expectedColumns[$header])) {
                $columnMap[$index] = $expectedColumns[$header];
                // Debug logging for image column mapping
                if ($expectedColumns[$header] === 'image_url') {
                    error_log("ExcelImporter: Found IMAGEN column at index $index");
                }
            }
        }

        // Debug: Log all found headers
        error_log("ExcelImporter: Found headers: " . implode(', ', array_map('strtoupper', array_map('trim', $headers))));
        error_log("ExcelImporter: Mapped columns: " . json_encode($columnMap));

        // Check if required columns exist
        $requiredFields = ['code', 'description'];
        $mappedFields = array_values($columnMap);

        foreach ($requiredFields as $field) {
            if (!in_array($field, $mappedFields)) {
                return false;
            }
        }

        return $columnMap;
    }

    private function getWarehouseColumns($headers) {
        $warehouseColumns = [];
        // Skip these columns - include all variations (case-insensitive)
        $excludeColumns = [
            'ARTICULO', 'Articulo',
            'DESCRIPCION', 'Descripcion', 'DESCRIPTION', 'Description',
            'CODIGO', 'Codigo', 'CODE', 'Code',
            'MARCA', 'Marca', 'BRAND', 'Brand',
            'SALDO', 'Saldo', 'BALANCE', 'Balance',
            'PREMIUM', 'Premium', 'PRECIO', 'Precio', 'PRICE', 'Price',
            'ULTCOSTO', 'UltCosto', 'COST', 'Cost',
            'IMAGEN', 'Imagen', 'IMAGE', 'Image', 'IMAGE_URL', 'URL_IMAGEN',
            'MED', 'Med', 'MEDIDA', 'Medida',
            'TOTAL', 'Total'
        ];

        foreach ($headers as $index => $header) {
            $header = trim($header);

            // Case-insensitive comparison
            if (!in_array($header, $excludeColumns, true) && !empty($header)) {
                // Additional check to exclude known product attribute columns
                $headerUpper = strtoupper($header);
                if (!in_array($headerUpper, array_map('strtoupper', $excludeColumns))) {
                    $warehouseColumns[$index] = $header;
                }
            }
        }

        return $warehouseColumns;
    }

    private function mapRowToProduct($row, $columnMap) {
        $product = [];

        foreach ($columnMap as $colIndex => $fieldName) {
            $value = $row[$colIndex] ?? '';

            switch ($fieldName) {
                case 'code':
                case 'description':
                case 'brand':
                case 'image_url':
                    $product[$fieldName] = trim($value);
                    // Debug logging for image_url
                    if ($fieldName === 'image_url' && !empty(trim($value))) {
                        error_log("ExcelImporter: Found image_url for product: " . ($product['code'] ?? 'unknown') . " -> " . trim($value));
                    }
                    break;

                case 'balance':
                case 'premium_price':
                case 'regular_price':
                case 'last_cost':
                    $product[$fieldName] = floatval($value);
                    break;

                case 'last_cost_date':
                    $product[$fieldName] = $this->parseDate($value);
                    break;
            }
        }

        return $product;
    }

    private function parseDate($dateValue) {
        if (empty($dateValue)) {
            return null;
        }

        // Try to parse Excel date
        if (is_numeric($dateValue)) {
            try {
                $dateTime = Date::excelToDateTimeObject($dateValue);
                return $dateTime->format('Y-m-d');
            } catch (Exception $e) {
                // Fall through to string parsing
            }
        }

        // Try to parse as string
        $timestamp = strtotime($dateValue);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    private function saveProduct($companyId, $productData, $currency = 'USD') {
        try {
            // Debug logging for image_url
            if (!empty($productData['image_url'])) {
                error_log("ExcelImporter: Saving product {$productData['code']} with image_url: {$productData['image_url']}");
            }

            // Check if product exists
            $existingProduct = $this->getProductByCode($companyId, $productData['code']);

            if ($existingProduct) {
                // Update existing product
                $sql = "UPDATE products SET
                        name = ?,
                        description = ?,
                        brand = ?,
                        balance = ?,
                        premium_price = ?,
                        regular_price = ?,
                        last_cost = ?,
                        last_cost_date = ?,
                        image_url = ?,
                        price_currency = ?,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";

                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([
                    $productData['description'], // name
                    $productData['description'], // description
                    $productData['brand'] ?? null,
                    $productData['balance'] ?? 0,
                    $productData['premium_price'] ?? 0,
                    $productData['regular_price'] ?? 0,
                    $productData['last_cost'] ?? 0,
                    $productData['last_cost_date'] ?? null,
                    $productData['image_url'] ?? null,
                    $currency,
                    $existingProduct['id']
                ]);

                if ($result && !empty($productData['image_url'])) {
                    error_log("ExcelImporter: Successfully updated product {$productData['code']} with image_url");
                }

                return ['success' => true, 'action' => 'updated', 'id' => $existingProduct['id']];

            } else {
                // Insert new product
                $sql = "INSERT INTO products
                        (company_id, code, name, description, brand, balance, premium_price,
                         regular_price, last_cost, last_cost_date, image_url, price_currency)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([
                    $companyId,
                    $productData['code'],
                    $productData['description'], // name
                    $productData['description'], // description
                    $productData['brand'] ?? null,
                    $productData['balance'] ?? 0,
                    $productData['premium_price'] ?? 0,
                    $productData['regular_price'] ?? 0,
                    $productData['last_cost'] ?? 0,
                    $productData['last_cost_date'] ?? null,
                    $productData['image_url'] ?? null,
                    $currency
                ]);

                if ($result && !empty($productData['image_url'])) {
                    error_log("ExcelImporter: Successfully inserted product {$productData['code']} with image_url");
                }

                return ['success' => true, 'action' => 'inserted', 'id' => $this->db->lastInsertId()];
            }

        } catch (PDOException $e) {
            error_log("ExcelImporter: Database error saving product: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function getProductByCode($companyId, $code) {
        try {
            $sql = "SELECT * FROM products WHERE company_id = ? AND code = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$companyId, $code]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return null;
        }
    }

    private function saveWarehouseStock($productId, $companyId, $warehouseName, $stockQuantity) {
        try {
            $sql = "INSERT INTO product_warehouse_stock
                    (product_id, company_id, warehouse_name, stock_quantity)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                    stock_quantity = VALUES(stock_quantity),
                    last_updated = CURRENT_TIMESTAMP";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$productId, $companyId, $warehouseName, $stockQuantity]);

            return true;
        } catch (PDOException $e) {
            error_log("Error saving warehouse stock: " . $e->getMessage());
            return false;
        }
    }

    public function getImportHistory($companyId, $limit = 10) {
        // This would require an import_logs table - implement if needed
        return [];
    }

    public function validateExcelFile($filePath) {
        try {
            // Check file size first (limit to 20MB)
            $fileSize = filesize($filePath);
            if ($fileSize > 20 * 1024 * 1024) {
                return [
                    'valid' => false,
                    'error' => 'File too large. Maximum size allowed: 20MB. Current size: ' . round($fileSize / 1024 / 1024, 2) . 'MB'
                ];
            }

            // Memory optimizations

            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(true); // Allow empty cells

            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            // Check row count
            $highestRow = $worksheet->getHighestDataRow();
            if ($highestRow > 5000) {
                $spreadsheet->disconnectWorksheets();
                return [
                    'valid' => false,
                    'error' => 'Too many rows. Maximum 5000 rows allowed. Found: ' . ($highestRow - 1) . ' data rows.'
                ];
            }

            // Get headers efficiently
            $highestColumn = $worksheet->getHighestDataColumn();
            $headerRange = 'A1:' . $highestColumn . '1';
            $headers = $worksheet->rangeToArray($headerRange)[0];

            $requiredColumns = ['CODIGO', 'DESCRIPCION'];
            $foundColumns = [];

            foreach ($headers as $header) {
                if ($header !== null) {
                    $header = strtoupper(trim($header));
                    if (in_array($header, $requiredColumns)) {
                        $foundColumns[] = $header;
                    }
                }
            }

            // Clean up
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return [
                'valid' => count($foundColumns) === count($requiredColumns),
                'found_columns' => $foundColumns,
                'missing_columns' => array_diff($requiredColumns, $foundColumns),
                'all_columns' => array_filter($headers, function($h) { return $h !== null; }),
                'total_rows' => $highestRow - 1,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2)
            ];

        } catch (Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Import products with progress tracking
     */
    public function importProductsWithProgress($filePath, $companyId, $currency = 'USD', $sessionId = null) {
        try {
            // Suppress warnings to prevent JSON interference
            $oldErrorReporting = error_reporting(E_ERROR | E_PARSE);

            // Start session if sessionId provided
            if ($sessionId) {
                session_id($sessionId);
                session_start();
            }

            $this->updateProgress(0, 0, 'Validando archivo Excel...');

            // First validate
            $validation = $this->validateExcelFile($filePath);
            if (!$validation['valid']) {
                $this->updateProgress(0, 0, 'Error: ' . $validation['error'], true);
                return ['success' => false, 'message' => $validation['error']];
            }

            $this->updateProgress(5, $validation['total_rows'], 'Leyendo archivo Excel...');

            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = $worksheet->getHighestDataRow();
            $highestColumn = $worksheet->getHighestDataColumn();

            $this->updateProgress(10, $highestRow - 1, 'Procesando encabezados...');

            // Get headers and map columns
            $headerRange = 'A1:' . $highestColumn . '1';
            $headers = $worksheet->rangeToArray($headerRange)[0];
            $columnMap = $this->mapColumns($headers);

            $this->updateProgress(15, $highestRow - 1, 'Iniciando importación de productos...');

            $processed = 0;
            $inserted = 0;
            $updated = 0;
            $errors = [];

            // Process data rows
            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $rowRange = 'A' . $rowNumber . ':' . $highestColumn . $rowNumber;
                $row = $worksheet->rangeToArray($rowRange)[0];

                $productData = $this->mapRowToProduct($row, $columnMap);

                if (empty($productData['code'])) {
                    $errors[] = "Row $rowNumber: Missing product code";
                    continue;
                }

                $result = $this->saveProduct($companyId, $productData, $currency);

                if ($result['success']) {
                    if ($result['action'] === 'inserted') {
                        $inserted++;
                    } else {
                        $updated++;
                    }
                }

                $processed++;

                // Update progress every 10 rows
                if ($processed % 10 === 0 || $rowNumber === $highestRow) {
                    $progress = 15 + (($processed / ($highestRow - 1)) * 80); // 15% to 95%
                    $this->updateProgress($progress, $highestRow - 1, "Procesando productos... ($processed/" . ($highestRow - 1) . ")", false, $processed);
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $this->updateProgress(100, $highestRow - 1, 'Importación completada', true, $processed);

            // Restore error reporting
            error_reporting($oldErrorReporting);

            return [
                'success' => true,
                'processed' => $processed,
                'inserted' => $inserted,
                'updated' => $updated,
                'errors' => $errors,
                'total_rows' => $highestRow - 1
            ];

        } catch (Exception $e) {
            // Restore error reporting
            if (isset($oldErrorReporting)) {
                error_reporting($oldErrorReporting);
            }
            $this->updateProgress(0, 0, 'Error: ' . $e->getMessage(), true);
            error_log("Import Products With Progress Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Import warehouse stock with progress tracking
     */
    public function importWarehouseStockWithProgress($filePath, $companyId, $sessionId = null) {
        try {
            // Suppress warnings to prevent JSON interference
            $oldErrorReporting = error_reporting(E_ERROR | E_PARSE);

            // Start session if sessionId provided
            if ($sessionId) {
                session_id($sessionId);
                session_start();
            }

            $this->updateProgress(0, 0, 'Validando archivo de stock...');

            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $highestRow = $worksheet->getHighestDataRow();
            $highestColumn = $worksheet->getHighestDataColumn();

            $this->updateProgress(10, $highestRow - 1, 'Procesando encabezados...');

            // Get headers and warehouse columns
            $headerRange = 'A1:' . $highestColumn . '1';
            $headers = $worksheet->rangeToArray($headerRange)[0];
            $warehouseColumns = $this->getWarehouseColumns($headers);

            if (empty($warehouseColumns)) {
                $this->updateProgress(0, 0, 'Error: No se encontraron columnas de almacén', true);
                return ['success' => false, 'message' => 'No warehouse columns found in Excel file'];
            }

            $this->updateProgress(20, $highestRow - 1, 'Iniciando importación de stock...');

            $processed = 0;
            $errors = [];

            // Process data rows
            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $rowRange = 'A' . $rowNumber . ':' . $highestColumn . $rowNumber;
                $row = $worksheet->rangeToArray($rowRange)[0];

                $articleCode = trim($row[0] ?? ''); // Assuming first column is article code

                if (empty($articleCode)) {
                    continue;
                }

                // Find product by code
                $product = $this->getProductByCode($companyId, $articleCode);

                if (!$product) {
                    $errors[] = "Row $rowNumber: Product with code '$articleCode' not found";
                    continue;
                }

                // Process warehouse stock for this product
                foreach ($warehouseColumns as $colIndex => $warehouseName) {
                    $stockQuantity = floatval($row[$colIndex] ?? 0);
                    $this->saveWarehouseStock($product['id'], $companyId, $warehouseName, $stockQuantity);
                }

                $processed++;

                // Update progress every 5 rows
                if ($processed % 5 === 0 || $rowNumber === $highestRow) {
                    $progress = 20 + (($processed / ($highestRow - 1)) * 75); // 20% to 95%
                    $this->updateProgress($progress, $highestRow - 1, "Procesando stock... ($processed/" . ($highestRow - 1) . ")", false, $processed);
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $this->updateProgress(100, $highestRow - 1, 'Importación de stock completada', true, $processed);

            // Restore error reporting
            error_reporting($oldErrorReporting);

            return [
                'success' => true,
                'processed' => $processed,
                'warehouses' => count($warehouseColumns),
                'errors' => $errors,
                'total_rows' => $highestRow - 1
            ];

        } catch (Exception $e) {
            // Restore error reporting
            if (isset($oldErrorReporting)) {
                error_reporting($oldErrorReporting);
            }
            $this->updateProgress(0, 0, 'Error: ' . $e->getMessage(), true);
            error_log("Import Warehouse Stock With Progress Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update progress in session
     */
    private function updateProgress($progress, $total, $status, $completed = false, $currentRow = 0) {
        // Make sure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['import_progress'] = [
            'started' => true,
            'progress' => round($progress, 2),
            'total' => $total,
            'current_row' => $currentRow,
            'status' => $status,
            'completed' => $completed,
            'error' => $completed && strpos($status, 'Error:') === 0 ? $status : null,
            'start_time' => $_SESSION['import_progress']['start_time'] ?? time()
        ];

        // Write session data immediately
        session_write_close();

        // Restart session for continued use
        session_start();
    }
}
?>