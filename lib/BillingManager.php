<?php
/**
 * BillingManager Class
 * Manages the billing workflow for accepted quotations
 */

class BillingManager {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    /**
     * Vendedor: Marcar cotización como pendiente de facturación
     * Si es a crédito y no está aprobado, redirige a CreditManager
     */
    public function requestBilling($quotationId, $sellerId, $companyId, $observations = null) {
        try {
            $this->db->beginTransaction();

            // Verificar que la cotización existe y está aceptada
            $stmt = $this->db->prepare("
                SELECT id, status, user_id, payment_condition, credit_status
                FROM quotations
                WHERE id = ? AND company_id = ? AND status = 'Accepted'
            ");
            $stmt->execute([$quotationId, $companyId]);
            $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$quotation) {
                throw new Exception("Cotización no encontrada o no está aceptada");
            }

            // Verificar que el vendedor es el dueño de la cotización
            if ($quotation['user_id'] != $sellerId) {
                throw new Exception("No tiene permisos para solicitar facturación de esta cotización");
            }

            // Si es a crédito y no está aprobado, redirigir a CreditManager
            if ($quotation['payment_condition'] === 'credit' && $quotation['credit_status'] !== 'Credit_Approved') {
                $this->db->rollBack();
                $creditManager = new CreditManager();
                return $creditManager->requestCreditApproval($quotationId, $sellerId, $companyId, $observations);
            }

            // Verificar que no existe una solicitud pendiente
            $stmt = $this->db->prepare("
                SELECT id FROM quotation_billing_tracking
                WHERE quotation_id = ? AND status IN ('Pending', 'In_Process')
            ");
            $stmt->execute([$quotationId]);
            if ($stmt->fetch()) {
                throw new Exception("Ya existe una solicitud de facturación pendiente para esta cotización");
            }

            // Actualizar estado de facturación en la cotización
            $stmt = $this->db->prepare("
                UPDATE quotations
                SET billing_status = 'Pending_Invoice'
                WHERE id = ?
            ");
            $stmt->execute([$quotationId]);

            // Crear registro de seguimiento
            $stmt = $this->db->prepare("
                INSERT INTO quotation_billing_tracking
                (quotation_id, company_id, seller_id, status, observations, requested_at)
                VALUES (?, ?, ?, 'Pending', ?, NOW())
            ");
            $stmt->execute([$quotationId, $companyId, $sellerId, $observations]);
            $trackingId = $this->db->lastInsertId();

            // Registrar en historial
            $this->addHistory($trackingId, $quotationId, $sellerId, 'requested', null, 'Pending', 'Solicitud de facturación enviada');

            // Crear notificación para usuarios de facturación
            $this->notifyBillingUsers($quotationId, $companyId, $sellerId);

            $this->db->commit();
            return ['success' => true, 'tracking_id' => $trackingId];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in requestBilling: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Facturador: Aprobar y facturar cotización
     */
    public function approveBilling($trackingId, $billingUserId, $invoiceNumber, $observations = null) {
        try {
            $this->db->beginTransaction();

            // Obtener información del tracking
            $stmt = $this->db->prepare("
                SELECT t.*, q.quotation_number
                FROM quotation_billing_tracking t
                JOIN quotations q ON t.quotation_id = q.id
                WHERE t.id = ? AND t.status IN ('Pending', 'In_Process')
            ");
            $stmt->execute([$trackingId]);
            $tracking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tracking) {
                throw new Exception("Solicitud no encontrada o ya fue procesada");
            }

            // Actualizar tracking
            $stmt = $this->db->prepare("
                UPDATE quotation_billing_tracking
                SET billing_user_id = ?,
                    status = 'Invoiced',
                    invoice_number = ?,
                    observations = ?,
                    processed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$billingUserId, $invoiceNumber, $observations, $trackingId]);

            // Actualizar cotización
            $stmt = $this->db->prepare("
                UPDATE quotations
                SET billing_status = 'Invoiced',
                    invoice_number = ?,
                    invoiced_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$invoiceNumber, $tracking['quotation_id']]);

            // Registrar en historial
            $this->addHistory($trackingId, $tracking['quotation_id'], $billingUserId, 'invoiced',
                $tracking['status'], 'Invoiced', "Facturado con número: $invoiceNumber");

            // Notificar al vendedor
            $this->notifySeller($tracking['quotation_id'], $tracking['seller_id'], $tracking['company_id'],
                'approved', $invoiceNumber);

            $this->db->commit();
            return ['success' => true, 'message' => 'Cotización facturada exitosamente'];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in approveBilling: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Facturador: Rechazar solicitud de facturación
     */
    public function rejectBilling($trackingId, $billingUserId, $rejectionReason) {
        try {
            $this->db->beginTransaction();

            // Obtener información del tracking
            $stmt = $this->db->prepare("
                SELECT t.*, q.quotation_number
                FROM quotation_billing_tracking t
                JOIN quotations q ON t.quotation_id = q.id
                WHERE t.id = ? AND t.status IN ('Pending', 'In_Process')
            ");
            $stmt->execute([$trackingId]);
            $tracking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tracking) {
                throw new Exception("Solicitud no encontrada o ya fue procesada");
            }

            // Actualizar tracking
            $stmt = $this->db->prepare("
                UPDATE quotation_billing_tracking
                SET billing_user_id = ?,
                    status = 'Rejected',
                    rejection_reason = ?,
                    processed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$billingUserId, $rejectionReason, $trackingId]);

            // Actualizar cotización
            $stmt = $this->db->prepare("
                UPDATE quotations
                SET billing_status = 'Invoice_Rejected'
                WHERE id = ?
            ");
            $stmt->execute([$tracking['quotation_id']]);

            // Registrar en historial
            $this->addHistory($trackingId, $tracking['quotation_id'], $billingUserId, 'rejected',
                $tracking['status'], 'Rejected', $rejectionReason);

            // Notificar al vendedor
            $this->notifySeller($tracking['quotation_id'], $tracking['seller_id'], $tracking['company_id'],
                'rejected', null, $rejectionReason);

            $this->db->commit();
            return ['success' => true, 'message' => 'Solicitud rechazada'];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in rejectBilling: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Obtener solicitudes pendientes de facturación
     */
    public function getPendingBillingRequests($companyId, $page = 1, $perPage = 20) {
        try {
            $offset = ($page - 1) * $perPage;

            $stmt = $this->db->prepare("
                SELECT
                    t.*,
                    q.quotation_number,
                    q.quotation_date,
                    q.total,
                    q.currency,
                    c.name as customer_name,
                    u.username as seller_name,
                    u.email as seller_email
                FROM quotation_billing_tracking t
                JOIN quotations q ON t.quotation_id = q.id
                JOIN customers c ON q.customer_id = c.id
                JOIN users u ON t.seller_id = u.id
                WHERE t.company_id = ? AND t.status IN ('Pending', 'In_Process')
                ORDER BY t.requested_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$companyId, $perPage, $offset]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getPendingBillingRequests: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener historial de facturación de un vendedor
     */
    public function getSellerBillingHistory($sellerId, $companyId, $page = 1, $perPage = 20) {
        try {
            $offset = ($page - 1) * $perPage;

            $stmt = $this->db->prepare("
                SELECT
                    t.*,
                    q.quotation_number,
                    q.quotation_date,
                    q.total,
                    q.currency,
                    c.name as customer_name,
                    bu.username as billing_user_name
                FROM quotation_billing_tracking t
                JOIN quotations q ON t.quotation_id = q.id
                JOIN customers c ON q.customer_id = c.id
                LEFT JOIN users bu ON t.billing_user_id = bu.id
                WHERE t.seller_id = ? AND t.company_id = ?
                ORDER BY t.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$sellerId, $companyId, $perPage, $offset]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getSellerBillingHistory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todas las solicitudes para administrador
     */
    public function getAllBillingRequests($companyId, $status = null, $page = 1, $perPage = 20) {
        try {
            $offset = ($page - 1) * $perPage;

            $where = "t.company_id = ?";
            $params = [$companyId];

            if ($status) {
                $where .= " AND t.status = ?";
                $params[] = $status;
            }

            $stmt = $this->db->prepare("
                SELECT
                    t.*,
                    q.quotation_number,
                    q.quotation_date,
                    q.total,
                    q.currency,
                    c.name as customer_name,
                    u.username as seller_name,
                    bu.username as billing_user_name
                FROM quotation_billing_tracking t
                JOIN quotations q ON t.quotation_id = q.id
                JOIN customers c ON q.customer_id = c.id
                JOIN users u ON t.seller_id = u.id
                LEFT JOIN users bu ON t.billing_user_id = bu.id
                WHERE $where
                ORDER BY t.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getAllBillingRequests: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Registrar en historial
     */
    private function addHistory($trackingId, $quotationId, $userId, $action, $previousStatus, $newStatus, $observations) {
        $stmt = $this->db->prepare("
            INSERT INTO quotation_billing_history
            (tracking_id, quotation_id, user_id, action, previous_status, new_status, observations)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$trackingId, $quotationId, $userId, $action, $previousStatus, $newStatus, $observations]);
    }

    /**
     * Notificar a usuarios de facturación
     */
    private function notifyBillingUsers($quotationId, $companyId, $sellerId) {
        // Obtener información de la cotización
        $stmt = $this->db->prepare("
            SELECT q.quotation_number, c.name as customer_name, u.username as seller_name
            FROM quotations q
            JOIN customers c ON q.customer_id = c.id
            JOIN users u ON q.user_id = u.id
            WHERE q.id = ?
        ");
        $stmt->execute([$quotationId]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info) {
            return;
        }

        // Obtener usuarios con rol de facturación
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.id
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.role_name = 'Facturación' AND u.company_id = ? AND u.id != ?
        ");
        $stmt->execute([$companyId, $sellerId]);
        $billingUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Crear notificación para cada usuario de facturación
        $notification = new Notification();
        foreach ($billingUsers as $userId) {
            $notification->create(
                $userId,
                $companyId,
                'billing_request',
                '📋 Nueva Solicitud de Facturación',
                "El vendedor {$info['seller_name']} solicita facturar la cotización {$info['quotation_number']} del cliente {$info['customer_name']}",
                $quotationId,
                BASE_URL . "/billing/pending.php?quotation_id=$quotationId"
            );
        }

        // Notificar a administradores
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.id
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.role_name IN ('Administrador del Sistema', 'Administrador de Empresa') AND u.company_id = ?
        ");
        $stmt->execute([$companyId]);
        $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($admins as $userId) {
            $notification->create(
                $userId,
                $companyId,
                'billing_request',
                '📋 Nueva Solicitud de Facturación',
                "Solicitud de facturación para cotización {$info['quotation_number']}",
                $quotationId,
                BASE_URL . "/billing/admin_view.php"
            );
        }
    }

    /**
     * Notificar al vendedor
     */
    private function notifySeller($quotationId, $sellerId, $companyId, $action, $invoiceNumber = null, $reason = null) {
        $stmt = $this->db->prepare("SELECT quotation_number FROM quotations WHERE id = ?");
        $stmt->execute([$quotationId]);
        $quotationNumber = $stmt->fetchColumn();

        $notification = new Notification();

        if ($action === 'approved') {
            $notification->create(
                $sellerId,
                $companyId,
                'billing_approved',
                '✅ Cotización Facturada',
                "Su cotización $quotationNumber ha sido facturada con número: $invoiceNumber",
                BASE_URL . "/quotations/view.php?id=$quotationId"
            );
        } elseif ($action === 'rejected') {
            $notification->create(
                $sellerId,
                $companyId,
                'billing_rejected',
                '❌ Solicitud de Facturación Rechazada',
                "Su solicitud de facturación para $quotationNumber fue rechazada. Motivo: $reason",
                BASE_URL . "/quotations/view.php?id=$quotationId"
            );
        }
    }

    /**
     * Obtener estadísticas de facturación
     */
    public function getBillingStats($companyId, $userId = null, $role = null) {
        try {
            $stats = [
                'pending' => 0,
                'in_process' => 0,
                'invoiced' => 0,
                'rejected' => 0,
                'total' => 0
            ];

            $where = "company_id = ?";
            $params = [$companyId];

            // Si es vendedor, solo sus solicitudes
            if ($role === 'seller' && $userId) {
                $where .= " AND seller_id = ?";
                $params[] = $userId;
            }

            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as count
                FROM quotation_billing_tracking
                WHERE $where
                GROUP BY status
            ");
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($results as $row) {
                $key = strtolower($row['status']);
                $stats[$key] = (int)$row['count'];
                $stats['total'] += (int)$row['count'];
            }

            return $stats;

        } catch (Exception $e) {
            error_log("Error in getBillingStats: " . $e->getMessage());
            return ['pending' => 0, 'in_process' => 0, 'invoiced' => 0, 'rejected' => 0, 'total' => 0];
        }
    }
}
