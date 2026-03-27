<?php
/**
 * CreditManager Class
 * Manages the credit approval workflow for quotations with credit payment condition
 */

class CreditManager {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    /**
     * Vendedor: Solicitar aprobación de crédito
     */
    public function requestCreditApproval($quotationId, $sellerId, $companyId, $observations = null) {
        try {
            $this->db->beginTransaction();

            // Verificar que la cotización existe, está aceptada y es a crédito
            $stmt = $this->db->prepare("
                SELECT id, status, user_id, payment_condition, credit_days, total, currency, customer_id
                FROM quotations
                WHERE id = ? AND company_id = ? AND status = 'Accepted'
            ");
            $stmt->execute([$quotationId, $companyId]);
            $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$quotation) {
                throw new Exception("Cotización no encontrada o no está aceptada");
            }

            // Verificar que es a crédito
            if ($quotation['payment_condition'] !== 'credit') {
                throw new Exception("Esta cotización no es a crédito");
            }

            // Verificar que el vendedor es el dueño de la cotización
            if ($quotation['user_id'] != $sellerId) {
                throw new Exception("No tiene permisos para solicitar aprobación de crédito para esta cotización");
            }

            // Verificar que no existe una solicitud pendiente
            $stmt = $this->db->prepare("
                SELECT id FROM quotation_credit_tracking
                WHERE quotation_id = ? AND status = 'Pending'
            ");
            $stmt->execute([$quotationId]);
            if ($stmt->fetch()) {
                throw new Exception("Ya existe una solicitud de crédito pendiente para esta cotización");
            }

            // Actualizar estado de crédito en la cotización
            $stmt = $this->db->prepare("
                UPDATE quotations
                SET credit_status = 'Pending_Credit'
                WHERE id = ?
            ");
            $stmt->execute([$quotationId]);

            // Crear registro de seguimiento
            $stmt = $this->db->prepare("
                INSERT INTO quotation_credit_tracking
                (quotation_id, company_id, seller_id, status, credit_days, total_amount, currency, observations, requested_at)
                VALUES (?, ?, ?, 'Pending', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $quotationId,
                $companyId,
                $sellerId,
                $quotation['credit_days'],
                $quotation['total'],
                $quotation['currency'],
                $observations
            ]);
            $trackingId = $this->db->lastInsertId();

            // Registrar en historial
            $this->addHistory($trackingId, $quotationId, $sellerId, 'requested', null, 'Pending', 'Solicitud de aprobación de crédito enviada');

            // Crear notificación para usuarios de créditos
            $this->notifyCreditUsers($quotationId, $companyId, $sellerId);

            $this->db->commit();
            return ['success' => true, 'tracking_id' => $trackingId];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in requestCreditApproval: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Créditos: Aprobar crédito y pasar automáticamente a facturación
     */
    public function approveCredit($trackingId, $creditUserId, $observations = null) {
        try {
            $this->db->beginTransaction();

            // Obtener información del tracking
            $stmt = $this->db->prepare("
                SELECT t.*, q.quotation_number, q.customer_id, q.total, q.currency
                FROM quotation_credit_tracking t
                JOIN quotations q ON t.quotation_id = q.id
                WHERE t.id = ? AND t.status = 'Pending'
            ");
            $stmt->execute([$trackingId]);
            $tracking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tracking) {
                throw new Exception("Solicitud no encontrada o ya fue procesada");
            }

            // Actualizar tracking
            $stmt = $this->db->prepare("
                UPDATE quotation_credit_tracking
                SET credit_user_id = ?,
                    status = 'Approved',
                    observations = ?,
                    processed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$creditUserId, $observations, $trackingId]);

            // Actualizar cotización
            $stmt = $this->db->prepare("
                UPDATE quotations
                SET credit_status = 'Credit_Approved',
                    billing_status = 'Pending_Invoice'
                WHERE id = ?
            ");
            $stmt->execute([$tracking['quotation_id']]);

            // Registrar en historial
            $this->addHistory($trackingId, $tracking['quotation_id'], $creditUserId, 'approved',
                'Pending', 'Approved', $observations ?: 'Crédito aprobado');

            // Crear automáticamente la solicitud de facturación
            $this->createBillingRequest($tracking['quotation_id'], $tracking['company_id'], $tracking['seller_id']);

            // Notificar al vendedor
            $this->notifySeller($tracking['quotation_id'], $tracking['seller_id'], $tracking['company_id'],
                'approved', $observations);

            // Notificar a facturación
            $this->notifyBillingUsers($tracking['quotation_id'], $tracking['company_id']);

            $this->db->commit();
            return ['success' => true, 'message' => 'Crédito aprobado y enviado a facturación'];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in approveCredit: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Créditos: Rechazar crédito
     */
    public function rejectCredit($trackingId, $creditUserId, $rejectionReason) {
        try {
            $this->db->beginTransaction();

            // Obtener información del tracking
            $stmt = $this->db->prepare("
                SELECT t.*, q.quotation_number
                FROM quotation_credit_tracking t
                JOIN quotations q ON t.quotation_id = q.id
                WHERE t.id = ? AND t.status = 'Pending'
            ");
            $stmt->execute([$trackingId]);
            $tracking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tracking) {
                throw new Exception("Solicitud no encontrada o ya fue procesada");
            }

            // Actualizar tracking
            $stmt = $this->db->prepare("
                UPDATE quotation_credit_tracking
                SET credit_user_id = ?,
                    status = 'Rejected',
                    rejection_reason = ?,
                    processed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$creditUserId, $rejectionReason, $trackingId]);

            // Actualizar cotización
            $stmt = $this->db->prepare("
                UPDATE quotations
                SET credit_status = 'Credit_Rejected'
                WHERE id = ?
            ");
            $stmt->execute([$tracking['quotation_id']]);

            // Registrar en historial
            $this->addHistory($trackingId, $tracking['quotation_id'], $creditUserId, 'rejected',
                'Pending', 'Rejected', $rejectionReason);

            // Notificar al vendedor
            $this->notifySeller($tracking['quotation_id'], $tracking['seller_id'], $tracking['company_id'],
                'rejected', $rejectionReason);

            $this->db->commit();
            return ['success' => true, 'message' => 'Solicitud de crédito rechazada'];

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in rejectCredit: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Obtener solicitudes pendientes de crédito
     */
    public function getPendingCreditRequests($companyId, $page = 1, $perPage = 20) {
        try {
            $offset = ($page - 1) * $perPage;

            $stmt = $this->db->prepare("
                SELECT
                    t.id,
                    t.quotation_id,
                    t.company_id,
                    t.seller_id,
                    t.credit_user_id,
                    t.status,
                    t.credit_days,
                    t.total_amount,
                    t.currency,
                    t.observations,
                    t.rejection_reason,
                    t.requested_at,
                    t.processed_at,
                    t.created_at,
                    q.quotation_number,
                    q.quotation_date,
                    q.total,
                    q.currency as q_currency,
                    q.credit_days as q_credit_days,
                    q.payment_condition,
                    c.name as customer_name,
                    c.tax_id as customer_tax_id,
                    c.email as customer_email,
                    c.phone as customer_phone,
                    u.username as seller_name,
                    u.email as seller_email
                FROM quotation_credit_tracking t
                JOIN quotations q ON t.quotation_id = q.id
                JOIN customers c ON q.customer_id = c.id
                JOIN users u ON t.seller_id = u.id
                WHERE t.company_id = ? AND t.status = 'Pending'
                ORDER BY t.requested_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$companyId, $perPage, $offset]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getPendingCreditRequests: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener historial de créditos de un vendedor
     */
    public function getSellerCreditHistory($sellerId, $companyId, $page = 1, $perPage = 20) {
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
                    cu.username as credit_user_name
                FROM quotation_credit_tracking t
                JOIN quotations q ON t.quotation_id = q.id
                JOIN customers c ON q.customer_id = c.id
                LEFT JOIN users cu ON t.credit_user_id = cu.id
                WHERE t.seller_id = ? AND t.company_id = ?
                ORDER BY t.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$sellerId, $companyId, $perPage, $offset]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getSellerCreditHistory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener todas las solicitudes para administrador
     */
    public function getAllCreditRequests($companyId, $status = null, $page = 1, $perPage = 20) {
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
                    q.credit_days,
                    c.name as customer_name,
                    u.username as seller_name,
                    cu.username as credit_user_name
                FROM quotation_credit_tracking t
                JOIN quotations q ON t.quotation_id = q.id
                JOIN customers c ON q.customer_id = c.id
                JOIN users u ON t.seller_id = u.id
                LEFT JOIN users cu ON t.credit_user_id = cu.id
                WHERE $where
                ORDER BY t.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getAllCreditRequests: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener historial de cotizaciones del cliente
     */
    public function getCustomerHistory($customerId, $companyId) {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    q.id,
                    q.quotation_number,
                    q.quotation_date,
                    q.total,
                    q.currency,
                    q.status,
                    q.billing_status,
                    q.credit_status,
                    q.payment_condition,
                    q.credit_days
                FROM quotations q
                WHERE q.customer_id = ? AND q.company_id = ?
                ORDER BY q.quotation_date DESC
                LIMIT 20
            ");
            $stmt->execute([$customerId, $companyId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getCustomerHistory: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener estadísticas de crédito
     */
    public function getCreditStats($companyId, $userId = null, $role = null) {
        try {
            $stats = [
                'pending' => 0,
                'approved' => 0,
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
                FROM quotation_credit_tracking
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
            error_log("Error in getCreditStats: " . $e->getMessage());
            return ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
        }
    }

    /**
     * Obtener detalle de una solicitud
     */
    public function getCreditRequest($trackingId) {
        try {
            // Query simplificada - solo columnas que existen en quotations
            $stmt = $this->db->prepare("
                SELECT
                    t.id,
                    t.quotation_id,
                    t.company_id,
                    t.seller_id,
                    t.credit_user_id,
                    t.status,
                    t.credit_days,
                    t.total_amount,
                    t.currency,
                    t.observations,
                    t.rejection_reason,
                    t.requested_at,
                    t.processed_at,
                    t.created_at,
                    q.quotation_number,
                    q.quotation_date,
                    q.total,
                    q.currency as q_currency,
                    q.credit_days as q_credit_days,
                    q.payment_condition,
                    q.customer_id,
                    c.name as customer_name,
                    c.tax_id as customer_tax_id,
                    c.email as customer_email,
                    c.phone as customer_phone,
                    c.address as customer_address,
                    u.username as seller_name,
                    u.email as seller_email,
                    cu.username as credit_user_name
                FROM quotation_credit_tracking t
                JOIN quotations q ON t.quotation_id = q.id
                LEFT JOIN customers c ON q.customer_id = c.id
                JOIN users u ON t.seller_id = u.id
                LEFT JOIN users cu ON t.credit_user_id = cu.id
                WHERE t.id = ?
            ");
            $stmt->execute([$trackingId]);

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getCreditRequest: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registrar en historial
     */
    private function addHistory($trackingId, $quotationId, $userId, $action, $previousStatus, $newStatus, $observations) {
        $stmt = $this->db->prepare("
            INSERT INTO quotation_credit_history
            (tracking_id, quotation_id, user_id, action, previous_status, new_status, observations)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$trackingId, $quotationId, $userId, $action, $previousStatus, $newStatus, $observations]);
    }

    /**
     * Crear solicitud de facturación automáticamente
     */
    private function createBillingRequest($quotationId, $companyId, $sellerId) {
        // Verificar si ya existe una solicitud de facturación
        $stmt = $this->db->prepare("
            SELECT id FROM quotation_billing_tracking
            WHERE quotation_id = ? AND status IN ('Pending', 'In_Process')
        ");
        $stmt->execute([$quotationId]);
        if ($stmt->fetch()) {
            return; // Ya existe
        }

        // Crear registro de facturación
        $stmt = $this->db->prepare("
            INSERT INTO quotation_billing_tracking
            (quotation_id, company_id, seller_id, status, observations, requested_at)
            VALUES (?, ?, ?, 'Pending', 'Crédito aprobado - Enviado automáticamente a facturación', NOW())
        ");
        $stmt->execute([$quotationId, $companyId, $sellerId]);
    }

    /**
     * Notificar a usuarios de créditos
     */
    private function notifyCreditUsers($quotationId, $companyId, $sellerId) {
        // Obtener información de la cotización
        $stmt = $this->db->prepare("
            SELECT q.quotation_number, q.total, q.currency, q.credit_days, c.name as customer_name, u.username as seller_name
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

        // Obtener usuarios con rol de créditos
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.id
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.role_name = 'Créditos y Cobranzas' AND u.company_id = ? AND u.id != ?
        ");
        $stmt->execute([$companyId, $sellerId]);
        $creditUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Crear notificación para cada usuario de créditos
        $notification = new Notification();
        $currencySymbol = $info['currency'] === 'USD' ? '$' : 'S/';
        foreach ($creditUsers as $userId) {
            $notification->create(
                $userId,
                $companyId,
                'credit_request',
                '💳 Nueva Solicitud de Crédito',
                "El vendedor {$info['seller_name']} solicita crédito de {$info['credit_days']} días para {$info['customer_name']} - {$currencySymbol}" . number_format($info['total'], 2),
                $quotationId,
                BASE_URL . "/credits/pending.php"
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
                'credit_request',
                '💳 Nueva Solicitud de Crédito',
                "Solicitud de crédito para cotización {$info['quotation_number']}",
                $quotationId,
                BASE_URL . "/credits/pending.php"
            );
        }
    }

    /**
     * Notificar al vendedor
     */
    private function notifySeller($quotationId, $sellerId, $companyId, $action, $reason = null) {
        $stmt = $this->db->prepare("SELECT quotation_number FROM quotations WHERE id = ?");
        $stmt->execute([$quotationId]);
        $quotationNumber = $stmt->fetchColumn();

        $notification = new Notification();

        if ($action === 'approved') {
            $notification->create(
                $sellerId,
                $companyId,
                'credit_approved',
                '✅ Crédito Aprobado',
                "El crédito para la cotización $quotationNumber ha sido aprobado y enviado a facturación",
                $quotationId,
                BASE_URL . "/quotations/view.php?id=$quotationId"
            );
        } elseif ($action === 'rejected') {
            $notification->create(
                $sellerId,
                $companyId,
                'credit_rejected',
                '❌ Crédito Rechazado',
                "El crédito para la cotización $quotationNumber fue rechazado. Motivo: $reason",
                $quotationId,
                BASE_URL . "/quotations/view.php?id=$quotationId"
            );
        }
    }

    /**
     * Notificar a usuarios de facturación
     */
    private function notifyBillingUsers($quotationId, $companyId) {
        $stmt = $this->db->prepare("
            SELECT q.quotation_number, c.name as customer_name
            FROM quotations q
            JOIN customers c ON q.customer_id = c.id
            WHERE q.id = ?
        ");
        $stmt->execute([$quotationId]);
        $info = $stmt->fetch(PDO::FETCH_ASSOC);

        // Obtener usuarios con rol de facturación
        $stmt = $this->db->prepare("
            SELECT DISTINCT u.id
            FROM users u
            JOIN user_roles ur ON u.id = ur.user_id
            JOIN roles r ON ur.role_id = r.id
            WHERE r.role_name = 'Facturación' AND u.company_id = ?
        ");
        $stmt->execute([$companyId]);
        $billingUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $notification = new Notification();
        foreach ($billingUsers as $userId) {
            $notification->create(
                $userId,
                $companyId,
                'billing_request',
                '📋 Nueva Solicitud de Facturación (Crédito Aprobado)',
                "Crédito aprobado para cotización {$info['quotation_number']} - Cliente: {$info['customer_name']}",
                $quotationId,
                BASE_URL . "/billing/pending.php"
            );
        }
    }

    /**
     * Verificar si una cotización requiere aprobación de crédito
     */
    public function requiresCreditApproval($quotationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT payment_condition, credit_status
                FROM quotations
                WHERE id = ?
            ");
            $stmt->execute([$quotationId]);
            $quotation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$quotation) {
                return false;
            }

            // Requiere aprobación si es a crédito y no ha sido aprobado
            return $quotation['payment_condition'] === 'credit' &&
                   $quotation['credit_status'] !== 'Credit_Approved';

        } catch (Exception $e) {
            error_log("Error in requiresCreditApproval: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener estado de crédito de una cotización
     */
    public function getCreditStatus($quotationId) {
        try {
            $stmt = $this->db->prepare("
                SELECT t.*, cu.username as credit_user_name
                FROM quotation_credit_tracking t
                LEFT JOIN users cu ON t.credit_user_id = cu.id
                WHERE t.quotation_id = ?
                ORDER BY t.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$quotationId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error in getCreditStatus: " . $e->getMessage());
            return false;
        }
    }
}
