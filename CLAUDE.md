# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**COTI** is a multi-enterprise quotation and billing management system built with PHP 8.0+ and MySQL. It supports multiple companies, each with role-based access for managing quotations, customers, products, inventory, billing, and credit collections. Production URL: `coti.gsm.pe`.

## Technology Stack

- **Backend**: PHP 8.0+, PDO with prepared statements (no ORM)
- **Database**: MySQL — two databases: `cotizacion` (main) and `cobol` (legacy product/stock data)
- **Frontend**: Bootstrap 5.3, Font Awesome 6.4 (CDN), vanilla JavaScript + Fetch API
- **PDF generation**: TCPDF (`tecnickcom/tcpdf`)
- **Excel**: PhpSpreadsheet (`phpoffice/phpspreadsheet`)
- **Dependencies**: Only 2 Composer packages (TCPDF + PhpSpreadsheet)

## Setup & Commands

```bash
composer install

# Database setup (run in order)
mysql -u admin -p cotizacion < scripts/database.sql

# Feature installers (web-based, open in browser)
# /install_billing_system.php
# /public/admin/install_inventory_module.php
# /public/admin/install_cotirapi_templates.php
```

There is no formal build pipeline, test suite, or linting configuration.

## Configuration

**`config/config.php`** — Database credentials (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `COBOL_DB_NAME`), `BASE_URL` (auto-detects localhost vs production), timezone `America/Lima`.

**`config/performance.php`** — Memory limit 256MB (for Excel imports), max execution 300s, opcache settings.

**`config/permissions.php`** — Static `Permissions` class with 6 roles and 30+ granular permissions:
- `Administrador del Sistema` — full access
- `Administrador de Empresa` — company-scoped admin
- `Vendedor` — create/manage quotations
- `Facturación` — billing approval workflow only (redirected to `/billing/pending.php`)
- `Créditos y Cobranzas` — credit and collections
- `Supervisor/Usuario Inventario` — physical inventory tracking

## Architecture

### Request Flow (Every Page)

All ~140 pages include `require_once __DIR__ . '/../../includes/init.php'` which:

1. **Session**: Creates `sessions/` dir if missing, sets secure cookie params (`httponly`, `samesite=Lax`), then calls `session_write_close()` immediately to release lock. **Important**: any code needing to write `$_SESSION` must call `session_start()` again.
2. **Config**: Loads `config.php`, `performance.php`, `permissions.php`
3. **Database**: `Database::getInstance()` and `CobolDatabase::getInstance()` (singletons). COBOL connection uses `PDO::CASE_LOWER` to normalize column names.
4. **Autoloader**: `spl_autoload_register()` loads classes from `/lib/` by filename (case-sensitive: `Auth` → `Auth.php`)

### Database Connections

```php
$db = getDBConnection();           // Main cotizacion DB
$dbCobol = getCobolConnection();   // Legacy COBOL product/stock DB
```

Both use PDO with `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`.

### Directory Layout
- **`lib/`** — 20 PHP model/service classes (composition, no inheritance). Each receives PDO via `getDBConnection()` in constructor.
- **`public/`** — Web-accessible views organized by module + `api/` for 29 JSON endpoints
- **`config/`** — Configuration, database connection singletons, permissions
- **`includes/`** — `init.php` bootstrap and shared UI components
- **`scripts/`** — SQL schema files and feature-specific migrations
- **`uploads/`**, **`sessions/`**, **`logs/`**, **`storage/`** — must be writable by web server

### API Endpoint Pattern

All APIs in `public/api/` follow this structure:

```php
error_reporting(E_ERROR | E_PARSE);
ob_start();
require_once __DIR__ . '/../../includes/init.php';
ob_clean();
header('Content-Type: application/json');

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
// ... business logic
echo json_encode(['success' => true, 'data' => $result]);
```

### Permission Checking Pattern

Pages enforce access via `Permissions::userCan($auth, 'permission_name')` or role checks:
```php
if (!$auth->hasRole(['Administrador del Sistema', 'Administrador de Empresa'])) {
    $auth->redirect();
}
```

Multi-role users see a role selector at login (`/public/select_role.php`); active role stored in `$_SESSION['active_role_name']`.

### Mobile Device Routing

`isMobileDevice()` checks User-Agent and redirects to `_mobile.php` variants (e.g., `index_mobile.php`, `create_mobile.php`). Bypass with `?desktop` query param. Same API endpoints serve both.

### Key Integrations
- **COBOL legacy DB**: Product catalog and stock via `vista_almacenes_anual` view — **do not modify**. Monthly stock columns (enero, febrero...) indexed by `$meses[date('n')]`.
- **Peru government APIs**: RUC/DNI validation via `lib/PeruApiClient.php`
- **WhatsApp (CotiRapi)**: Template-based quick quotations with `{VARIABLE}` replacement
- **File serving**: Uploaded files served through `img.php` proxy (IIS/Windows NTFS permission workaround)

### Quotation & Billing Workflow

`Borrador → Enviada → Aceptada/Rechazada → Facturada`

Billing is two-step: Quotation → `quotation_billing_tracking` table → Invoice. Vendedor requests billing → Facturación role approves/rejects with invoice number.

### Critical Gotchas

1. **Product ID duality**: Quotation items can reference either a COBOL product code (string) or a local product ID (int) — `save_quotation.php` handles both.
2. **Session write lock**: Always released early via `session_write_close()`. Re-open session before writing.
3. **Company data isolation**: All major tables have `company_id` FK. **Always filter by `company_id`** in queries to prevent cross-company data leaks.
4. **Currency/Tax**: IGV is always 18%. Currency stored as code (`S/`, `$`). IGV mode configurable (inclusive vs exclusive).
5. **Status enums**: Use mixed-case snake_case strings (e.g., `Pending_Invoice`, `Credit_Approved`, `In_Process`).
6. **BASE_URL**: Includes `/public` path segment because IIS has no URL rewrite.
7. **Transaction usage**: Critical workflows (billing, credits, inventory) use explicit `beginTransaction()`/`commit()`/`rollBack()` — maintain this pattern.
