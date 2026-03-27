<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    die('No autorizado');
}

$db = getDBConnection();

echo "<h2>Debug de Cuentas Bancarias</h2>";
echo "<hr>";

// 1. Check if table exists
echo "<h3>1. Verificar si la tabla existe</h3>";
try {
    $stmt = $db->query("SHOW TABLES LIKE 'bank_accounts'");
    $tableExists = $stmt->fetch();
    if ($tableExists) {
        echo "<p style='color: green;'>✅ La tabla 'bank_accounts' existe</p>";
    } else {
        echo "<p style='color: red;'>❌ La tabla 'bank_accounts' NO existe</p>";
        die("La tabla no existe. Debe ser creada primero.");
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// 2. Check table structure
echo "<h3>2. Estructura de la tabla</h3>";
try {
    $stmt = $db->query("DESCRIBE bank_accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// 3. Check all records
echo "<h3>3. Todos los registros en bank_accounts</h3>";
try {
    $stmt = $db->query("SELECT * FROM bank_accounts ORDER BY id");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Total de registros: " . count($accounts) . "</strong></p>";

    if (empty($accounts)) {
        echo "<p style='color: orange;'>⚠️ No hay registros en la tabla</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr>";
        foreach (array_keys($accounts[0]) as $key) {
            echo "<th>" . htmlspecialchars($key) . "</th>";
        }
        echo "</tr>";

        foreach ($accounts as $account) {
            echo "<tr>";
            foreach ($account as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// 4. Check by company
echo "<h3>4. Registros por empresa</h3>";
$companyId = $auth->getCompanyId();
echo "<p>Tu company_id: <strong>$companyId</strong></p>";

try {
    $stmt = $db->prepare("SELECT * FROM bank_accounts WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p><strong>Registros para tu empresa: " . count($accounts) . "</strong></p>";

    if (empty($accounts)) {
        echo "<p style='color: orange;'>⚠️ No hay registros para tu empresa (company_id = $companyId)</p>";
    } else {
        echo "<pre>" . print_r($accounts, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// 5. Test CompanySettings::getBankAccounts()
echo "<h3>5. Probar CompanySettings::getBankAccounts()</h3>";
try {
    $companySettings = new CompanySettings();
    $accounts = $companySettings->getBankAccounts($companyId);

    echo "<p><strong>Resultado del método getBankAccounts(): " . count($accounts) . " registros</strong></p>";

    if (empty($accounts)) {
        echo "<p style='color: orange;'>⚠️ El método getBankAccounts() retorna vacío</p>";
    } else {
        echo "<pre>" . print_r($accounts, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// 6. Check last insert attempt (if any)
echo "<h3>6. Último intento de inserción</h3>";
if (isset($_SESSION['last_bank_insert_error'])) {
    echo "<p style='color: red;'>Último error: " . $_SESSION['last_bank_insert_error'] . "</p>";
    unset($_SESSION['last_bank_insert_error']);
} else {
    echo "<p>No hay errores registrados en la sesión</p>";
}

echo "<hr>";
echo "<p><a href='bank_accounts.php' class='btn btn-primary'>Volver a Cuentas Bancarias</a></p>";
