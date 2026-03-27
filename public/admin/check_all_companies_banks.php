<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn() || !$auth->hasRole('Administrador del Sistema')) {
    die('No autorizado - Solo para Administradores del Sistema');
}

$db = getDBConnection();

echo "<h2>Estado de Cuentas Bancarias por Empresa</h2>";
echo "<hr>";

// Get all companies
$companyRepo = new Company();
$companies = $companyRepo->getAll();

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background-color: #f0f0f0;'>";
echo "<th>ID</th>";
echo "<th>Empresa</th>";
echo "<th>Total Cuentas</th>";
echo "<th>Cuentas Activas</th>";
echo "<th>Detalle</th>";
echo "<th>Acción</th>";
echo "</tr>";

foreach ($companies as $company) {
    $companyId = $company['id'];
    $companyName = $company['name'];

    // Count total accounts
    $stmt = $db->prepare("SELECT COUNT(*) FROM bank_accounts WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $totalAccounts = $stmt->fetchColumn();

    // Count active accounts
    $stmt = $db->prepare("SELECT COUNT(*) FROM bank_accounts WHERE company_id = ? AND is_active = 1");
    $stmt->execute([$companyId]);
    $activeAccounts = $stmt->fetchColumn();

    // Get account details
    $stmt = $db->prepare("SELECT bank_name, currency, is_active FROM bank_accounts WHERE company_id = ? ORDER BY bank_name");
    $stmt->execute([$companyId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statusColor = $totalAccounts > 0 ? '#d4edda' : '#f8d7da';
    $statusText = $totalAccounts > 0 ? '✅' : '❌';

    echo "<tr style='background-color: $statusColor;'>";
    echo "<td><strong>$companyId</strong></td>";
    echo "<td><strong>" . htmlspecialchars($companyName) . "</strong></td>";
    echo "<td style='text-align: center;'>$statusText <strong>$totalAccounts</strong></td>";
    echo "<td style='text-align: center;'><strong>$activeAccounts</strong></td>";
    echo "<td>";

    if (empty($accounts)) {
        echo "<em style='color: #999;'>Sin cuentas bancarias</em>";
    } else {
        echo "<ul style='margin: 0; padding-left: 20px;'>";
        foreach ($accounts as $acc) {
            $status = $acc['is_active'] ? '🟢' : '🔴';
            echo "<li>$status " . htmlspecialchars($acc['bank_name']) . " (" . htmlspecialchars($acc['currency']) . ")</li>";
        }
        echo "</ul>";
    }

    echo "</td>";
    echo "<td style='text-align: center;'>";
    echo "<a href='bank_accounts.php?company_id=$companyId' style='text-decoration: none; background-color: #007bff; color: white; padding: 5px 10px; border-radius: 3px;'>Gestionar</a>";
    echo "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<h3>Resumen</h3>";
$stmt = $db->query("SELECT COUNT(DISTINCT company_id) FROM bank_accounts");
$companiesWithAccounts = $stmt->fetchColumn();
$totalCompanies = count($companies);
$companiesWithoutAccounts = $totalCompanies - $companiesWithAccounts;

echo "<ul>";
echo "<li><strong>Total de empresas:</strong> $totalCompanies</li>";
echo "<li><strong>Empresas con cuentas bancarias:</strong> $companiesWithAccounts</li>";
echo "<li><strong>Empresas sin cuentas bancarias:</strong> $companiesWithoutAccounts</li>";
echo "</ul>";

if ($companiesWithoutAccounts > 0) {
    echo "<div style='background-color: #fff3cd; padding: 15px; border: 1px solid #ffc107; border-radius: 5px; margin-top: 20px;'>";
    echo "<strong>⚠️ Atención:</strong> Hay empresas sin cuentas bancarias configuradas. ";
    echo "Las cotizaciones de estas empresas no mostrarán información bancaria en el PDF.";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='bank_accounts.php' style='text-decoration: none; background-color: #28a745; color: white; padding: 10px 20px; border-radius: 5px;'>Ir a Cuentas Bancarias</a></p>";
