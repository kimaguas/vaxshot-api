<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // ==================
        // KPI CARDS
        // ==================
        $today        = now()->toDateString();
        $thisMonth    = now()->startOfMonth()->toDateString();
        $thisMonthEnd = now()->endOfMonth()->toDateString();

        // Sales Rep area code restriction
        $authUser      = Auth::user();
        $areaCodeId    = ($authUser->hasRole('sales_rep') && $authUser->area_code_id)
                            ? $authUser->area_code_id
                            : null;

        $saleScope = fn ($q) => $q->when($areaCodeId, fn ($q) => $q->where('area_code_id', $areaCodeId));

        // Sales today
        $salesToday = Sale::where('status', 'confirmed')
            ->whereDate('sale_date', $today)
            ->where($saleScope)
            ->sum('total_amount');

        // Sales this month
        $salesThisMonth = Sale::where('status', 'confirmed')
            ->whereBetween('sale_date', [$thisMonth, $thisMonthEnd])
            ->where($saleScope)
            ->sum('total_amount');

        // Total revenue this month (amount paid)
        $revenueThisMonth = Sale::where('status', 'confirmed')
            ->whereBetween('sale_date', [$thisMonth, $thisMonthEnd])
            ->where($saleScope)
            ->sum('amount_paid');

        // Outstanding balance
        $outstandingBalance = Sale::where('status', 'confirmed')
            ->where('payment_status', '!=', 'paid')
            ->where($saleScope)
            ->sum('balance');

        // Total products
        $totalProducts = Product::active()->count();

        // Low stock products
        $lowStockCount = Product::active()
            ->whereColumn('stock', '<=', 'maintaining_stock')
            ->count();

        // Expiring soon (30 days)
        $expiringSoonCount = ProductBatch::expiringSoon(30)->count();

        // Expired batches
        $expiredCount = ProductBatch::expired()->count();

        // Total customers
        $totalCustomers = Customer::active()->count();

        // Total unpaid sales
        $unpaidSales = Sale::where('status', 'confirmed')
            ->where('payment_status', 'unpaid')
            ->where($saleScope)
            ->count();

        // ==================
        // SALES TREND (last 7 days)
        // ==================
        $salesTrend = Sale::where('status', 'confirmed')
            ->whereBetween('sale_date', [
                now()->subDays(6)->toDateString(),
                $today
            ])
            ->where($saleScope)
            ->groupBy('sale_date')
            ->select(
                'sale_date',
                DB::raw('COUNT(*) as total_sales'),
                DB::raw('SUM(total_amount) as total_amount')
            )
            ->orderBy('sale_date', 'asc')
            ->get()
            ->map(function ($item) {
                return [
                    'date'         => $item->sale_date->format('M d'),
                    'total_sales'  => $item->total_sales,
                    'total_amount' => $item->total_amount,
                ];
            });

        // ==================
        // TOP 5 SELLING PRODUCTS
        // ==================
        $topProducts = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.status', 'confirmed')
            ->whereMonth('sales.sale_date', now()->month)
            ->when($areaCodeId, fn ($q) => $q->where('sales.area_code_id', $areaCodeId))
            ->groupBy('sale_items.product_id', 'products.brand_name')
            ->select(
                'products.brand_name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_price) as total_amount')
            )
            ->orderBy('total_quantity', 'desc')
            ->limit(5)
            ->get();

        // ==================
        // PAYMENT STATUS SUMMARY
        // ==================
        $paymentSummary = [
            'paid'    => Sale::where('status', 'confirmed')->where('payment_status', 'paid')
                            ->where($saleScope)->count(),
            'partial' => Sale::where('status', 'confirmed')->where('payment_status', 'partial')
                            ->where($saleScope)->count(),
            'unpaid'  => Sale::where('status', 'confirmed')->where('payment_status', 'unpaid')
                            ->where($saleScope)->count(),
        ];

        // ==================
        // ALERTS
        // ==================

        // Low stock products
        $lowStockProducts = Product::active()
            ->whereColumn('stock', '<=', 'maintaining_stock')
            ->with('supplier')
            ->get()
            ->map(function ($product) {
                return [
                    'product_code'     => $product->product_code,
                    'brand_name'       => $product->brand_name,
                    'stock'            => $product->stock,
                    'maintaining_stock'=> $product->maintaining_stock,
                ];
            });

        // Expiring soon batches
        $expiringSoon = ProductBatch::with('product')
            ->expiringSoon(30)
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(function ($batch) {
                return [
                    'brand_name'         => $batch->product?->brand_name,
                    'product_code'       => $batch->product?->product_code,
                    'lot_number'         => $batch->lot_number,
                    'expiry_date'        => $batch->expiry_date?->format('M d, Y'),
                    'remaining_quantity' => $batch->remaining_quantity,
                    'days_until_expiry'  => now()->diffInDays($batch->expiry_date),
                ];
            });

        // Unpaid sales
        $unpaidSalesList = Sale::with('customer')
            ->where('status', 'confirmed')
            ->where('payment_status', 'unpaid')
            ->where($saleScope)
            ->latest()
            ->limit(5)
            ->get()
            ->map(function ($sale) {
                return [
                    'sale_number'  => $sale->sale_number,
                    'customer'     => $sale->customer?->name,
                    'sale_date'    => $sale->sale_date?->format('M d, Y'),
                    'total_amount' => $sale->total_amount,
                    'balance'      => $sale->balance,
                ];
            });

        return response()->json([
            'kpi' => [
                'sales_today'         => $salesToday,
                'sales_this_month'    => $salesThisMonth,
                'revenue_this_month'  => $revenueThisMonth,
                'outstanding_balance' => $outstandingBalance,
                'total_products'      => $totalProducts,
                'low_stock_count'     => $lowStockCount,
                'expiring_soon_count' => $expiringSoonCount,
                'expired_count'       => $expiredCount,
                'total_customers'     => $totalCustomers,
                'unpaid_sales'        => $unpaidSales,
            ],
            'charts' => [
                'sales_trend'     => $salesTrend,
                'top_products'    => $topProducts,
                'payment_summary' => $paymentSummary,
            ],
            'alerts' => [
                'low_stock'     => $lowStockProducts,
                'expiring_soon' => $expiringSoon,
                'unpaid_sales'  => $unpaidSalesList,
            ],
        ], 200);
    }
}