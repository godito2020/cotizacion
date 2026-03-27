<?php

class TemplateEngine {
    private $auth;
    private $companyData = [];
    private $userData = [];

    public function __construct() {
        $this->auth = new Auth();
        $this->loadUserData();
        $this->loadCompanyData();
    }

    private function loadUserData() {
        if ($this->auth->isLoggedIn()) {
            $this->userData = $this->auth->getUser();
        }
    }

    private function loadCompanyData() {
        if ($this->auth->isLoggedIn() && $this->userData) {
            $companyRepo = new Company();
            $this->companyData = $companyRepo->getById($this->userData['company_id']) ?: [];
        }
    }

    public function render($templateFile, $data = []) {
        // Make auth, userData, and companyData available in all templates
        $data['auth'] = $this->auth;
        $data['userData'] = $this->userData;
        $data['companyData'] = $this->companyData;

        // Set default values
        $pageTitle = $data['pageTitle'] ?? 'Sistema de Cotizaciones';
        $customCSS = $data['customCSS'] ?? [];
        $customJS = $data['customJS'] ?? [];
        $pageScripts = $data['pageScripts'] ?? '';

        // Start output buffering
        ob_start();

        // Include the specific template
        if (file_exists($templateFile)) {
            extract($data);
            include $templateFile;
        } else {
            echo '<div class="alert alert-danger">Template not found: ' . htmlspecialchars($templateFile) . '</div>';
        }

        // Get the content
        $content = ob_get_clean();

        // Include the layout
        $layoutFile = __DIR__ . '/../templates/layout.php';
        if (file_exists($layoutFile)) {
            extract($data);
            include $layoutFile;
        } else {
            // Fallback if layout doesn't exist
            echo $content;
        }
    }

    public function renderPartial($partialFile, $data = []) {
        if (file_exists($partialFile)) {
            extract($data);
            include $partialFile;
        }
    }

    public static function formatCurrency($amount, $currency = 'S/') {
        return $currency . ' ' . number_format($amount, 2);
    }

    public static function formatDate($date, $format = 'd/m/Y') {
        if (empty($date)) return '-';
        return date($format, strtotime($date));
    }

    public static function getStatusBadge($status) {
        $statusClasses = [
            'Draft' => 'bg-secondary',
            'Sent' => 'bg-info',
            'Accepted' => 'bg-success',
            'Rejected' => 'bg-danger',
            'Invoiced' => 'bg-primary',
            'Deleted' => 'bg-dark'
        ];

        $statusNames = [
            'Draft' => 'Borrador',
            'Sent' => 'Enviada',
            'Accepted' => 'Aceptada',
            'Rejected' => 'Rechazada',
            'Invoiced' => 'Facturada',
            'Deleted' => 'Eliminada'
        ];

        $class = $statusClasses[$status] ?? 'bg-secondary';
        $name = $statusNames[$status] ?? $status;

        return "<span class=\"badge {$class}\">{$name}</span>";
    }

    public static function truncateText($text, $length = 50) {
        if (strlen($text) <= $length) return $text;
        return substr($text, 0, $length) . '...';
    }

    public static function generatePagination($currentPage, $totalPages, $baseUrl, $queryParams = []) {
        if ($totalPages <= 1) return '';

        $html = '<nav aria-label="Pagination"><ul class="pagination justify-content-center">';

        // Previous button
        $prevDisabled = $currentPage <= 1 ? 'disabled' : '';
        $prevPage = max(1, $currentPage - 1);
        $prevUrl = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $prevPage]));

        $html .= "<li class=\"page-item {$prevDisabled}\">";
        $html .= "<a class=\"page-link\" href=\"{$prevUrl}\"><i class=\"fas fa-chevron-left\"></i></a>";
        $html .= "</li>";

        // Page numbers
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);

        if ($start > 1) {
            $url = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => 1]));
            $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$url}\">1</a></li>";
            if ($start > 2) {
                $html .= "<li class=\"page-item disabled\"><span class=\"page-link\">...</span></li>";
            }
        }

        for ($i = $start; $i <= $end; $i++) {
            $activeClass = $i === $currentPage ? 'active' : '';
            $url = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $i]));
            $html .= "<li class=\"page-item {$activeClass}\"><a class=\"page-link\" href=\"{$url}\">{$i}</a></li>";
        }

        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= "<li class=\"page-item disabled\"><span class=\"page-link\">...</span></li>";
            }
            $url = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $totalPages]));
            $html .= "<li class=\"page-item\"><a class=\"page-link\" href=\"{$url}\">{$totalPages}</a></li>";
        }

        // Next button
        $nextDisabled = $currentPage >= $totalPages ? 'disabled' : '';
        $nextPage = min($totalPages, $currentPage + 1);
        $nextUrl = $baseUrl . '?' . http_build_query(array_merge($queryParams, ['page' => $nextPage]));

        $html .= "<li class=\"page-item {$nextDisabled}\">";
        $html .= "<a class=\"page-link\" href=\"{$nextUrl}\"><i class=\"fas fa-chevron-right\"></i></a>";
        $html .= "</li>";

        $html .= '</ul></nav>';

        return $html;
    }
}

// Helper functions for templates
function formatCurrency($amount, $currency = 'S/') {
    return TemplateEngine::formatCurrency($amount, $currency);
}

function formatDate($date, $format = 'd/m/Y') {
    return TemplateEngine::formatDate($date, $format);
}

function getStatusBadge($status) {
    return TemplateEngine::getStatusBadge($status);
}

function truncateText($text, $length = 50) {
    return TemplateEngine::truncateText($text, $length);
}

function generatePagination($currentPage, $totalPages, $baseUrl, $queryParams = []) {
    return TemplateEngine::generatePagination($currentPage, $totalPages, $baseUrl, $queryParams);
}
?>