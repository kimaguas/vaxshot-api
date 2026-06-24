<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleDelivery;
use App\Models\SaleDeliveryItem;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleDeliveryController extends Controller
{
    use LogsActivity;

    public function index(Sale $sale)
    {
        $deliveries = $sale->deliveries()
            ->with(['deliveredBy', 'items.saleItem.product'])
            ->latest()
            ->get()
            ->map(fn($d) => [
                'id'              => $d->id,
                'delivery_number' => $d->delivery_number,
                'delivery_date'   => $d->delivery_date?->format('Y-m-d'),
                'status'          => $d->status,
                'notes'           => $d->notes,
                'delivered_by'    => $d->deliveredBy?->name,
                'items'           => $d->items->map(fn($di) => [
                    'id'                 => $di->id,
                    'sale_item_id'       => $di->sale_item_id,
                    'product_name'       => $di->saleItem?->product_name ?? $di->saleItem?->product?->brand_name,
                    'lot_number'         => $di->saleItem?->lot_number,
                    'quantity_delivered' => $di->quantity_delivered,
                ]),
            ]);

        return response()->json(['deliveries' => $deliveries]);
    }

    public function store(Sale $sale, Request $request)
    {
        if ($sale->status !== 'confirmed') {
            return response()->json(['message' => 'Only confirmed sales can have deliveries recorded'], 422);
        }

        if ($sale->delivery_status === 'delivered') {
            return response()->json(['message' => 'This sale has already been fully delivered'], 422);
        }

        $request->validate([
            'delivery_date'              => 'required|date',
            'notes'                      => 'nullable|string|max:500',
            'items'                      => 'required|array|min:1',
            'items.*.sale_item_id'       => 'required|integer',
            'items.*.quantity_delivered' => 'required|integer|min:1',
        ]);

        // Load all sale items once
        $saleItems = $sale->items()->with('batch')->get()->keyBy('id');

        // Validate each delivery item
        foreach ($request->items as $idx => $item) {
            $saleItemId = $item['sale_item_id'];
            $qtyToDeliver = $item['quantity_delivered'];

            if (!isset($saleItems[$saleItemId])) {
                return response()->json([
                    'message' => "Item #{$idx}: sale_item_id {$saleItemId} does not belong to this sale",
                ], 422);
            }

            $saleItem = $saleItems[$saleItemId];

            // How much has already been delivered for this item?
            $alreadyDelivered = SaleDeliveryItem::whereIn(
                'sale_delivery_id',
                $sale->deliveries()->pluck('id')
            )->where('sale_item_id', $saleItemId)->sum('quantity_delivered');

            $remaining = $saleItem->quantity - $alreadyDelivered;

            if ($qtyToDeliver > $remaining) {
                return response()->json([
                    'message' => "Item \"{$saleItem->product_name}\": cannot deliver {$qtyToDeliver} — only {$remaining} remaining to deliver",
                ], 422);
            }

        }

        DB::beginTransaction();
        try {
            // Determine delivery status: is every sale item now fully covered?
            $deliveryStatus = 'complete';
            foreach ($saleItems as $saleItem) {
                $alreadyDelivered = SaleDeliveryItem::whereIn(
                    'sale_delivery_id',
                    $sale->deliveries()->pluck('id')
                )->where('sale_item_id', $saleItem->id)->sum('quantity_delivered');

                $thisItemDelivery = collect($request->items)
                    ->firstWhere('sale_item_id', $saleItem->id);

                $totalAfter = $alreadyDelivered + ($thisItemDelivery['quantity_delivered'] ?? 0);

                if ($totalAfter < $saleItem->quantity) {
                    $deliveryStatus = 'partial';
                    break;
                }
            }

            // Create delivery record
            $delivery = SaleDelivery::create([
                'sale_id'         => $sale->id,
                'delivered_by'    => Auth::id(),
                'delivery_date'   => $request->delivery_date,
                'status'          => $deliveryStatus,
                'notes'           => $request->notes,
            ]);

            // Process each item
            foreach ($request->items as $item) {
                $saleItem     = $saleItems[$item['sale_item_id']];
                $qtyToDeliver = $item['quantity_delivered'];

                SaleDeliveryItem::create([
                    'sale_delivery_id'   => $delivery->id,
                    'sale_item_id'       => $saleItem->id,
                    'quantity_delivered' => $qtyToDeliver,
                ]);

            }

            // Update sale delivery_status
            $sale->update([
                'delivery_status' => $deliveryStatus === 'complete' ? 'delivered' : 'partial',
            ]);

            $this->logActivity(
                action:      'DELIVER',
                module:      'Sales',
                description: "Delivery recorded: {$delivery->delivery_number} for {$sale->sale_number} [{$deliveryStatus}]",
                newData:     ['delivery_id' => $delivery->id, 'sale_id' => $sale->id, 'status' => $deliveryStatus],
            );

            DB::commit();

            return response()->json([
                'message'         => 'Delivery recorded successfully',
                'delivery_number' => $delivery->delivery_number,
                'delivery_status' => $deliveryStatus === 'complete' ? 'delivered' : 'partial',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to record delivery: ' . $e->getMessage()], 500);
        }
    }
}
