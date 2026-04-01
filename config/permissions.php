<?php
/**
 * Sistema de Permisos por Rol
 * Define qué puede hacer cada rol en el sistema
 */

class Permissions {

    /**
     * Definición de permisos por rol
     */
    private static $rolePermissions = [
        'Administrador del Sistema' => [
            // Acceso total
            'admin_panel' => true,
            'manage_companies' => true,
            'manage_all_users' => true,
            'manage_api_settings' => true,
            'manage_email_settings' => true,
            'view_system_reports' => true,
            'manage_system_settings' => true,
            'view_all_quotations' => true,
            'manage_quotations' => true,
            'manage_customers' => true,
            'manage_products' => true,
            'manage_warehouses' => true,
            'manage_bank_accounts' => true,
            'billing_panel' => true,
            'cost_analysis_panel' => true,
        ],

        'Administrador de Empresa' => [
            'admin_panel' => true,
            'manage_companies' => false,
            'manage_company_users' => true, // Solo de su empresa
            'manage_api_settings' => false,
            'manage_email_settings' => false,
            'view_company_reports' => true,
            'manage_company_settings' => true,
            'view_all_quotations' => true, // De su empresa
            'manage_quotations' => true,
            'manage_customers' => true,
            'manage_products' => true,
            'manage_warehouses' => true,
            'manage_bank_accounts' => true,
            'billing_panel' => false,
            'cost_analysis_panel' => true,
        ],

        'Vendedor' => [
            'admin_panel' => false,
            'manage_companies' => false,
            'manage_users' => false,
            'manage_api_settings' => false,
            'manage_email_settings' => false,
            'view_reports' => false,
            'manage_settings' => false,
            'view_own_quotations' => true,
            'manage_quotations' => true,
            'manage_customers' => true,
            'view_products' => true, // Solo lectura
            'manage_products' => false,
            'manage_warehouses' => false,
            'manage_bank_accounts' => false,
            'billing_panel' => false,
            'request_billing' => true, // Puede solicitar facturación
            'view_billing_history' => true, // Su propio historial
        ],

        'Facturación' => [
            'admin_panel' => false,
            'manage_companies' => false,
            'manage_users' => false,
            'manage_api_settings' => false,
            'manage_email_settings' => false,
            'view_reports' => false,
            'manage_settings' => false,
            'view_quotations_readonly' => true, // Solo lectura de cotizaciones relacionadas
            'manage_quotations' => false,
            'manage_customers' => false,
            'view_products' => true,
            'manage_products' => false,
            'manage_warehouses' => false,
            'manage_bank_accounts' => false,
            'billing_panel' => true,
            'process_billing' => true,
            'view_billing_requests' => true,
        ],

        'Créditos y Cobranzas' => [
            'admin_panel' => false,
            'manage_companies' => false,
            'manage_users' => false,
            'manage_api_settings' => false,
            'manage_email_settings' => false,
            'view_reports' => false,
            'manage_settings' => false,
            'view_quotations_readonly' => true, // Solo lectura de cotizaciones
            'manage_quotations' => false,
            'manage_customers' => true, // Ver info de clientes para historial
            'view_products' => true,
            'manage_products' => false,
            'manage_warehouses' => false,
            'manage_bank_accounts' => false,
            'billing_panel' => false,
            'credits_panel' => true, // Panel de créditos
            'process_credits' => true, // Aprobar/rechazar créditos
            'view_credit_requests' => true, // Ver solicitudes
            'view_customer_history' => true, // Ver historial de cliente
        ],

        'Supervisor Inventario' => [
            'admin_panel' => false,
            'manage_companies' => false,
            'manage_users' => false,
            'manage_api_settings' => false,
            'manage_email_settings' => false,
            'view_reports' => false,
            'manage_settings' => false,
            'view_quotations_readonly' => false,
            'manage_quotations' => false,
            'manage_customers' => false,
            'view_products' => true,
            'manage_products' => false,
            'manage_warehouses' => false,
            'manage_bank_accounts' => false,
            'billing_panel' => false,
            // Permisos de inventario
            'inventory_panel' => true,
            'inventory_admin' => true,
            'inventory_view_all' => true,
            'inventory_reports' => true,
            'inventory_export' => true,
            'inventory_manage_sessions' => true,
            'inventory_register' => true,
            'inventory_view_own' => true,
            'inventory_edit_own' => true,
        ],

        'Usuario Inventario' => [
            'admin_panel' => false,
            'manage_companies' => false,
            'manage_users' => false,
            'manage_api_settings' => false,
            'manage_email_settings' => false,
            'view_reports' => false,
            'manage_settings' => false,
            'view_quotations_readonly' => false,
            'manage_quotations' => false,
            'manage_customers' => false,
            'view_products' => true,
            'manage_products' => false,
            'manage_warehouses' => false,
            'manage_bank_accounts' => false,
            'billing_panel' => false,
            // Permisos de inventario
            'inventory_panel' => true,
            'inventory_admin' => false,
            'inventory_view_all' => false,
            'inventory_reports' => false,
            'inventory_export_own' => true,
            'inventory_manage_sessions' => false,
            'inventory_register' => true,
            'inventory_view_own' => true,
            'inventory_edit_own' => true,
        ],
    ];

    /**
     * Verifica si un rol tiene un permiso específico
     *
     * @param string $roleName Nombre del rol
     * @param string $permission Nombre del permiso
     * @return bool
     */
    public static function hasPermission($roleName, $permission) {
        if (!isset(self::$rolePermissions[$roleName])) {
            return false;
        }

        return self::$rolePermissions[$roleName][$permission] ?? false;
    }

    /**
     * Verifica si el usuario actual tiene un permiso
     *
     * @param Auth $auth Instancia de Auth
     * @param string $permission Nombre del permiso
     * @return bool
     */
    public static function userCan($auth, $permission) {
        if (!$auth->isLoggedIn()) {
            return false;
        }

        $userId = $auth->getUserId();
        $userRepo = new User();
        $userRoles = $userRepo->getRoles($userId);

        foreach ($userRoles as $role) {
            if (self::hasPermission($role['role_name'], $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Obtiene todos los permisos de un usuario
     *
     * @param Auth $auth Instancia de Auth
     * @return array
     */
    public static function getUserPermissions($auth) {
        if (!$auth->isLoggedIn()) {
            return [];
        }

        $userId = $auth->getUserId();
        $userRepo = new User();
        $userRoles = $userRepo->getRoles($userId);

        $permissions = [];
        foreach ($userRoles as $role) {
            if (isset(self::$rolePermissions[$role['role_name']])) {
                $permissions = array_merge($permissions, self::$rolePermissions[$role['role_name']]);
            }
        }

        return $permissions;
    }

    /**
     * Verifica acceso al Admin Panel
     */
    public static function canAccessAdminPanel($auth) {
        return self::userCan($auth, 'admin_panel');
    }

    /**
     * Verifica acceso al Panel de Facturación
     */
    public static function canAccessBillingPanel($auth) {
        return self::userCan($auth, 'billing_panel') ||
               self::userCan($auth, 'request_billing') ||
               self::userCan($auth, 'view_billing_history');
    }

    /**
     * Verifica acceso al Panel de Créditos y Cobranzas
     */
    public static function canAccessCreditsPanel($auth) {
        return self::userCan($auth, 'credits_panel') ||
               self::userCan($auth, 'process_credits') ||
               self::userCan($auth, 'view_credit_requests');
    }

    /**
     * Verifica acceso al Panel de Inventario
     */
    public static function canAccessInventoryPanel($auth) {
        return self::userCan($auth, 'inventory_panel');
    }

    /**
     * Verifica si puede administrar sesiones de inventario
     */
    public static function canManageInventorySessions($auth) {
        return self::userCan($auth, 'inventory_manage_sessions') ||
               self::userCan($auth, 'inventory_admin');
    }

    /**
     * Verifica si puede registrar entradas de inventario
     */
    public static function canRegisterInventory($auth) {
        return self::userCan($auth, 'inventory_register');
    }

    /**
     * Verifica si puede ver todos los registros de inventario
     */
    public static function canViewAllInventory($auth) {
        return self::userCan($auth, 'inventory_view_all');
    }

    /**
     * Verifica si puede generar reportes de inventario
     */
    public static function canGenerateInventoryReports($auth) {
        return self::userCan($auth, 'inventory_reports');
    }

    /**
     * Verifica acceso al Módulo de Análisis de Costos
     * Requiere permiso de rol + acceso individual en cost_analysis_access
     */
    public static function canAccessCostAnalysis($auth) {
        if (!$auth->isLoggedIn()) return false;

        // Admins del sistema siempre tienen acceso
        if ($auth->hasRole('Administrador del Sistema')) return true;

        // Para otros, verificar acceso individual en BD
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT COUNT(*) FROM cost_analysis_access WHERE user_id = ?");
            $stmt->execute([$auth->getUserId()]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
