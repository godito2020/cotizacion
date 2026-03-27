<?php

class CompanySettings {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    // Company Configuration Methods
    public function updateCompanyInfo($companyId, $data) {
        try {
            // Update settings table instead of companies table
            $settingKeys = [
                'company_name' => $data['name'],
                'company_tax_id' => $data['tax_id'],
                'company_address' => $data['address'],
                'company_phone' => $data['phone'],
                'company_email' => $data['email'],
                'company_website' => $data['website'],
                'company_whatsapp' => $data['whatsapp']
            ];

            foreach ($settingKeys as $key => $value) {
                $this->updateSetting($companyId, $key, $value);
            }

            return true;
        } catch (Exception $e) {
            error_log("Error updating company info: " . $e->getMessage());
            return false;
        }
    }

    public function updateLogo($companyId, $logoPath) {
        try {
            return $this->updateSetting($companyId, 'company_logo_url', $logoPath);
        } catch (Exception $e) {
            error_log("Error updating logo: " . $e->getMessage());
            return false;
        }
    }

    public function updateFavicon($companyId, $faviconPath) {
        try {
            return $this->updateSetting($companyId, 'company_favicon_url', $faviconPath);
        } catch (Exception $e) {
            error_log("Error updating favicon: " . $e->getMessage());
            return false;
        }
    }

    // Settings Table Management Methods
    public function updateSetting($companyId, $settingKey, $settingValue) {
        try {
            // UPDATE primero (funciona aunque no haya UNIQUE constraint)
            $stmt = $this->db->prepare(
                "UPDATE settings SET setting_value = ? WHERE company_id = ? AND setting_key = ?"
            );
            $stmt->execute([$settingValue, $companyId, $settingKey]);

            if ($stmt->rowCount() === 0) {
                // No existía fila — insertar
                $stmt = $this->db->prepare(
                    "INSERT INTO settings (company_id, setting_key, setting_value) VALUES (?, ?, ?)"
                );
                $stmt->execute([$companyId, $settingKey, $settingValue]);
            }
            return true;
        } catch (PDOException $e) {
            error_log("Error updating setting: " . $e->getMessage());
            return false;
        }
    }

    // Settings that are global/shared and should fall back to any company's value
    private static $sharedSettings = ['exchange_rate_usd_pen'];

    public function getSetting($companyId, $settingKey, $defaultValue = '') {
        try {
            $sql = "SELECT setting_value FROM settings WHERE company_id = ? AND setting_key = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$companyId, $settingKey]);

            $result = $stmt->fetchColumn();
            if ($result !== false) {
                return $result;
            }

            // For shared settings, fall back to any company that has it configured
            if (in_array($settingKey, self::$sharedSettings)) {
                $fallbackStmt = $this->db->prepare("SELECT setting_value FROM settings WHERE setting_key = ? AND setting_value IS NOT NULL AND setting_value != '' ORDER BY company_id ASC LIMIT 1");
                $fallbackStmt->execute([$settingKey]);
                $fallback = $fallbackStmt->fetchColumn();
                if ($fallback !== false) {
                    return $fallback;
                }
            }

            return $defaultValue;
        } catch (PDOException $e) {
            error_log("Error getting setting: " . $e->getMessage());
            return $defaultValue;
        }
    }

    public function getAllSettings($companyId) {
        try {
            $sql = "SELECT setting_key, setting_value FROM settings WHERE company_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$companyId]);

            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }

            return $settings;
        } catch (PDOException $e) {
            error_log("Error getting all settings: " . $e->getMessage());
            return [];
        }
    }

    public function getCompanyInfo($companyId) {
        $settings = $this->getAllSettings($companyId);

        return [
            'name' => $settings['company_name'] ?? '',
            'tax_id' => $settings['company_tax_id'] ?? '',
            'address' => $settings['company_address'] ?? '',
            'phone' => $settings['company_phone'] ?? '',
            'email' => $settings['company_email'] ?? '',
            'website' => $settings['company_website'] ?? '',
            'whatsapp' => $settings['company_whatsapp'] ?? '',
            'logo_url' => $settings['company_logo_url'] ?? '',
            'favicon_url' => $settings['company_favicon_url'] ?? ''
        ];
    }

    // Email Settings Methods

    public function getEmailSettings($companyId) {
        try {
            $settings = $this->getAllSettings($companyId);

            $emailSettings = [
                'smtp_host' => $settings['smtp_host'] ?? '',
                'smtp_port' => $settings['smtp_port'] ?? 587,
                'smtp_username' => $settings['smtp_username'] ?? '',
                'smtp_password' => $settings['smtp_password'] ?? '',
                'smtp_encryption' => $settings['smtp_encryption'] ?? 'tls',
                'from_email' => $settings['email_from'] ?? '',
                'from_name' => $settings['email_from_name'] ?? '',
                'reply_to_email' => $settings['email_reply_to'] ?? '',
                'use_smtp' => $settings['smtp_enabled'] ?? 0
            ];

            // Decrypt password if not empty
            if (!empty($emailSettings['smtp_password'])) {
                $emailSettings['smtp_password'] = $this->decryptPassword($emailSettings['smtp_password']);
            }

            return $emailSettings;
        } catch (Exception $e) {
            error_log("Error getting email settings: " . $e->getMessage());
            return [
                'smtp_host' => '',
                'smtp_port' => 587,
                'smtp_username' => '',
                'smtp_password' => '',
                'smtp_encryption' => 'tls',
                'from_email' => '',
                'from_name' => '',
                'reply_to_email' => '',
                'use_smtp' => 0
            ];
        }
    }

    // Bank Accounts Methods
    public function addBankAccount($companyId, $bankData) {
        try {
            // If setting as default, unset other defaults for same currency
            if (!empty($bankData['is_default'])) {
                $sql = "UPDATE bank_accounts SET is_default = 0
                        WHERE company_id = :company_id AND currency = :currency";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'company_id' => $companyId,
                    'currency' => $bankData['currency']
                ]);
            }

            $sql = "INSERT INTO bank_accounts
                    (company_id, bank_name, account_type, account_number, account_holder, cci, currency, is_active, is_default)
                    VALUES (:company_id, :bank_name, :account_type, :account_number, :account_holder, :cci, :currency, :is_active, :is_default)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'company_id' => $companyId,
                'bank_name' => $bankData['bank_name'],
                'account_type' => $bankData['account_type'],
                'account_number' => $bankData['account_number'],
                'account_holder' => $bankData['account_holder'] ?? '',
                'cci' => $bankData['cci'],
                'currency' => $bankData['currency'],
                'is_active' => $bankData['is_active'] ? 1 : 0,
                'is_default' => !empty($bankData['is_default']) ? 1 : 0
            ]);

            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error adding bank account: " . $e->getMessage());
            return false;
        }
    }




    // API Settings Methods

    public function getApiSettings($companyId, $apiName) {
        try {
            $sql = "SELECT * FROM api_settings WHERE company_id = :company_id AND api_name = :api_name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'company_id' => $companyId,
                'api_name' => strtolower($apiName)
            ]);

            $settings = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$settings) {
                // Fallback: use API settings from any other company that has them configured
                $fallbackSql = "SELECT * FROM api_settings WHERE api_name = :api_name AND is_active = 1 ORDER BY company_id ASC LIMIT 1";
                $fallbackStmt = $this->db->prepare($fallbackSql);
                $fallbackStmt->execute(['api_name' => strtolower($apiName)]);
                $settings = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$settings) {
                return [
                    'enabled' => 0,
                    'api_url' => '',
                    'api_token' => '',
                    'timeout' => 30
                ];
            }

            // Map database columns to expected format
            $result = [
                'enabled' => $settings['is_active'] ?? 0,
                'api_url' => $settings['api_url'] ?? '',
                'api_token' => $settings['api_key'] ?? '',
                'timeout' => 30
            ];

            // Merge additional config if exists
            if ($settings['additional_config']) {
                $additional = json_decode($settings['additional_config'], true);
                if ($additional) {
                    $result = array_merge($result, $additional);
                }
            }

            return $result;
        } catch (PDOException $e) {
            error_log("Error getting API settings: " . $e->getMessage());
            return [
                'enabled' => 0,
                'api_url' => '',
                'api_token' => '',
                'timeout' => 30
            ];
        }
    }

    // Email Settings Methods - Updated
    public function updateEmailSettings($companyId, $emailConfig) {
        try {
            // Encrypt password before saving
            $encryptedPassword = !empty($emailConfig['smtp_password']) ?
                $this->encryptPassword($emailConfig['smtp_password']) : '';

            // Update settings using the settings table
            $settingsMap = [
                'smtp_enabled' => $emailConfig['use_smtp'],
                'smtp_host' => $emailConfig['smtp_host'],
                'smtp_port' => $emailConfig['smtp_port'],
                'smtp_username' => $emailConfig['smtp_username'],
                'smtp_password' => $encryptedPassword,
                'smtp_encryption' => $emailConfig['smtp_encryption'],
                'email_from' => $emailConfig['from_email'],
                'email_from_name' => $emailConfig['from_name'],
                'email_reply_to' => $emailConfig['reply_to_email']
            ];

            foreach ($settingsMap as $key => $value) {
                $this->updateSetting($companyId, $key, $value);
            }

            return true;
        } catch (Exception $e) {
            error_log("Error updating email settings: " . $e->getMessage());
            return false;
        }
    }

    // API Settings Methods - Updated
    public function updateApiSettings($companyId, $apiType, $apiConfig) {
        try {
            // Check if record exists
            $checkSql = "SELECT id FROM api_settings WHERE company_id = :company_id AND api_name = :api_name";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute(['company_id' => $companyId, 'api_name' => $apiType]);
            $exists = $checkStmt->fetch();

            if ($exists) {
                // Update existing record
                $sql = "UPDATE api_settings SET
                        api_url = :api_url,
                        api_key = :api_key,
                        is_active = :is_active,
                        additional_config = :additional_config,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE company_id = :company_id AND api_name = :api_name";
            } else {
                // Insert new record
                $sql = "INSERT INTO api_settings
                        (company_id, api_name, api_url, api_key, is_active, additional_config, created_at, updated_at)
                        VALUES (:company_id, :api_name, :api_url, :api_key, :is_active, :additional_config, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
            }

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'company_id' => $companyId,
                'api_name' => $apiType,
                'api_url' => $apiConfig[$apiType . '_api_url'] ?? '',
                'api_key' => $apiConfig[$apiType . '_api_token'] ?? '',
                'is_active' => $apiConfig[$apiType . '_api_enabled'] ?? 0,
                'additional_config' => json_encode($apiConfig)
            ]);

        } catch (PDOException $e) {
            error_log("Error updating API settings: " . $e->getMessage());
            return false;
        }
    }

    // Bank Account Methods - Updated
    public function getBankAccounts($companyId, $activeOnly = false) {
        try {
            $sql = "SELECT * FROM bank_accounts WHERE company_id = :company_id";
            if ($activeOnly) {
                $sql .= " AND is_active = 1";
            }
            $sql .= " ORDER BY is_default DESC, bank_name ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['company_id' => $companyId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting bank accounts: " . $e->getMessage());
            return [];
        }
    }

    public function updateBankAccount($companyId, $accountId, $bankData) {
        try {
            // If setting as default, unset other defaults for same currency
            if ($bankData['is_default']) {
                $sql = "UPDATE bank_accounts SET is_default = 0
                        WHERE company_id = :company_id AND currency = :currency AND id != :account_id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'company_id' => $companyId,
                    'currency' => $bankData['currency'],
                    'account_id' => $accountId
                ]);
            }

            $sql = "UPDATE bank_accounts SET
                    bank_name = :bank_name,
                    account_type = :account_type,
                    account_number = :account_number,
                    account_holder = :account_holder,
                    currency = :currency,
                    cci = :cci,
                    is_active = :is_active,
                    is_default = :is_default,
                    updated_at = CURRENT_TIMESTAMP
                    WHERE id = :account_id AND company_id = :company_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'bank_name' => $bankData['bank_name'],
                'account_type' => $bankData['account_type'],
                'account_number' => $bankData['account_number'],
                'account_holder' => $bankData['account_holder'],
                'currency' => $bankData['currency'],
                'cci' => $bankData['cci'],
                'is_active' => $bankData['is_active'],
                'is_default' => $bankData['is_default'],
                'account_id' => $accountId,
                'company_id' => $companyId
            ]);

        } catch (PDOException $e) {
            error_log("Error updating bank account: " . $e->getMessage());
            return false;
        }
    }

    public function deleteBankAccount($companyId, $accountId) {
        try {
            $sql = "DELETE FROM bank_accounts WHERE id = :account_id AND company_id = :company_id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'account_id' => $accountId,
                'company_id' => $companyId
            ]);
        } catch (PDOException $e) {
            error_log("Error deleting bank account: " . $e->getMessage());
            return false;
        }
    }

    // Brand Logos Methods
    public function getBrandLogos($companyId, $activeOnly = false) {
        try {
            $sql = "SELECT * FROM company_brands WHERE company_id = :company_id";
            if ($activeOnly) $sql .= " AND is_active = 1";
            $sql .= " ORDER BY sort_order ASC, id ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['company_id' => $companyId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting brand logos: " . $e->getMessage());
            return [];
        }
    }

    public function addBrandLogo($companyId, $brandName, $logoUrl, $sortOrder = 0) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO company_brands (company_id, brand_name, logo_url, sort_order) VALUES (?, ?, ?, ?)"
            );
            return $stmt->execute([$companyId, $brandName, $logoUrl, $sortOrder]);
        } catch (PDOException $e) {
            error_log("Error adding brand logo: " . $e->getMessage());
            return false;
        }
    }

    public function updateBrandLogo($companyId, $brandId, $brandName, $sortOrder, $isActive) {
        try {
            $stmt = $this->db->prepare(
                "UPDATE company_brands SET brand_name=?, sort_order=?, is_active=? WHERE id=? AND company_id=?"
            );
            return $stmt->execute([$brandName, $sortOrder, $isActive, $brandId, $companyId]);
        } catch (PDOException $e) {
            error_log("Error updating brand logo: " . $e->getMessage());
            return false;
        }
    }

    public function deleteBrandLogo($companyId, $brandId) {
        try {
            // Get logo_url before deleting so we can delete the file
            $stmt = $this->db->prepare("SELECT logo_url FROM company_brands WHERE id=? AND company_id=?");
            $stmt->execute([$brandId, $companyId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['logo_url'])) {
                $filePath = PUBLIC_PATH . '/' . $row['logo_url'];
                if (file_exists($filePath)) @unlink($filePath);
            }
            $stmt = $this->db->prepare("DELETE FROM company_brands WHERE id=? AND company_id=?");
            return $stmt->execute([$brandId, $companyId]);
        } catch (PDOException $e) {
            error_log("Error deleting brand logo: " . $e->getMessage());
            return false;
        }
    }

    // Utility Methods
    private function encryptPassword($password) {
        // Simple encryption - in production use stronger encryption
        $key = hash('sha256', 'cotizacion_secret_key');
        $iv = substr(hash('sha256', 'cotizacion_iv'), 0, 16);
        return openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
    }

    private function decryptPassword($encryptedPassword) {
        $key = hash('sha256', 'cotizacion_secret_key');
        $iv = substr(hash('sha256', 'cotizacion_iv'), 0, 16);
        return openssl_decrypt($encryptedPassword, 'AES-256-CBC', $key, 0, $iv);
    }

    // File Upload Methods

    /**
     * Genera íconos PWA redimensionados (192×192 y 512×512) a partir de una imagen fuente.
     * Los guarda como uploads/company/pwa_{companyId}_192x192.png y pwa_{companyId}_512x512.png.
     * SVG se omite (no necesita redimensionar, el manifest lo usa con sizes="any").
     *
     * @return array  Rutas relativas generadas (vacío si SVG o error GD)
     */
    public function generatePwaIcons(string $sourcePath, int $companyId): array {
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        // SVG es vectorial — no necesita redimensionar
        if ($ext === 'svg') {
            return [];
        }

        // Cargar fuente con GD
        $src = null;
        switch ($ext) {
            case 'jpg':
            case 'jpeg': $src = @imagecreatefromjpeg($sourcePath); break;
            case 'png':  $src = @imagecreatefrompng($sourcePath);  break;
            case 'webp': $src = @imagecreatefromwebp($sourcePath); break;
            case 'gif':  $src = @imagecreatefromgif($sourcePath);  break;
            case 'ico':
                // ICO: intentar como PNG (muchos ICO modernos son PNG embebido)
                $src = @imagecreatefrompng($sourcePath);
                if (!$src) $src = @imagecreatefromstring(file_get_contents($sourcePath));
                break;
        }

        if (!$src) {
            error_log("generatePwaIcons: no se pudo cargar imagen: $sourcePath");
            return [];
        }

        $srcW     = imagesx($src);
        $srcH     = imagesy($src);
        $outputDir = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company';
        $generated = [];

        foreach ([192, 512] as $size) {
            $dst = imagecreatetruecolor($size, $size);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            // Fondo transparente
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefill($dst, 0, 0, $transparent);

            // Escalar manteniendo proporción, centrado en el canvas
            $ratio = min($size / $srcW, $size / $srcH);
            $newW  = (int)($srcW * $ratio);
            $newH  = (int)($srcH * $ratio);
            $dstX  = (int)(($size - $newW) / 2);
            $dstY  = (int)(($size - $newH) / 2);

            imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);

            $outFile = "pwa_{$companyId}_{$size}x{$size}.png";
            $outPath = $outputDir . DIRECTORY_SEPARATOR . $outFile;

            if (imagepng($dst, $outPath, 9)) {
                $generated[] = 'uploads/company/' . $outFile;
            } else {
                error_log("generatePwaIcons: no se pudo guardar $outPath");
            }
            imagedestroy($dst);
        }

        imagedestroy($src);
        return $generated;
    }

    public function handleFileUpload($file, $uploadDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif']) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload error: ' . $file['error']];
        }

        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);

        if (!in_array($extension, $allowedTypes)) {
            return ['success' => false, 'message' => 'Invalid file type'];
        }

        // Normalizar directorio (sin barra al final) para evitar doble barra en Windows
        $uploadDir  = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $uploadDir), DIRECTORY_SEPARATOR);
        $filename   = uniqid() . '_' . time() . '.' . $extension;
        $uploadPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;

        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            return ['success' => true, 'filename' => $filename, 'path' => $uploadPath];
        } else {
            return ['success' => false, 'message' => 'Failed to move uploaded file'];
        }
    }
}
?>