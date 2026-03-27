<?php
/**
 * Meta tags para PWA — per-company favicon and name.
 * Expects init.php and auth to have run already (session active, companyId known).
 */

// Read company favicon/logo from session-cached settings or DB
$_pwaFavicon    = null;
$_pwaLogo       = null;
$_pwaAppName    = 'COTIZACION GSM';
$_pwaShortName  = 'Coti GSM';

try {
    $_pwaCompanyId = $_SESSION['company_id'] ?? 1;
    $_pwaDb = getDBConnection();
    $_pwaStmt = $_pwaDb->prepare(
        "SELECT setting_key, setting_value FROM settings
         WHERE company_id = ?
           AND setting_key IN ('company_name','company_logo_url','company_favicon_url')"
    );
    $_pwaStmt->execute([$_pwaCompanyId]);
    $_pwaCfg = $_pwaStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    if (!empty($_pwaCfg['company_name']))        $_pwaShortName = $_pwaCfg['company_name'];
    if (!empty($_pwaCfg['company_favicon_url'])) $_pwaFavicon   = $_pwaCfg['company_favicon_url'];
    if (!empty($_pwaCfg['company_logo_url']))    $_pwaLogo      = $_pwaCfg['company_logo_url'];
} catch (Exception $_pwaEx) { /* use defaults */ }

// Best icon for apple-touch-icon: prefer pre-generated 192px icon, then favicon, then logo, then static
$_pwaPreGen192 = PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company'
               . DIRECTORY_SEPARATOR . "pwa_{$_pwaCompanyId}_192x192.png";
if (file_exists($_pwaPreGen192)) {
    $_pwaTouchIcon = upload_url("uploads/company/pwa_{$_pwaCompanyId}_192x192.png");
} elseif ($_pwaFavicon) {
    $_pwaTouchIcon = upload_url($_pwaFavicon);
} elseif ($_pwaLogo) {
    $_pwaTouchIcon = upload_url($_pwaLogo);
} else {
    $_pwaTouchIcon = BASE_URL . '/assets/icons/icon-192x192.png';
}

$_pwaFaviconSrc32 = $_pwaFavicon
    ? upload_url($_pwaFavicon)
    : BASE_URL . '/assets/icons/favicon-32x32.png';
$_pwaFaviconSrc16 = $_pwaFavicon
    ? upload_url($_pwaFavicon)
    : BASE_URL . '/assets/icons/favicon-16x16.png';
?>
<!-- PWA Meta Tags -->
<link rel="manifest" href="<?= BASE_URL ?>/manifest.php?c=<?= (int)$_pwaCompanyId ?>">
<meta name="theme-color" content="#0d6efd">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($_pwaShortName) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="application-name" content="COTIZACION GSM">
<meta name="msapplication-TileColor" content="#0d6efd">
<meta name="msapplication-TileImage" content="<?= htmlspecialchars($_pwaTouchIcon) ?>">

<!-- Favicon (tab icon) — uses company favicon if uploaded -->
<link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars($_pwaFaviconSrc32) ?>">
<link rel="icon" type="image/png" sizes="16x16" href="<?= htmlspecialchars($_pwaFaviconSrc16) ?>">

<!-- Apple touch icons — uses company logo if uploaded -->
<link rel="apple-touch-icon" href="<?= htmlspecialchars($_pwaTouchIcon) ?>">
<link rel="apple-touch-icon" sizes="152x152" href="<?= htmlspecialchars($_pwaTouchIcon) ?>">
<link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($_pwaTouchIcon) ?>">
<link rel="apple-touch-icon" sizes="167x167" href="<?= htmlspecialchars($_pwaTouchIcon) ?>">

<!-- iOS splash screen -->
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="apple-touch-startup-image" href="<?= htmlspecialchars($_pwaTouchIcon) ?>">
