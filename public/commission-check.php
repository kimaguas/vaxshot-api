<?php
/**
 * TEMPORARY DIAGNOSTIC — delete after use
 * Access at: https://api.vaxshotcorp.com/commission-check.php?token=vaxcheck2026
 */

if (($_GET['token'] ?? '') !== 'vaxcheck2026') {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized. Add ?token=vaxcheck2026 to the URL.']));
}

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// ── Parse .env ──────────────────────────────────────────────────────────────
$envFile = __DIR__ . '/../.env';
$env = [];
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\n\r\"'");
    }
}

$out['env_found'] = file_exists($envFile);
$out['db_host']   = $env['DB_HOST']     ?? 'NOT SET';
$out['db_name']   = $env['DB_DATABASE'] ?? 'NOT SET';
$out['db_user']   = $env['DB_USERNAME'] ?? 'NOT SET';

// ── Connect via PDO ──────────────────────────────────────────────────────────
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $env['DB_HOST']     ?? '127.0.0.1',
        $env['DB_PORT']     ?? '3306',
        $env['DB_DATABASE'] ?? ''
    );
    $pdo = new PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $out['db_connected'] = true;
} catch (\Exception $e) {
    $out['db_connected'] = false;
    $out['db_error']     = $e->getMessage();
    echo json_encode($out, JSON_PRETTY_PRINT);
    exit;
}

// ── Table existence ──────────────────────────────────────────────────────────
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$out['tables']['sale_commissions'] = in_array('sale_commissions', $tables);
$out['tables']['permissions']      = in_array('permissions', $tables);
$out['tables']['sales']            = in_array('sales', $tables);

// ── Permissions ──────────────────────────────────────────────────────────────
if (in_array('permissions', $tables)) {
    $stmt = $pdo->query("SELECT name FROM permissions WHERE name IN ('view_sales_commissions','collect_commission')");
    $found = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $out['permissions']['view_sales_commissions'] = in_array('view_sales_commissions', $found);
    $out['permissions']['collect_commission']     = in_array('collect_commission', $found);

    // Which roles have view_sales_commissions
    $stmt = $pdo->query("
        SELECT r.name FROM roles r
        JOIN role_has_permissions rp ON r.id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE p.name = 'view_sales_commissions'
    ");
    $out['roles_with_view_sales_commissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $out['permissions'] = 'permissions table missing';
}

// ── Sales summary ────────────────────────────────────────────────────────────
if (in_array('sales', $tables)) {
    $total = $pdo->query("SELECT COUNT(*) FROM sales WHERE status='confirmed'")->fetchColumn();
    $out['sales_summary']['total_confirmed'] = (int)$total;

    $stmt = $pdo->query("SELECT payment_status, COUNT(*) as cnt FROM sales WHERE status='confirmed' GROUP BY payment_status");
    $out['sales_summary']['payment_status_breakdown'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt = $pdo->query("SELECT delivery_status, COUNT(*) as cnt FROM sales WHERE status='confirmed' GROUP BY delivery_status");
    $out['sales_summary']['delivery_status_breakdown'] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Commission query counts
    if (in_array('sale_commissions', $tables)) {
        $pending = $pdo->query("
            SELECT COUNT(*) FROM sales s
            WHERE s.status = 'confirmed'
              AND s.payment_status IN ('unpaid','partial')
              AND NOT EXISTS (
                SELECT 1 FROM sale_commissions sc
                WHERE sc.sale_id = s.id AND sc.collected_at IS NOT NULL
              )
        ")->fetchColumn();

        $forRelease = $pdo->query("
            SELECT COUNT(*) FROM sales s
            WHERE s.status = 'confirmed'
              AND s.payment_status = 'paid'
              AND NOT EXISTS (
                SELECT 1 FROM sale_commissions sc
                WHERE sc.sale_id = s.id AND sc.collected_at IS NOT NULL
              )
        ")->fetchColumn();

        $collected = $pdo->query("
            SELECT COUNT(*) FROM sales s
            WHERE s.status = 'confirmed'
              AND EXISTS (
                SELECT 1 FROM sale_commissions sc
                WHERE sc.sale_id = s.id AND sc.collected_at IS NOT NULL
              )
        ")->fetchColumn();

        $out['commission_query_counts'] = [
            'pending'     => (int)$pending,
            'for_release' => (int)$forRelease,
            'collected'   => (int)$collected,
        ];
    } else {
        $out['commission_query_counts'] = 'sale_commissions table missing — migration not run';
    }

    // Sample sale
    $sample = $pdo->query("SELECT id, sale_number, status, payment_status, delivery_status FROM sales WHERE status='confirmed' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $out['sample_sale'] = $sample ?: null;
}

echo json_encode($out, JSON_PRETTY_PRINT);
