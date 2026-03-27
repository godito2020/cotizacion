<?php

class PeruApiClient {
    private $db;
    private $companyId;

    public function __construct($companyId = null) {
        $this->db = getDBConnection();
        $this->companyId = $companyId;
    }

    public function consultarRuc($ruc, $companyId = null, $skipValidation = false) {
        if ($companyId) {
            $this->companyId = $companyId;
        }

        $apiSettings = $this->getApiSettings('sunat');

        if (!$apiSettings || !($apiSettings['enabled'] ?? false)) {
            return [
                'success' => false,
                'message' => 'SUNAT API not configured'
            ];
        }

        $ruc = $this->cleanRuc($ruc);

        // Basic validation: length and numeric
        $length = strlen($ruc);
        if (!is_numeric($ruc) || ($length !== 10 && $length !== 11)) {
            return [
                'success' => false,
                'message' => 'Invalid RUC format: must be 10 or 11 digits'
            ];
        }

        // Note: Check digit validation is not performed as the API will validate the RUC
        // and different RUC types (10, 15, 17, 20) have different validation algorithms

        try {
            // Build URL for apis.net.pe - use query parameter instead of placeholder
            $baseUrl = $apiSettings['api_url'] ?? '';
            if (empty($baseUrl)) {
                return [
                    'success' => false,
                    'message' => 'SUNAT API URL not configured'
                ];
            }

            // For apis.net.pe format: URL already includes ?numero= parameter
            if (strpos($baseUrl, '?numero=') !== false) {
                $url = $baseUrl . $ruc;
            } else {
                $url = $baseUrl . '?numero=' . $ruc;
            }

            $headers = [];
            if (!empty($apiSettings['api_token'])) {
                $headers[] = 'Authorization: Bearer ' . $apiSettings['api_token'];
            }

            $response = $this->makeRequest($url, $headers);

            if ($response['success']) {
                $data = $response['data'];

                $result = [
                    'success' => true,
                    'data' => [
                        'ruc' => $data['ruc'] ?? $ruc,
                        'razon_social' => $data['razonSocial'] ?? $data['nombre_o_razon_social'] ?? '',
                        'nombre_comercial' => $data['nombreComercial'] ?? $data['nombre_comercial'] ?? '',
                        'tipo_contribuyente' => $data['tipoContribuyente'] ?? $data['tipo_contribuyente'] ?? '',
                        'tipo_documento' => $data['tipoDocumento'] ?? $data['tipo_documento'] ?? '',
                        'estado' => $data['estado'] ?? '',
                        'condicion' => $data['condicion'] ?? '',
                        'direccion' => $data['direccion'] ?? '',
                        'ubigeo' => $data['ubigeo'] ?? '',
                        'viaTipo' => $data['viaTipo'] ?? '',
                        'viaNombre' => $data['viaNombre'] ?? '',
                        'zonaCodigo' => $data['zonaCodigo'] ?? '',
                        'zonaTipo' => $data['zonaTipo'] ?? '',
                        'numero' => $data['numero'] ?? '',
                        'interior' => $data['interior'] ?? '',
                        'lote' => $data['lote'] ?? '',
                        'dpto' => $data['dpto'] ?? '',
                        'manzana' => $data['manzana'] ?? '',
                        'kilometro' => $data['kilometro'] ?? '',
                        'distrito' => $data['distrito'] ?? '',
                        'provincia' => $data['provincia'] ?? '',
                        'departamento' => $data['departamento'] ?? '',
                    ]
                ];

                return $result;
            } else {
                return [
                    'success' => false,
                    'message' => $response['message']
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error consulting RUC: ' . $e->getMessage()
            ];
        }
    }

    public function consultarDni($dni, $companyId = null) {
        if ($companyId) {
            $this->companyId = $companyId;
        }

        $apiSettings = $this->getApiSettings('reniec');

        if (!$apiSettings || !($apiSettings['enabled'] ?? false)) {
            return [
                'success' => false,
                'message' => 'RENIEC API not configured'
            ];
        }

        $dni = $this->cleanDni($dni);

        if (!$this->isValidDni($dni)) {
            return [
                'success' => false,
                'message' => 'Invalid DNI format'
            ];
        }

        try {
            // Build URL for apis.net.pe - use query parameter instead of placeholder
            $baseUrl = $apiSettings['api_url'] ?? '';
            if (empty($baseUrl)) {
                return [
                    'success' => false,
                    'message' => 'RENIEC API URL not configured'
                ];
            }

            // For apis.net.pe format: URL already includes ?numero= parameter
            if (strpos($baseUrl, '?numero=') !== false) {
                $url = $baseUrl . $dni;
            } else {
                $url = $baseUrl . '?numero=' . $dni;
            }

            $headers = [];
            if (!empty($apiSettings['api_token'])) {
                $headers[] = 'Authorization: Bearer ' . $apiSettings['api_token'];
            }

            $response = $this->makeRequest($url, $headers);

            if ($response['success']) {
                $data = $response['data'];

                return [
                    'success' => true,
                    'data' => [
                        'dni' => $data['dni'] ?? $dni,
                        'nombres' => $data['nombres'] ?? '',
                        'apellido_paterno' => $data['apellidoPaterno'] ?? $data['apellido_paterno'] ?? '',
                        'apellido_materno' => $data['apellidoMaterno'] ?? $data['apellido_materno'] ?? '',
                        'nombre_completo' => trim(($data['nombres'] ?? '') . ' ' .
                                                 ($data['apellidoPaterno'] ?? $data['apellido_paterno'] ?? '') . ' ' .
                                                 ($data['apellidoMaterno'] ?? $data['apellido_materno'] ?? '')),
                        'codigo_verificacion' => $data['codigoVerificacion'] ?? $data['codigo_verificacion'] ?? ''
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $response['message']
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error consulting DNI: ' . $e->getMessage()
            ];
        }
    }

    private function makeRequest($url, $headers = []) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
                'Accept: application/json'
            ], $headers),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Cotizacion App v1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => 'cURL Error: ' . $error
            ];
        }

        if ($httpCode !== 200) {
            $errorMessage = 'HTTP Error: ' . $httpCode;

            // Add more specific error information
            switch ($httpCode) {
                case 401:
                    $errorMessage .= ' - API Token inválido o expirado';
                    break;
                case 403:
                    $errorMessage .= ' - Acceso denegado a la API';
                    break;
                case 429:
                    $errorMessage .= ' - Límite de consultas excedido';
                    break;
                case 502:
                    $errorMessage .= ' - Error del servidor API (temporalmente no disponible)';
                    break;
                case 503:
                    $errorMessage .= ' - Servicio API temporalmente no disponible';
                    break;
                case 504:
                    $errorMessage .= ' - Timeout del servidor API';
                    break;
                default:
                    if ($response) {
                        $errorMessage .= ' - Respuesta: ' . substr($response, 0, 100);
                    }
            }

            return [
                'success' => false,
                'message' => $errorMessage
            ];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Invalid JSON response'
            ];
        }

        return [
            'success' => true,
            'data' => $data
        ];
    }

    private function getApiSettings($apiName) {
        try {
            if (!$this->companyId) {
                return null;
            }

            $companySettings = new CompanySettings();
            $settings = $companySettings->getApiSettings($this->companyId, $apiName);

            return $settings;
        } catch (Exception $e) {
            error_log("Error getting API settings: " . $e->getMessage());
            return null;
        }
    }

    private function cleanRuc($ruc) {
        return preg_replace('/[^0-9]/', '', $ruc);
    }

    private function cleanDni($dni) {
        return preg_replace('/[^0-9]/', '', $dni);
    }

    private function isValidRuc($ruc) {
        // RUC can be 10 or 11 digits (10 digits for natural persons with business activity)
        $length = strlen($ruc);
        if (!is_numeric($ruc) || ($length !== 10 && $length !== 11)) {
            return false;
        }

        // Only validate check digit for 11-digit RUCs
        if ($length === 11) {
            return $this->validateRucDigit($ruc);
        }

        return true; // 10-digit RUCs don't have check digit validation
    }

    private function isValidDni($dni) {
        return strlen($dni) === 8 && is_numeric($dni);
    }

    private function validateRucDigit($ruc) {
        // En Perú existen diferentes tipos de RUC según el prefijo:
        // 10: Persona Natural con Negocio (DNI + dígito verificador)
        // 15: Persona Natural no Domiciliada
        // 17: Persona Natural con Negocio - EIRL
        // 20: Persona Jurídica (empresa)

        // Para RUC tipo 10 (persona natural), el algoritmo de validación es diferente
        // porque se basa en el DNI (8 dígitos) + 2 dígitos (código 10) + 1 dígito verificador
        $prefix = substr($ruc, 0, 2);

        // Para RUC tipo 10, usar validación específica
        if ($prefix === '10') {
            return $this->validateRucTipo10($ruc);
        }

        // Para otros tipos (20, 15, 17, etc.), usar validación estándar
        $factors = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $sum += intval($ruc[$i]) * $factors[$i];
        }

        $remainder = $sum % 11;
        $digit = $remainder < 2 ? $remainder : 11 - $remainder;

        return $digit == intval($ruc[10]);
    }

    private function validateRucTipo10($ruc) {
        // RUC tipo 10: formato 10 + DNI(8 dígitos) + dígito verificador
        // Ejemplo: 10438480621 = 10 + 43848062 + 1

        // Validación del dígito verificador para RUC tipo 10
        $factors = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $sum += intval($ruc[$i]) * $factors[$i];
        }

        $remainder = $sum % 11;
        $digit = 11 - $remainder;

        // Si el dígito es 10 u 11, se usa 0
        if ($digit == 10 || $digit == 11) {
            $digit = 0;
        }

        return $digit == intval($ruc[10]);
    }

    public function testApiConnection($apiName) {
        $apiSettings = $this->getApiSettings($apiName);

        if (!$apiSettings) {
            return [
                'success' => false,
                'message' => 'API settings not found'
            ];
        }

        // Test with a known valid document number
        if (strtolower($apiName) === 'sunat') {
            return $this->consultarRuc('20100070970'); // Sample RUC
        } elseif (strtolower($apiName) === 'reniec') {
            return $this->consultarDni('12345678'); // Sample DNI
        }

        return [
            'success' => false,
            'message' => 'Unknown API name'
        ];
    }

    public static function getDefaultApiConfigs() {
        return [
            'SUNAT' => [
                'api_name' => 'SUNAT',
                'api_url' => 'https://api.apis.net.pe/v1', // Replace with actual API
                'description' => 'API for SUNAT RUC consultation'
            ],
            'RENIEC' => [
                'api_name' => 'RENIEC',
                'api_url' => 'https://api.apis.net.pe/v1', // Replace with actual API
                'description' => 'API for RENIEC DNI consultation'
            ]
        ];
    }

    public function formatAddress($addressData) {
        $parts = [];

        if (!empty($addressData['viaTipo']) && !empty($addressData['viaNombre'])) {
            $parts[] = $addressData['viaTipo'] . ' ' . $addressData['viaNombre'];
        }

        if (!empty($addressData['numero'])) {
            $parts[] = 'N° ' . $addressData['numero'];
        }

        if (!empty($addressData['interior'])) {
            $parts[] = 'Int. ' . $addressData['interior'];
        }

        if (!empty($addressData['zonaTipo']) && !empty($addressData['zonaCodigo'])) {
            $parts[] = $addressData['zonaTipo'] . ' ' . $addressData['zonaCodigo'];
        }

        if (!empty($addressData['distrito'])) {
            $parts[] = $addressData['distrito'];
        }

        if (!empty($addressData['provincia'])) {
            $parts[] = $addressData['provincia'];
        }

        if (!empty($addressData['departamento'])) {
            $parts[] = $addressData['departamento'];
        }

        return implode(', ', $parts);
    }
}
?>