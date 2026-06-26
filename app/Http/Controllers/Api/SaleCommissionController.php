<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleCommission;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SaleCommissionController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();

        // Sales reps are always scoped to their own territory; others may filter by area code
        if ($authUser->hasRole('sales_rep') && $authUser->area_code_id) {
            $areaCodeId = $authUser->area_code_id;
        } else {
            $areaCodeId = $request->input('area_code_id') ?: null;
        }

        $query = Sale::with(['customer', 'items.product', 'deliveries', 'payments', 'commission.collectedBy'])
            ->where('status', 'confirmed')
            ->when($areaCodeId, fn ($q) => $q->where('area_code_id', $areaCodeId));

        if ($status === 'pending') {
            // Confirmed but customer hasn't paid yet and commission not collected
            $query->whereIn('payment_status', ['unpaid', 'partial'])
                  ->whereDoesntHave('commission', fn ($q) => $q->whereNotNull('collected_at'));
        } elseif ($status === 'for_release') {
            // Customer paid, commission not yet collected
            $query->where('payment_status', 'paid')
                  ->whereDoesntHave('commission', fn ($q) => $q->whereNotNull('collected_at'));
        } elseif ($status === 'collected') {
            $query->whereHas('commission', fn ($q) => $q->whereNotNull('collected_at'));
        }

        $sales  = $query->orderBy('sale_date', 'desc')->get();
        $result = $sales->map(fn ($s) => $this->formatSale($s));

        // Summary scoped to same territory/filter
        $allSales = Sale::with(['items.product', 'commission'])
            ->where('status', 'confirmed')
            ->when($areaCodeId, fn ($q) => $q->where('area_code_id', $areaCodeId))
            ->get();

        $summary = [
            'pending_total'     => 0, 'pending_count'     => 0,
            'for_release_total' => 0, 'for_release_count' => 0,
            'collected_total'   => 0, 'collected_count'   => 0,
        ];

        foreach ($allSales as $s) {
            $amount      = $this->calcCommission($s);
            $isCollected = $s->commission?->collected_at !== null;
            $isPaid      = $s->payment_status === 'paid';

            if ($isCollected) {
                $summary['collected_total'] += $amount;
                $summary['collected_count'] += 1;
            } elseif ($isPaid) {
                $summary['for_release_total'] += $amount;
                $summary['for_release_count'] += 1;
            } else {
                $summary['pending_total'] += $amount;
                $summary['pending_count'] += 1;
            }
        }

        return response()->json([
            'sales'   => $result,
            'summary' => [
                'pending_total'     => round($summary['pending_total'], 2),
                'pending_count'     => $summary['pending_count'],
                'for_release_total' => round($summary['for_release_total'], 2),
                'for_release_count' => $summary['for_release_count'],
                'collected_total'   => round($summary['collected_total'], 2),
                'collected_count'   => $summary['collected_count'],
            ],
        ]);
    }

    public function updateAmount(Request $request, Sale $sale)
    {
        $request->validate([
            'commission_amount' => 'required|numeric|min:0',
            'cost_overrides'    => 'nullable|array',
        ]);

        SaleCommission::updateOrCreate(
            ['sale_id' => $sale->id],
            [
                'commission_amount' => round((float) $request->commission_amount, 2),
                'cost_overrides'    => $request->cost_overrides,
            ]
        );

        return response()->json(['message' => 'Commission amount updated']);
    }

    public function collect(Request $request, Sale $sale)
    {
        $request->validate([
            'notes'             => 'nullable|string|max:500',
            'collected_date'    => 'nullable|date',
            'commission_amount' => 'nullable|numeric|min:0',
        ]);

        if ($sale->payment_status !== 'paid') {
            return response()->json(['message' => 'Commission can only be collected for fully paid sales'], 422);
        }

        $sale->load('items.product');
        $commissionAmount = $request->filled('commission_amount')
            ? (float) $request->commission_amount
            : ($sale->commission?->commission_amount ?? $this->calcCommission($sale));

        $collectedAt = $request->collected_date
            ? \Carbon\Carbon::parse($request->collected_date)
            : now();

        SaleCommission::updateOrCreate(
            ['sale_id' => $sale->id],
            [
                'commission_amount' => round($commissionAmount, 2),
                'collected_at'      => $collectedAt,
                'collected_by'      => Auth::id(),
                'notes'             => $request->notes,
            ]
        );

        $this->logActivity(
            action:      'COLLECT',
            module:      'Sales Commission',
            description: "Commission collected for sale: {$sale->sale_number} — ₱" . number_format($commissionAmount, 2),
        );

        return response()->json(['message' => 'Commission marked as collected']);
    }

    private function calcCommission(Sale $sale): float
    {
        return (float) $sale->items->sum(function ($item) {
            $cost = (float) ($item->product?->acquisition_cost ?? 0);
            return ((float) $item->unit_price - $cost) * (int) $item->quantity * 0.5;
        });
    }

    private function formatSale(Sale $sale): array
    {
        $latestDelivery = $sale->deliveries->sortByDesc('delivery_date')->first();
        $latestPayment  = $sale->payments->sortByDesc('payment_date')->first();
        $customer       = $sale->customer;

        return [
            'id'                => $sale->id,
            'sale_number'       => $sale->sale_number,
            'invoice_number'    => $sale->invoice_number,
            'or_number'         => $sale->or_number,
            'payment_method'    => $sale->payment_method,
            'customer'          => $customer?->name,
            'customer_address'  => $customer?->full_address,
            'customer_contact'  => $customer?->contact_no,
            'sale_date'         => $sale->sale_date?->format('M d, Y'),
            'delivery_date'     => $latestDelivery?->delivery_date?->format('M d, Y'),
            'payment_date'      => $latestPayment?->payment_date?->format('M d, Y'),
            'total_amount'      => $sale->total_amount,
            'payment_status'    => $sale->payment_status,
            'delivery_status'   => $sale->delivery_status,
            'commission_amount' => $sale->commission?->commission_amount
                                    ? (float) $sale->commission->commission_amount
                                    : round($this->calcCommission($sale), 2),
            'cost_overrides'    => $sale->commission?->cost_overrides ?? [],
            'collected_at'      => $sale->commission?->collected_at?->format('M d, Y'),
            'collected_by'      => $sale->commission?->collectedBy?->name,
            'items'             => $sale->items->map(fn ($item) => [
                'product_name'     => $item->product_name,
                'quantity'         => (int) $item->quantity,
                'unit_price'       => (float) $item->unit_price,
                'acquisition_cost' => (float) ($item->product?->acquisition_cost ?? 0),
                'commission'       => round(((float) $item->unit_price - (float) ($item->product?->acquisition_cost ?? 0)) * (int) $item->quantity * 0.5, 2),
            ]),
        ];
    }
}
