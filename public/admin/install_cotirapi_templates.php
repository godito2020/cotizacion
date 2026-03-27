<?php
require_once __DIR__ . '/../../includes/init.php';

$auth = new Auth();

if (!$auth->isLoggedIn() || !$auth->hasRole('Administrador del Sistema')) {
    die('❌ No autorizado - Solo para Administradores del Sistema');
}

echo "<h2>Instalación de Plantillas CotiRapi</h2>";
echo "<hr>";

$db = getDBConnection();

try {
    // Create table
    echo "<h3>1. Creando tabla cotirapi_templates...</h3>";

    // First, check if companies table has proper primary key
    echo "<p>Verificando estructura de tabla companies...</p>";
    $stmt = $db->query("SHOW CREATE TABLE companies");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $hasProperPK = strpos($result['Create Table'], 'PRIMARY KEY') !== false;

    if ($hasProperPK) {
        echo "<p style='color: green;'>✓ Tabla companies tiene PRIMARY KEY</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Tabla companies sin PRIMARY KEY, creando sin foreign key</p>";
    }

    // Create table without foreign key first
    $sql = "CREATE TABLE IF NOT EXISTS cotirapi_templates (
        id INT PRIMARY KEY AUTO_INCREMENT,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        template_header TEXT,
        template_item TEXT NOT NULL,
        template_footer TEXT,
        is_active TINYINT(1) DEFAULT 1,
        is_default TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_company (company_id),
        INDEX idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<p style='color: green;'>✅ Tabla creada exitosamente</p>";

    // Try to add foreign key if companies has proper structure
    if ($hasProperPK) {
        try {
            echo "<p>Intentando agregar foreign key constraint...</p>";
            $db->exec("ALTER TABLE cotirapi_templates
                       ADD CONSTRAINT fk_cotirapi_company
                       FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE");
            echo "<p style='color: green;'>✓ Foreign key agregada</p>";
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠ No se pudo agregar foreign key (no crítico): " . $e->getMessage() . "</p>";
        }
    }

    // Insert default templates
    echo "<h3>2. Insertando plantillas por defecto...</h3>";

    // Get all companies
    $companyRepo = new Company();
    $companies = $companyRepo->getAll();
    $totalInserted = 0;

    foreach ($companies as $company) {
        $companyId = $company['id'];
        $companyName = $company['name'];

        echo "<p><strong>Empresa: {$companyName} (ID: {$companyId})</strong></p>";

        // Plantilla Estándar
        $stmt = $db->prepare("SELECT 1 FROM cotirapi_templates WHERE company_id = ? AND name = 'Plantilla Estándar'");
        $stmt->execute([$companyId]);
        if (!$stmt->fetch()) {
            $stmt = $db->prepare("
                INSERT INTO cotirapi_templates (company_id, name, template_header, template_item, template_footer, is_active, is_default)
                VALUES (?, ?, ?, ?, ?, 1, 1)
            ");
            $stmt->execute([
                $companyId,
                'Plantilla Estándar',
                "🏪 *COTIZACIÓN RÁPIDA*\n━━━━━━━━━━━━━━━━━━━━━\n\n👤 *Cliente:* {CUSTOMER_NAME}\n📅 *Fecha:* {DATE}\n\n━━━━━━━━━━━━━━━━━━━━━\n\n",
                "📦 *{ITEM_NUMBER}. {DESCRIPTION}*\n{CODE_LINE}   📊 Cantidad: {QUANTITY}\n   💰 Precio: {CURRENCY} {UNIT_PRICE}\n{DISCOUNT_LINE}   💵 Total: {CURRENCY} {TOTAL}\n\n",
                "━━━━━━━━━━━━━━━━━━━━━\n\n💵 *Subtotal:* {CURRENCY} {SUBTOTAL}\n📊 *IGV (18%):* {CURRENCY} {IGV}\n💰 *TOTAL:* {CURRENCY} {GRAND_TOTAL}\n\n━━━━━━━━━━━━━━━━━━━━━\n\n✅ _Precios incluyen IGV_\n📍 _Stock sujeto a disponibilidad_\n💬 _Consultas al WhatsApp_"
            ]);
            echo "<p style='color: green; margin-left: 20px;'>✅ Plantilla Estándar insertada</p>";
            $totalInserted++;
        } else {
            echo "<p style='color: orange; margin-left: 20px;'>⚠️ Plantilla Estándar ya existe</p>";
        }

        // Plantilla Simple
        $stmt = $db->prepare("SELECT 1 FROM cotirapi_templates WHERE company_id = ? AND name = 'Plantilla Simple'");
        $stmt->execute([$companyId]);
        if (!$stmt->fetch()) {
            $stmt = $db->prepare("
                INSERT INTO cotirapi_templates (company_id, name, template_header, template_item, template_footer, is_active, is_default)
                VALUES (?, ?, ?, ?, ?, 1, 0)
            ");
            $stmt->execute([
                $companyId,
                'Plantilla Simple',
                "COTIZACIÓN\n\nCliente: {CUSTOMER_NAME}\nFecha: {DATE}\n\n",
                "{ITEM_NUMBER}. {DESCRIPTION}\nCódigo: {CODE}\nCantidad: {QUANTITY} | Precio: {CURRENCY} {UNIT_PRICE}\nTotal: {CURRENCY} {TOTAL}\n\n",
                "-------------------\nSubtotal: {CURRENCY} {SUBTOTAL}\nIGV: {CURRENCY} {IGV}\nTOTAL: {CURRENCY} {GRAND_TOTAL}\n\nPrecios incluyen IGV"
            ]);
            echo "<p style='color: green; margin-left: 20px;'>✅ Plantilla Simple insertada</p>";
            $totalInserted++;
        } else {
            echo "<p style='color: orange; margin-left: 20px;'>⚠️ Plantilla Simple ya existe</p>";
        }

        // Plantilla Profesional
        $stmt = $db->prepare("SELECT 1 FROM cotirapi_templates WHERE company_id = ? AND name = 'Plantilla Profesional'");
        $stmt->execute([$companyId]);
        if (!$stmt->fetch()) {
            $stmt = $db->prepare("
                INSERT INTO cotirapi_templates (company_id, name, template_header, template_item, template_footer, is_active, is_default)
                VALUES (?, ?, ?, ?, ?, 1, 0)
            ");
            $stmt->execute([
                $companyId,
                'Plantilla Profesional',
                "═══════════════════════\n  COTIZACIÓN COMERCIAL\n═══════════════════════\n\n▸ Cliente: {CUSTOMER_NAME}\n▸ Fecha: {DATE}\n\n",
                "┌ ITEM {ITEM_NUMBER}\n├─ {DESCRIPTION}\n{CODE_LINE}├─ Cantidad: {QUANTITY} unidades\n├─ Precio unitario: {CURRENCY} {UNIT_PRICE}\n{DISCOUNT_LINE}└─ Subtotal: {CURRENCY} {TOTAL}\n\n",
                "═══════════════════════\n  RESUMEN FINANCIERO\n═══════════════════════\n\nSubtotal......: {CURRENCY} {SUBTOTAL}\nIGV (18%).....: {CURRENCY} {IGV}\n───────────────────────\nTOTAL.........: {CURRENCY} {GRAND_TOTAL}\n\n⚠ Condiciones:\n• Precios incluyen IGV\n• Sujeto a stock disponible\n• Válido por 7 días"
            ]);
            echo "<p style='color: green; margin-left: 20px;'>✅ Plantilla Profesional insertada</p>";
            $totalInserted++;
        } else {
            echo "<p style='color: orange; margin-left: 20px;'>⚠️ Plantilla Profesional ya existe</p>";
        }
    }

    echo "<hr>";
    echo "<h3>Resumen Final</h3>";
    echo "<ul>";
    echo "<li><strong>Total de empresas procesadas:</strong> " . count($companies) . "</li>";
    echo "<li><strong>Total de plantillas insertadas:</strong> {$totalInserted}</li>";

    $stmt = $db->query("SELECT COUNT(*) FROM cotirapi_templates");
    $totalTemplates = $stmt->fetchColumn();
    echo "<li><strong>Total de plantillas en el sistema:</strong> {$totalTemplates}</li>";
    echo "</ul>";

    echo "<div style='background-color: #d4edda; padding: 15px; border: 1px solid #28a745; border-radius: 5px; margin-top: 20px;'>";
    echo "<strong>✅ Instalación completada exitosamente</strong>";
    echo "</div>";

    echo "<hr>";
    echo "<p><a href='cotirapi_templates.php' style='text-decoration: none; background-color: #007bff; color: white; padding: 10px 20px; border-radius: 5px;'>Ir a Gestión de Plantillas</a></p>";

} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; padding: 15px; border: 1px solid #dc3545; border-radius: 5px;'>";
    echo "<strong>❌ Error:</strong> " . $e->getMessage();
    echo "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
