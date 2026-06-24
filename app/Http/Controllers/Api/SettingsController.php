<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    use LogsActivity;

    private array $groups = [
        'activity_logs'   => 'Activity Logs',
        'inventory_logs'  => 'Inventory Logs',
        'sales'           => 'Sales',
        'quotations'      => 'Quotations',
        'purchase_orders' => 'Purchase Orders',
    ];

    public function dataSummary()
    {
        return response()->json([
            'activity_logs' => [
                'label'       => 'Activity Logs',
                'description' => 'All system activity and audit trail records',
                'count'       => DB::table('activity_logs')->count(),
                'warning'     => null,
            ],
            'inventory_logs' => [
                'label'       => 'Inventory Logs',
                'description' => 'All stock movement history records',
                'count'       => DB::table('inventory_logs')->count(),
                'warning'     => null,
            ],
            'sales' => [
                'label'       => 'Sales',
                'description' => 'Sales records including items, payments, and deliveries',
                'count'       => DB::table('sales')->count(),
                'warning'     => 'Also deletes sale items, payments, and deliveries.',
            ],
            'quotations' => [
                'label'       => 'Quotations',
                'description' => 'All quotation records and their line items',
                'count'       => DB::table('quotations')->count(),
                'warning'     => null,
            ],
            'purchase_orders' => [
                'label'       => 'Purchase Orders',
                'description' => 'Purchase orders, receipts, all product batches, and inventory stock',
                'count'       => DB::table('purchase_orders')->count(),
                'warning'     => 'Also deletes all product batches and resets product stock to 0. This cannot be undone.',
            ],
        ]);
    }

    public function cleanData(Request $request)
    {
        $request->validate([
            'groups'   => 'required|array|min:1',
            'groups.*' => 'in:activity_logs,inventory_logs,sales,quotations,purchase_orders',
        ]);

        $groups  = $request->groups;
        $deleted = [];

        DB::beginTransaction();
        try {
            if (in_array('activity_logs', $groups)) {
                $deleted['activity_logs'] = DB::table('activity_logs')->count();
                DB::table('activity_logs')->delete();
            }

            if (in_array('inventory_logs', $groups)) {
                $deleted['inventory_logs'] = DB::table('inventory_logs')->count();
                DB::table('inventory_logs')->delete();
            }

            if (in_array('sales', $groups)) {
                $deleted['sales'] = DB::table('sales')->count();
                DB::table('sale_deliveries')->delete();
                DB::table('sale_payments')->delete();
                DB::table('sale_items')->delete();
                DB::table('sales')->delete();
            }

            if (in_array('quotations', $groups)) {
                $deleted['quotations'] = DB::table('quotations')->count();
                DB::table('quotation_items')->delete();
                DB::table('quotations')->delete();
            }

            if (in_array('purchase_orders', $groups)) {
                $deleted['purchase_orders'] = DB::table('purchase_orders')->count();
                DB::table('purchase_order_receipt_items')->delete();
                DB::table('purchase_order_receipts')->delete();
                DB::table('purchase_order_items')->delete();
                DB::table('purchase_orders')->delete();
                // Nullify batch references in inventory_logs before deleting batches
                DB::table('inventory_logs')->whereNotNull('product_batch_id')->update(['product_batch_id' => null]);
                DB::table('product_batches')->delete();
                DB::table('products')->update(['stock' => 0]);
            }

            DB::commit();

            $this->logActivity(
                action:      'DELETE',
                module:      'Settings',
                description: 'Cleaned data groups: ' . implode(', ', $groups),
            );

            return response()->json([
                'message' => 'Data cleaned successfully.',
                'deleted' => $deleted,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to clean data: ' . $e->getMessage(),
            ], 500);
        }
    }
}
