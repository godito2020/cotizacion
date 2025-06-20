<?php
// cotizacion/lib/ImportHelper.php

class ImportHelper {

    /**
     * Parses a CSV file, validates headers, and returns its content.
     *
     * @param string $filePath Path to the CSV file.
     * @param array $expectedHeaders An array of strings representing the expected header columns.
     * @param string $delimiter CSV delimiter, default is comma.
     * @param int $length Max line length for fgetcsv, default 0 (no limit).
     * @return array An array containing 'data' (array of associative arrays) or 'error' (string message).
     *               Example success: ['data' => [['Header1' => 'val1', 'Header2' => 'val2'], ...]]
     *               Example error: ['error' => 'Error message here']
     */
    public function parseCsv(string $filePath, array $expectedHeaders, string $delimiter = ',', int $length = 0): array {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return ['error' => 'El archivo no existe o no se puede leer.'];
        }

        $header = null;
        $data = [];
        $rowCount = 0;

        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle, $length, $delimiter)) !== false) {
                $rowCount++;
                if (!$header) {
                    // Normalize headers: trim spaces, convert to lowercase for comparison if needed (optional)
                    $header = array_map('trim', $row);
                    // Validate headers
                    if (count($header) < count($expectedHeaders)) { // Allow more columns in CSV than expected, but not less
                        fclose($handle);
                        return ['error' => 'El archivo CSV tiene menos columnas de las esperadas. Cabeceras encontradas: ' . implode(', ', $header) . '. Cabeceras esperadas: ' . implode(', ', $expectedHeaders)];
                    }

                    $actualHeadersSlice = array_slice($header, 0, count($expectedHeaders));
                    if ($actualHeadersSlice !== $expectedHeaders) {
                        fclose($handle);
                        return ['error' => 'Las cabeceras del archivo CSV no coinciden con el formato esperado. Esperado: "' . implode('", "', $expectedHeaders) . '". Encontrado: "'. implode('", "', $actualHeadersSlice) . '".'];
                    }
                } else {
                    // Ensure row has enough columns to match headers, pad with null if not (or error out)
                    $rowData = [];
                    foreach ($header as $i => $colName) {
                        // Only map data for expected headers, or all headers found
                        // For this implementation, we map all columns found using actual file headers
                        $rowData[$colName] = $row[$i] ?? null;
                    }
                    $data[] = $rowData;
                }
            }
            fclose($handle);

            if ($rowCount === 0) {
                 return ['error' => 'El archivo CSV está vacío.'];
            }
            if ($rowCount === 1 && !empty($header) && empty($data)) {
                return ['error' => 'El archivo CSV solo contiene la fila de cabecera, no hay datos para importar.'];
            }

            return ['data' => $data];
        } else {
            return ['error' => 'No se pudo abrir el archivo CSV.'];
        }
    }
}
?>
