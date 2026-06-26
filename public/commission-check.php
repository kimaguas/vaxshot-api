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

require __DIR__ . '/../vendor/autoload.php';
$app    = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Sale;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$out = [];

// 1. Table existence
$out['tables'] = [
    'sales'            => Schema::hasTable('sales'),
    'sale_commissions' => Schema::hasTable('sale_commissions'),
    'permissions'      => Schema::hasTable('permissions'),
];

// 2. Permission existence
$out['permissions'] = [
    'view_sales_commissions' => Permission::where('name', 'view_sales_commissions')->exists(),
    'collect_commission'     => Permission::where('name', 'collect_commission')->exists(),
];

// 3. Role → permission assignments
foreach (['admin', 'manager', 'sales_rep'] as $roleName) {
    $role = Role::where('name', $roleName)->first();
    $out['role_permissions'][$roleName] = $role ? [
        'view_sales_commissions' => $role->hasPermissionTo('view_sales_commissions'),
        'collect_commission'     => $role->hasPermissionTo('collect_commission'),
    ] : 'role not found';
}

// 4. Sales data overview
$out['sales_summary'] = [
    'total_confirmed'           => Sale::where('status', 'confirmed')->count(),
    'payment_status_breakdown'  => Sale::where('status', 'confirmed')
        ->selectRaw('payment_status, count(*) as count')
        ->groupBy('payment_status')
        ->pluck('count', 'payment_status'),
    'delivery_status_breakdown' => Sale::where('status', 'confirmed')
        ->selectRaw('delivery_status, count(*) as count')
        ->groupBy('delivery_status')
        ->pluck('count', 'delivery_status'),
];

// 5. Commission query counts (what the controller actually returns)
try {
    $out['commission_query_counts'] = [
        'pending'     => Sale::where('status', 'confirmed')
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->whereDoesntHave('commission', fn($q) => $q->whereNotNull('collected_at'))
            ->count(),
        'for_release' => Sale::where('status', 'confirmed')
            ->where('payment_status', 'paid')
            ->whereDoesntHave('commission', fn($q) => $q->whereNotNull('collected_at'))
            ->count(),
        'collected'   => Sale::where('status', 'confirmed')
            ->whereHas('commission', fn($q) => $q->whereNotNull('collected_at'))
            ->count(),
    ];
} catch (\Exception $e) {
    $out['commission_query_error'] = $e->getMessage();
}

// 6. Sample confirmed sale
$sample = Sale::where('status', 'confirmed')->first();
$out['sample_sale'] = $sample ? [
    'id'              => $sample->id,
    'sale_number'     => $sample->sale_number,
    'status'          => $sample->status,
    'payment_status'  => $sample->payment_status,
    'delivery_status' => $sample->delivery_status,
] : null;

// 7. Which roles have view_sales_commissions
$out['who_has_view_sales_commissions'] = [
    'via_role' => DB::table('roles')
        ->join('role_has_permissions', 'roles.id', '=', 'role_has_permissions.role_id')
        ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
        ->where('permissions.name', 'view_sales_commissions')
        ->pluck('roles.name')
        ->toArray(),
];

echo json_encode($out, JSON_PRETTY_PRINT);
