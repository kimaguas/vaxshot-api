<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // ==================
    // SALES REPORT
    // ==================
    public function salesReport(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $authUser   = \Illuminate\Support\Facades\Auth::user();
        $areaCodeId = ($authUser->hasRole('sales_rep') && $authUser->area_code_id)
                        ? $authUser->area_code_id
                        : null;

        $sales = Sale::with(['customer.areaCode', 'items.product.supplier', 'payments'])
            ->where('status', 'confirmed')
            ->whereBetween('sale_date', [$request->from, $request->to])
            ->when($areaCodeId, fn ($q) => $q->where('area_code_id', $areaCodeId))
            ->latest()
            ->get();

        // Summary
        $summary = [
            'total_sales'        => $sales->count(),
            'total_amount'       => $sales->sum('total_amount'),
            'total_paid'         => $sales->sum('amount_paid'),
            'total_balance'      => $sales->sum('balance'),
            'paid_count'         => $sales->where('payment_status', 'paid')->count(),
            'partial_count'      => $sales->where('payment_status', 'partial')->count(),
            'unpaid_count'       => $sales->where('payment_status', 'unpaid')->count(),
        ];

        // Sales by product
        $byProduct = $sales->flatMap->items
            ->groupBy('product_id')
            ->map(function ($items) {
                return [
                    'product'        => $items->first()->product?->brand_name,
                    'product_code'   => $items->first()->product?->product_code,
                    'total_quantity' => $items->sum('quantity'),
                    'total_amount'   => $items->sum('total_price'),
                ];
            })->values();

        // Sales by customer
        $byCustomer = $sales->groupBy('customer_id')
            ->map(function ($group) {
                $customer = $group->first()->customer;
                return [
                    'customer_code' => $customer?->customer_id,
                    'customer'      => $customer?->name,
                    'total_sales'   => $group->count(),
                    'total_amount'  => $group->sum('total_amount'),
                    'total_paid'    => $group->sum('amount_paid'),
                    'balance'       => $group->sum('balance'),
                ];
            })->values();

        // Sales by supplier (line-item detail grouped by supplier)
        $bySupplier = $sales->flatMap(function ($sale) {
            return $sale->items->map(function ($item) use ($sale) {
                return [
                    'supplier'          => $item->product?->supplier?->company ?? 'Unknown',
                    'supplier_sort'     => $item->product?->supplier_id ?? 0,
                    'sale_date'         => $sale->sale_date?->format('M d, Y'),
                    'customer_code'     => $sale->customer?->customer_id,
                    'customer_name'     => $sale->customer?->name,
                    'customer_address'  => $sale->customer?->full_address,
                    'product'           => $item->product?->brand_name,
                    'quantity'          => (int) $item->quantity,
                    'amount'            => (float) $item->total_price,
                    'invoice_number'    => $sale->invoice_number,
                ];
            });
        })->groupBy('supplier')
          ->sortBy('supplier')
          ->map(function ($items, $supplier) {
              return [
                  'supplier'       => $supplier,
                  'items'          => $items->values(),
                  'total_quantity' => $items->sum('quantity'),
                  'total_amount'   => (float) $items->sum('amount'),
              ];
          })->values();

        // Sales by area
        $byArea = $sales->groupBy(function ($sale) {
            $area = $sale->customer?->areaCode;
            return $area ? "{$area->code} - {$area->name}" : 'No Area';
        })->map(function ($group, $area) {
            return [
                'area'         => $area,
                'total_sales'  => $group->count(),
                'total_amount' => (float) $group->sum('total_amount'),
                'total_paid'   => (float) $group->sum('amount_paid'),
                'balance'      => (float) $group->sum('balance'),
            ];
        })->sortBy('area')->values();

        return response()->json([
            'from'        => $request->from,
            'to'          => $request->to,
            'summary'     => $summary,
            'sales'       => $sales->map(function ($sale) {
                return [
                    'sale_number'    => $sale->sale_number,
                    'invoice_number' => $sale->invoice_number,
                    'or_number'      => $sale->or_number,
                    'customer'       => $sale->customer?->name,
                    'sale_date'      => $sale->sale_date?->format('M d, Y'),
                    'total_amount'   => $sale->total_amount,
                    'amount_paid'    => $sale->amount_paid,
                    'balance'        => $sale->balance,
                    'payment_status' => $sale->payment_status,
                    'payment_method' => $sale->payment_method,
                ];
            }),
            'by_product'  => $byProduct,
            'by_customer' => $byCustomer,
            'by_supplier' => $bySupplier,
            'by_area'     => $byArea,
        ], 200);
    }

    // ==================
    // INVENTORY REPORT
    // ==================
    public function inventoryReport(Request $request)
    {
        // Current stock levels
        $products = Product::with(['batches', 'supplier'])
            ->active()
            ->get()
            ->map(function ($product) {
                $activeBatches = $product->batches
                    ->where('status', '!=', 'depleted')
                    ->sortBy('expiry_date')
                    ->values();
                $isLowStock = $product->stock <= ($product->maintaining_stock ?? 0);

                return [
                    'product_code'     => $product->product_code,
                    'brand_name'       => $product->brand_name,
                    'supplier'         => $product->supplier?->company,
                    'total_stock'      => $product->stock,
                    'maintaining_stock'=> $product->maintaining_stock,
                    'is_low_stock'     => $isLowStock,
                    'batches'          => $activeBatches->map(function ($batch) {
                        return [
                            'lot_number'         => $batch->lot_number,
                            'expiry_date'        => $batch->expiry_date?->format('M d, Y'),
                            'remaining_quantity' => $batch->remaining_quantity,
                            'is_expiring_soon'   => $batch->isExpiringSoon(),
                        ];
                    }),
                ];
            });

        // Low stock products
        $lowStock = $products->filter(fn($p) => $p['is_low_stock'])->values();

        // Stock movements
        $movements = null;
        if ($request->from && $request->to) {
            $movements = InventoryLog::with(['product', 'batch', 'createdBy'])
                ->whereBetween('created_at', [$request->from, $request->to])
                ->latest()
                ->get()
                ->map(function ($log) {
                    return [
                        'date'           => $log->created_at->format('M d, Y h:i A'),
                        'product'        => $log->product?->brand_name,
                        'product_code'   => $log->product?->product_code,
                        'lot_number'     => $log->batch?->lot_number,
                        'type'           => $log->type,
                        'quantity'       => $log->quantity,
                        'previous_stock' => $log->previous_stock,
                        'new_stock'      => $log->new_stock,
                        'reference'      => $log->reference,
                        'remarks'        => $log->remarks,
                        'created_by'     => $log->createdBy?->name,
                    ];
                });
        }

        return response()->json([
            'products'   => $products,
            'low_stock'  => $lowStock,
            'movements'  => $movements,
        ], 200);
    }

    // ==================
    // PURCHASE ORDER REPORT
    // ==================
    public function purchaseOrderReport(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $orders = PurchaseOrder::with(['supplier', 'items.product', 'receipts'])
            ->whereBetween('order_date', [$request->from, $request->to])
            ->latest()
            ->get();

        // Summary
        $summary = [
            'total_orders'    => $orders->count(),
            'total_amount'    => $orders->sum(fn($o) => $o->total_amount),
            'draft_count'     => $orders->where('status', 'draft')->count(),
            'ordered_count'   => $orders->where('status', 'ordered')->count(),
            'partial_count'   => $orders->where('status', 'partial')->count(),
            'received_count'  => $orders->where('status', 'received')->count(),
            'cancelled_count' => $orders->where('status', 'cancelled')->count(),
        ];

        // By supplier
        $bySupplier = $orders->groupBy('supplier_id')
            ->map(function ($orders) {
                return [
                    'supplier'      => $orders->first()->supplier?->company,
                    'total_orders'  => $orders->count(),
                    'total_amount'  => $orders->sum(fn($o) => $o->total_amount),
                ];
            })->values();

        return response()->json([
            'from'        => $request->from,
            'to'          => $request->to,
            'summary'     => $summary,
            'orders'      => $orders->map(function ($order) {
                return [
                    'po_number'              => $order->po_number,
                    'supplier'               => $order->supplier?->company,
                    'order_date'             => $order->order_date?->format('M d, Y'),
                    'expected_delivery_date' => $order->expected_delivery_date?->format('M d, Y'),
                    'status'                 => $order->status,
                    'total_amount'           => $order->total_amount,
                    'items_count'            => $order->items->count(),
                ];
            }),
            'by_supplier' => $bySupplier,
        ], 200);
    }

    // ==================
    // CUSTOMER REPORT
    // ==================
    public function customerReport(Request $request)
    {
        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $authUser   = \Illuminate\Support\Facades\Auth::user();
        $areaCodeId = ($authUser->hasRole('sales_rep') && $authUser->area_code_id)
                        ? $authUser->area_code_id
                        : null;

        $sales = Sale::with(['customer', 'items'])
            ->where('status', 'confirmed')
            ->whereBetween('sale_date', [$request->from, $request->to])
            ->when($areaCodeId, fn ($q) => $q->where('area_code_id', $areaCodeId))
            ->get();

        // By customer
        $byCustomer = $sales->groupBy('customer_id')
            ->map(function ($sales) {
                $customer = $sales->first()->customer;
                return [
                    'customer'         => $customer?->name,
                    'specialization'   => $customer?->specialization,
                    'city'             => $customer?->city,
                    'province'         => $customer?->province,
                    'total_sales'      => $sales->count(),
                    'total_amount'     => $sales->sum('total_amount'),
                    'total_paid'       => $sales->sum('amount_paid'),
                    'balance'          => $sales->sum('balance'),
                ];
            })->sortByDesc('total_amount')->values();

        // By city
        $byCity = $byCustomer->groupBy('city')
            ->map(function ($customers) {
                return [
                    'city'         => $customers->first()['city'],
                    'total_sales'  => $customers->sum('total_sales'),
                    'total_amount' => $customers->sum('total_amount'),
                ];
            })->values();

        // By specialization
        $bySpecialization = $byCustomer->groupBy('specialization')
            ->map(function ($customers) {
                return [
                    'specialization' => $customers->first()['specialization'],
                    'total_sales'    => $customers->sum('total_sales'),
                    'total_amount'   => $customers->sum('total_amount'),
                ];
            })->values();

        return response()->json([
            'from'               => $request->from,
            'to'                 => $request->to,
            'by_customer'        => $byCustomer,
            'by_city'            => $byCity,
            'by_specialization'  => $bySpecialization,
        ], 200);
    }

    // ==================
    // EXPIRY REPORT
    // ==================
    public function expiryReport(Request $request)
    {
        // Expiring within 30 days
        $expiringSoon = ProductBatch::with(['product', 'product.supplier'])
            ->expiringSoon(30)
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function ($batch) {
                return [
                    'product_code'       => $batch->product?->product_code,
                    'brand_name'         => $batch->product?->brand_name,
                    'supplier'           => $batch->product?->supplier?->company,
                    'lot_number'         => $batch->lot_number,
                    'expiry_date'        => $batch->expiry_date?->format('M d, Y'),
                    'remaining_quantity' => $batch->remaining_quantity,
                    'days_until_expiry'  => now()->diffInDays($batch->expiry_date),
                ];
            });

        // Expiring within 7 days
        $expiringUrgent = ProductBatch::with(['product'])
            ->expiringSoon(7)
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function ($batch) {
                return [
                    'product_code'       => $batch->product?->product_code,
                    'brand_name'         => $batch->product?->brand_name,
                    'lot_number'         => $batch->lot_number,
                    'expiry_date'        => $batch->expiry_date?->format('M d, Y'),
                    'remaining_quantity' => $batch->remaining_quantity,
                    'days_until_expiry'  => now()->diffInDays($batch->expiry_date),
                ];
            });

        // Already expired
        $expired = ProductBatch::with(['product'])
            ->expired()
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function ($batch) {
                return [
                    'product_code'       => $batch->product?->product_code,
                    'brand_name'         => $batch->product?->brand_name,
                    'lot_number'         => $batch->lot_number,
                    'expiry_date'        => $batch->expiry_date?->format('M d, Y'),
                    'remaining_quantity' => $batch->remaining_quantity,
                ];
            });

        return response()->json([
            'expiring_soon'   => $expiringSoon,
            'expiring_urgent' => $expiringUrgent,
            'expired'         => $expired,
            'summary'         => [
                'expiring_soon_count'   => $expiringSoon->count(),
                'expiring_urgent_count' => $expiringUrgent->count(),
                'expired_count'         => $expired->count(),
            ]
        ], 200);
    }

    public function paymentsReport(Request $request)
    {
        $aging  = $request->input('aging');
        $status = $request->input('status'); // 'paid' or null (unpaid/partial)

        $authUser   = \Illuminate\Support\Facades\Auth::user();
        $areaCodeId = ($authUser->hasRole('sales_rep') && $authUser->area_code_id)
                        ? $authUser->area_code_id
                        : null;

        $scope = fn ($q) => $q->when($areaCodeId, fn ($q) => $q->where('area_code_id', $areaCodeId));

        $query = Sale::with(['customer', 'deliveries' => fn ($q) => $q->orderBy('delivery_date', 'desc')])
            ->where('status', '!=', 'cancelled')
            ->where($scope);

        if ($status === 'paid') {
            $query->where('payment_status', 'paid');
        } else {
            $query->whereIn('payment_status', ['unpaid', 'partial']);
            if (in_array($aging, ['15', '30', '60'])) {
                $query->whereDate('sale_date', '<=', now()->subDays((int) $aging)->toDateString());
            }
        }

        $sales = $query->orderBy('sale_date', 'asc')->get();

        $result = $sales->map(function ($sale) {
            $latestDelivery  = $sale->deliveries->first();
            $referenceDate   = $latestDelivery?->delivery_date ?? $sale->sale_date;
            return [
                'sale_number'    => $sale->sale_number,
                'invoice_number' => $sale->invoice_number,
                'customer'       => $sale->customer?->name,
                'sale_date'      => $sale->sale_date?->format('M d, Y'),
                'delivery_date'  => $latestDelivery?->delivery_date?->format('M d, Y'),
                'days_overdue'   => (int) $referenceDate->diffInDays(now()),
                'total_amount'   => $sale->total_amount,
                'amount_paid'    => $sale->amount_paid,
                'balance'        => $sale->balance,
                'payment_status' => $sale->payment_status,
            ];
        });

        // Aging bucket counts scoped to same user territory
        $allUnpaid = Sale::whereIn('payment_status', ['unpaid', 'partial'])
            ->where('status', '!=', 'cancelled')
            ->where($scope)
            ->select(['id', 'sale_date'])
            ->get();

        return response()->json([
            'sales'   => $result,
            'summary' => [
                'total_unpaid'      => Sale::whereIn('payment_status', ['unpaid', 'partial'])->where('status', '!=', 'cancelled')->where($scope)->count(),
                'total_paid'        => Sale::where('payment_status', 'paid')->where('status', '!=', 'cancelled')->where($scope)->count(),
                'total_balance'     => (float) Sale::whereIn('payment_status', ['unpaid', 'partial'])->where('status', '!=', 'cancelled')->where($scope)->sum('balance'),
                'total_amount'      => (float) $result->sum('total_amount'),
                'total_paid_amount' => (float) Sale::where('payment_status', 'paid')->where('status', '!=', 'cancelled')->where($scope)->sum('amount_paid'),
                'over_15_days'      => $allUnpaid->filter(fn ($s) => $s->sale_date->diffInDays(now()) > 15)->count(),
                'over_30_days'      => $allUnpaid->filter(fn ($s) => $s->sale_date->diffInDays(now()) > 30)->count(),
                'over_60_days'      => $allUnpaid->filter(fn ($s) => $s->sale_date->diffInDays(now()) > 60)->count(),
            ],
        ], 200);
    }
}