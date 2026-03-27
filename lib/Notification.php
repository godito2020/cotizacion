<?php

class Notification {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function create($userId, $companyId, $type, $title, $message, $relatedId = null, $relatedUrl = null) {
        try {
            $sql = "INSERT INTO notifications (user_id, company_id, type, title, message, related_id, related_url)
                    VALUES (:user_id, :company_id, :type, :title, :message, :related_id, :related_url)";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'company_id' => $companyId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'related_id' => $relatedId,
                'related_url' => $relatedUrl
            ]);
        } catch (PDOException $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }

    public function getUserNotifications($userId, $companyId, $limit = 50, $offset = 0) {
        try {
            $sql = "SELECT * FROM notifications
                    WHERE user_id = :user_id AND company_id = :company_id
                    ORDER BY created_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user notifications: " . $e->getMessage());
            return [];
        }
    }

    public function getUnreadCount($userId, $companyId) {
        try {
            $sql = "SELECT COUNT(*) FROM notifications
                    WHERE user_id = :user_id AND company_id = :company_id AND read_at IS NULL";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'company_id' => $companyId
            ]);

            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error getting unread count: " . $e->getMessage());
            return 0;
        }
    }

    public function getLatestNotifications($userId, $companyId, $limit = 5) {
        try {
            $sql = "SELECT * FROM notifications
                    WHERE user_id = :user_id AND company_id = :company_id
                    ORDER BY created_at DESC
                    LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting latest notifications: " . $e->getMessage());
            return [];
        }
    }

    public function markAsRead($notificationId, $userId) {
        try {
            $sql = "UPDATE notifications SET read_at = CURRENT_TIMESTAMP
                    WHERE id = :id AND user_id = :user_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'id' => $notificationId,
                'user_id' => $userId
            ]);
        } catch (PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }

    public function markAllAsRead($userId, $companyId) {
        try {
            $sql = "UPDATE notifications SET read_at = CURRENT_TIMESTAMP
                    WHERE user_id = :user_id AND company_id = :company_id AND read_at IS NULL";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'company_id' => $companyId
            ]);
        } catch (PDOException $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }

    // Static helper methods for common notifications
    public static function notifyQuotationCreated($userId, $companyId, $quotationId, $quotationNumber) {
        $notification = new self();
        return $notification->create(
            $userId,
            $companyId,
            'quotation_created',
            'Nueva cotización creada',
            "Se ha creado la cotización {$quotationNumber}",
            $quotationId,
            "/quotations/view.php?id={$quotationId}"
        );
    }

    public static function notifyQuotationStatusChange($userId, $companyId, $quotationId, $quotationNumber, $status) {
        $notification = new self();
        $statusNames = [
            'Sent' => 'enviada',
            'Accepted' => 'aceptada',
            'Rejected' => 'rechazada',
            'Invoiced' => 'facturada'
        ];

        $statusName = $statusNames[$status] ?? $status;

        return $notification->create(
            $userId,
            $companyId,
            'quotation_' . strtolower($status),
            "Cotización {$statusName}",
            "La cotización {$quotationNumber} ha sido {$statusName}",
            $quotationId,
            "/quotations/view.php?id={$quotationId}"
        );
    }

    public static function notifyLowStock($userId, $companyId, $productId, $productName, $currentStock) {
        $notification = new self();
        return $notification->create(
            $userId,
            $companyId,
            'low_stock',
            'Stock bajo detectado',
            "El producto {$productName} tiene stock bajo: {$currentStock} unidades",
            $productId,
            "/products/index.php"
        );
    }

    public static function notifyNewCustomer($userId, $companyId, $customerId, $customerName) {
        $notification = new self();
        return $notification->create(
            $userId,
            $companyId,
            'customer_created',
            'Nuevo cliente registrado',
            "Se ha registrado el cliente {$customerName}",
            $customerId,
            "/customers/view.php?id={$customerId}"
        );
    }
}
?>