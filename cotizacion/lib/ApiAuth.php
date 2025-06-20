<?php
// cotizacion/lib/ApiAuth.php

class ApiAuth {
    private $db;

    public function __construct() {
        $this->db = getDBConnection(); // Assumes getDBConnection() is available
    }

    private function jsonResponse(int $statusCode, array $data): void {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    public function authenticate(callable $handlerFunction): void {
        $token = null;
        $headers = getallheaders();

        if (isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
        }

        if (!$token) {
            $this->jsonResponse(401, ['error' => 'Acceso no autorizado. Token no proporcionado.']);
        }

        try {
            $sql = "SELECT t.user_id, t.permissions, t.token, u.company_id, u.is_active as user_is_active
                    FROM api_tokens t
                    JOIN users u ON t.user_id = u.id
                    WHERE t.token = :token";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':token', $token);
            $stmt->execute();

            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tokenData) {
                $this->jsonResponse(401, ['error' => 'Acceso no autorizado. Token inválido.']);
            }

            if (!$tokenData['user_is_active']) {
                $this->jsonResponse(403, ['error' => 'Acceso prohibido. La cuenta de usuario está inactiva.']);
            }

            // Optional: Check token expiry if `expires_at` is implemented
            // if ($tokenData['expires_at'] && strtotime($tokenData['expires_at']) < time()) {
            //     $this->jsonResponse(401, ['error' => 'Acceso no autorizado. El token ha expirado.']);
            // }

            // Update last_used_at
            $updateSql = "UPDATE api_tokens SET last_used_at = NOW() WHERE token = :token";
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->bindParam(':token', $token);
            $updateStmt->execute();

            $user = [
                'id' => (int)$tokenData['user_id'],
                'company_id' => (int)$tokenData['company_id']
                // Add other user details if needed by the handler, but keep it minimal
            ];
            $permissions = json_decode($tokenData['permissions'] ?? '[]', true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $permissions = []; // Default to no permissions if JSON is invalid
                error_log("API Auth: Invalid JSON in permissions for token: " . $token);
            }

            // Call the handler function with user data and permissions
            $handlerFunction($user, $permissions);

        } catch (PDOException $e) {
            error_log("ApiAuth::authenticate PDOException: " . $e->getMessage());
            $this->jsonResponse(500, ['error' => 'Error interno del servidor durante la autenticación.']);
        }
    }

    /**
     * Checks if the user's permissions array contains the required permission.
     *
     * @param array $userPermissions Array of permissions granted to the user (e.g., from token).
     * @param string $requiredPermission The permission string to check for (e.g., "products:read").
     * @return bool True if the user has the permission, false otherwise.
     */
    public function checkPermissions(array $userPermissions, string $requiredPermission): bool {
        if (empty($requiredPermission)) {
            return true; // No specific permission required
        }
        if (in_array('*', $userPermissions)) { // Wildcard permission
            return true;
        }
        return in_array($requiredPermission, $userPermissions);
    }
}
?>
