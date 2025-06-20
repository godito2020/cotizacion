<?php
// cotizacion/lib/Settings.php

class Settings {
    private $db;

    // Define known setting keys as constants for consistency
    // System-level keys
    public const KEY_SUNAT_API_URL = 'system.sunat.api.url';
    public const KEY_SUNAT_API_KEY = 'system.sunat.api.key';
    public const KEY_RENIEC_API_URL = 'system.reniec.api.url';
    public const KEY_RENIEC_API_KEY = 'system.reniec.api.key';

    public const KEY_SMTP_HOST = 'system.smtp.host';
    public const KEY_SMTP_PORT = 'system.smtp.port';
    public const KEY_SMTP_USER = 'system.smtp.user';
    public const KEY_SMTP_PASS = 'system.smtp.password';
    public const KEY_SMTP_ENCRYPTION = 'system.smtp.encryption'; // e.g., 'tls', 'ssl', or empty
    public const KEY_SMTP_FROM_EMAIL = 'system.smtp.from_email';
    public const KEY_SMTP_FROM_NAME = 'system.smtp.from_name';

    public const KEY_DEFAULT_TERMS_QUOTATION = 'system.default.terms_conditions.quotation';
    public const KEY_DEFAULT_NOTES_QUOTATION = 'system.default.notes.quotation';
    public const KEY_CURRENCY_SYMBOL = 'system.currency.symbol'; // e.g. S/, $

    // Company-specific keys (can override system defaults or be unique to company)
    // These might use the same constant name if they are direct overrides,
    // or have specific prefixes/suffixes if they are distinct settings.
    // For example, a company might override the default terms:
    public const KEY_COMPANY_TERMS_QUOTATION = 'company.terms_conditions.quotation';
    public const KEY_COMPANY_NOTES_QUOTATION = 'company.notes.quotation';
    public const KEY_COMPANY_LOGO_URL_OVERRIDE = 'company.logo_url.override'; // If company wants a different logo than in `companies` table for some reason
    public const KEY_COMPANY_QUOTATION_PREFIX = 'company.quotation.prefix';


    public function __construct() {
        $this->db = getDBConnection();
    }

    /**
     * Retrieves a setting value.
     * If company_id is provided, it first looks for a company-specific setting.
     * If not found or company_id is null, it looks for a system-wide default.
     *
     * @param string $setting_key The key of the setting.
     * @param int|null $company_id Optional company ID.
     * @param mixed $default_value Value to return if the setting is not found at all.
     * @return mixed The setting value or the default.
     */
    public function get(string $setting_key, ?int $company_id = null, $default_value = null) {
        $value = null;
        $found = false;

        if ($company_id !== null) {
            try {
                $sql = "SELECT setting_value FROM settings WHERE setting_key = :setting_key AND company_id = :company_id";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':setting_key', $setting_key);
                $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result !== false) {
                    $value = $result['setting_value'];
                    $found = true;
                }
            } catch (PDOException $e) {
                error_log("Settings::get (company) Error: " . $e->getMessage());
                // Continue to try system default
            }
        }

        if (!$found) { // Try system default if company-specific not found or not requested
            try {
                $sql = "SELECT setting_value FROM settings WHERE setting_key = :setting_key AND company_id IS NULL";
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':setting_key', $setting_key);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result !== false) {
                    $value = $result['setting_value'];
                    $found = true;
                }
            } catch (PDOException $e) {
                error_log("Settings::get (system) Error: " . $e->getMessage());
            }
        }

        return $found ? $value : $default_value;
    }

    /**
     * Sets a setting value (creates or updates).
     * The unique constraint on (company_id, setting_key) handles insert vs update.
     *
     * @param string $setting_key The key of the setting.
     * @param string $setting_value The value to set.
     * @param int|null $company_id Optional company ID for company-specific settings. Null for system settings.
     * @param string|null $description Optional description for the setting.
     * @return bool True on success, false on failure.
     */
    public function set(string $setting_key, string $setting_value, ?int $company_id = null, ?string $description = null): bool {
        try {
            // Using INSERT ... ON DUPLICATE KEY UPDATE
            // The `settings` table should have a UNIQUE constraint on (company_id, setting_key)
            // For rows where company_id IS NULL, the unique constraint should effectively be on (setting_key)
            // if the DB handles NULLs in unique keys correctly (MySQL does, by treating NULLs as distinct unless all key parts are NULL).
            // The schema has `UNIQUE KEY unique_setting_key_per_company_or_system (company_id, setting_key)`
            // which should work for this.

            $sql = "INSERT INTO settings (setting_key, setting_value, company_id, description)
                    VALUES (:setting_key, :setting_value, :company_id, :description)
                    ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    description = VALUES(description)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':setting_key', $setting_key);
            $stmt->bindParam(':setting_value', $setting_value);
            $stmt->bindParam(':company_id', $company_id, ($company_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT));
            $stmt->bindParam(':description', $description, ($description === null ? PDO::PARAM_NULL : PDO::PARAM_STR));

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Settings::set Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetches all settings for a specific company or all system-wide settings.
     *
     * @param int|null $company_id Company ID or null for system settings.
     * @return array Associative array [key => value].
     */
    public function getAllByCompany(?int $company_id = null): array {
        $settings = [];
        try {
            if ($company_id !== null) {
                $sql = "SELECT setting_key, setting_value FROM settings WHERE company_id = :company_id";
            } else {
                $sql = "SELECT setting_key, setting_value FROM settings WHERE company_id IS NULL";
            }
            $stmt = $this->db->prepare($sql);
            if ($company_id !== null) {
                $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            }
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($results as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            error_log("Settings::getAllByCompany Error: " . $e->getMessage());
        }
        return $settings;
    }

    /**
     * Deletes a specific setting.
     *
     * @param string $setting_key The key of the setting to delete.
     * @param int|null $company_id Optional company ID. If null, deletes a system setting.
     * @return bool True on success, false on failure.
     */
    public function delete(string $setting_key, ?int $company_id = null): bool {
        try {
            if ($company_id !== null) {
                $sql = "DELETE FROM settings WHERE setting_key = :setting_key AND company_id = :company_id";
            } else {
                $sql = "DELETE FROM settings WHERE setting_key = :setting_key AND company_id IS NULL";
            }
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':setting_key', $setting_key);
            if ($company_id !== null) {
                $stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            }
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Settings::delete Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
