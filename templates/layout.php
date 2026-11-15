<?php
/**
 * COTI · Master Layout Template (templates/layout.php)
 * Spec: gstack Industrial Design System (Native Left Sidebar + Sleek Topbar + Content Canvas)
 * Used by TemplateEngine and views throughout the system.
 */

$pageTitle = $pageTitle ?? ($companyData['name'] ?? 'Sistema de Cotizaciones');

// Include Master App Shell Header (Left Sidebar + Modern Topbar)
require_once __DIR__ . '/../includes/app_header.php';
?>

<?php if (isset($content)): ?>
    <?= $content ?>
<?php endif; ?>

<?php
// Pass custom and page scripts to the App Footer
ob_start();
?>
<?php if (isset($customJS)): ?>
    <?php foreach ($customJS as $js): ?>
        <script src="<?= htmlspecialchars($js) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<?php if (isset($pageScripts)): ?>
    <script>
        <?= $pageScripts ?>
    </script>
<?php endif; ?>
<?php
$extraScripts = ob_get_clean();

// Include Master App Shell Footer (Bottom nav, backdrop, controllers)
require_once __DIR__ . '/../includes/app_footer.php';
?>
