<?php

class ActivityLog {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function log($userId, $companyId, $action, $entityType, $entityId, $description, $details = null) {
        try {
            $sql = "INSERT INTO activity_logs
                    (user_id, company_id, action, entity_type, entity_id, description, details, ip_address, user_agent)
                    VALUES (:user_id, :company_id, :action, :entity_type, :entity_id, :description, :details, :ip_address, :user_agent)";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'company_id' => $companyId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $description,
                'details' => $details ? json_encode($details) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
            return false;
        }
    }

    public function getActivities($companyId, $filterType = 'all', $filterUser = 'all', $limit = 20, $offset = 0) {
        try {
            $sql = "SELECT al.*,
                           CONCAT(u.first_name, ' ', u.last_name) as user_name
                    FROM activity_logs al
                    JOIN users u ON al.user_id = u.id
                    WHERE al.company_id = :company_id";

            $params = ['company_id' => $companyId];

            if ($filterType !== 'all') {
                $sql .= " AND al.entity_type = :entity_type";
                $params['entity_type'] = $filterType;
            }

            if ($filterUser !== 'all') {
                $sql .= " AND al.user_id = :user_id";
                $params['user_id'] = $filterUser;
            }

            $sql .= " ORDER BY al.created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);

            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting activities: " . $e->getMessage());
            return [];
        }
    }

    public function getActivitiesCount($companyId, $filterType = 'all', $filterUser = 'all') {
        try {
            $sql = "SELECT COUNT(*) FROM activity_logs WHERE company_id = :company_id";
            $params = ['company_id' => $companyId];

            if ($filterType !== 'all') {
                $sql .= " AND entity_type = :entity_type";
                $params['entity_type'] = $filterType;
            }

            if ($filterUser !== 'all') {
                $sql .= " AND user_id = :user_id";
                $params['user_id'] = $filterUser;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting activities count: " . $e->getMessage());
            return 0;
        }
    }

    // Static helper methods for common activities
    public static function logQuotationActivity($userId, $companyId, $action, $quotationId, $quotationNumber, $details = null) {
        $activityLog = new self();

        $descriptions = [
            'create' => "Creó la cotización {$quotationNumber}",
            'update' => "Actualizó la cotización {$quotationNumber}",
            'delete' => "Eliminó la cotización {$quotationNumber}",
            'duplicate' => "Duplicó la cotización {$quotationNumber}",
            'view' => "Visualizó la cotización {$quotationNumber}",
            'export' => "Exportó la cotización {$quotationNumber}"
        ];

        $description = $descriptions[$action] ?? "Acción {$action} en cotización {$quotationNumber}";

        return $activityLog->log(
            $userId,
            $companyId,
            $action,
            'quotation',
            $quotationId,
            $description,
            $details
        );
    }

    public static function logCustomerActivity($userId, $companyId, $action, $customerId, $customerName, $details = null) {
        $activityLog = new self();

        $descriptions = [
            'create' => "Registró el cliente {$customerName}",
            'update' => "Actualizó los datos del cliente {$customerName}",
            'delete' => "Eliminó el cliente {$customerName}",
            'view' => "Consultó los datos del cliente {$customerName}"
        ];

        $description = $descriptions[$action] ?? "Acción {$action} en cliente {$customerName}";

        return $activityLog->log(
            $userId,
            $companyId,
            $action,
            'customer',
            $customerId,
            $description,
            $details
        );
    }

    public static function logProductActivity($userId, $companyId, $action, $productId, $productName, $details = null) {
        $activityLog = new self();

        $descriptions = [
            'create' => "Agregó el producto {$productName}",
            'update' => "Actualizó el producto {$productName}",
            'delete' => "Eliminó el producto {$productName}",
            'import' => "Importó datos del producto {$productName}",
            'stock_update' => "Actualizó el stock del producto {$productName}"
        ];

        $description = $descriptions[$action] ?? "Acción {$action} en producto {$productName}";

        return $activityLog->log(
            $userId,
            $companyId,
            $action,
            'product',
            $productId,
            $description,
            $details
        );
    }

    public static function logSystemActivity($userId, $companyId, $action, $description, $details = null) {
        $activityLog = new self();

        return $activityLog->log(
            $userId,
            $companyId,
            $action,
            'system',
            null,
            $description,
            $details
        );
    }

    public static function logUserActivity($userId, $companyId, $action, $details = null) {
        $activityLog = new self();

        $descriptions = [
            'login' => "Inició sesión en el sistema",
            'logout' => "Cerró sesión en el sistema",
            'password_change' => "Cambió su contraseña",
            'profile_update' => "Actualizó su perfil"
        ];

        $description = $descriptions[$action] ?? "Acción de usuario: {$action}";

        return $activityLog->log(
            $userId,
            $companyId,
            $action,
            'user',
            $userId,
            $description,
            $details
        );
    }
}
?>