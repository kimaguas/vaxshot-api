<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    use LogsActivity;

    public function stats(Request $request)
    {
        $now        = now();
        $thirtyDays = now()->addDays(30);

        $base = Product::where('status', 'active')
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id));

        $total      = (clone $base)->count();
        $outOfStock = (clone $base)->where('stock', 0)->count();
        $lowStock   = (clone $base)
                        ->where('stock', '>', 0)
                        ->where('maintaining_stock', '>', 0)
                        ->whereColumn('stock', '<=', 'maintaining_stock')
                        ->count();
        $expiring   = (clone $base)
                        ->whereNotNull('expiry_date')
                        ->where('expiry_date', '>', $now)
                        ->where('expiry_date', '<=', $thirtyDays)
                        ->count();

        return response()->json([
            'total'         => $total,
            'low_or_out'    => $outOfStock + $lowStock,
            'expiring_soon' => $expiring,
        ]);
    }

    public function batches(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        $batches = ProductBatch::where('product_id', $request->product_id)
            ->orderBy('expiry_date', 'asc')
            ->get()
            ->map(fn($b) => [
                'id'                 => $b->id,
                'lot_number'         => $b->lot_number,
                'received_date'      => $b->created_at?->format('Y-m-d'),
                'expiry_date'        => $b->expiry_date?->format('Y-m-d'),
                'quantity'           => $b->quantity,
                'remaining_quantity' => $b->remaining_quantity,
                'unit_cost'          => (float) $b->unit_cost,
                'status'             => $b->status,
                'is_expiring_soon'   => $b->isExpiringSoon(),
                'is_expired'         => $b->isExpired(),
            ]);

        return response()->json(['batches' => $batches]);
    }

    public function adjust(Request $request)
    {
        $request->validate([
            'product_id'       => 'required|exists:products,id',
            'product_batch_id' => 'required|exists:product_batches,id',
            'type'             => 'required|in:adjustment,damaged,expired,return',
            'qty_change'       => 'required|integer|not_in:0',
            'remarks'          => 'nullable|string|max:500',
        ]);

        $batch   = ProductBatch::findOrFail($request->product_batch_id);
        $product = Product::findOrFail($request->product_id);

        if ($batch->product_id !== $product->id) {
            return response()->json(['message' => 'Batch does not belong to this product'], 422);
        }

        $newRemaining = $batch->remaining_quantity + $request->qty_change;

        if ($newRemaining < 0) {
            return response()->json([
                'message' => "Cannot deduct {$request->qty_change} — only {$batch->remaining_quantity} available in this batch",
            ], 422);
        }

        DB::beginTransaction();
        try {
            $previousStock = (int) ($product->stock ?? 0);

            $batch->update([
                'remaining_quantity' => $newRemaining,
                'status'             => $newRemaining <= 0 ? 'depleted' : 'active',
            ]);

            $newStock = (int) ProductBatch::where('product_id', $product->id)
                ->where('status', '!=', 'depleted')
                ->sum('remaining_quantity');

            $product->update(['stock' => $newStock]);

            InventoryLog::create([
                'product_id'       => $product->id,
                'product_batch_id' => $batch->id,
                'created_by'       => Auth::id(),
                'type'             => $request->type,
                'quantity'         => $request->qty_change,
                'previous_stock'   => $previousStock,
                'new_stock'        => $newStock,
                'reference'        => "Manual {$request->type}",
                'remarks'          => $request->remarks,
            ]);

            $this->logActivity(
                action:      'ADJUST',
                module:      'Inventory',
                description: "Stock adjusted: {$product->brand_name} (Lot: {$batch->lot_number}) → {$request->qty_change} [{$request->type}]",
                newData:     [
                    'product_id'  => $product->id,
                    'batch_id'    => $batch->id,
                    'lot_number'  => $batch->lot_number,
                    'qty_change'  => $request->qty_change,
                    'type'        => $request->type,
                    'new_stock'   => $newStock,
                ]
            );

            DB::commit();

            return response()->json([
                'message'       => 'Stock adjustment saved',
                'new_stock'     => $newStock,
                'new_remaining' => $newRemaining,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to adjust stock: ' . $e->getMessage(),
            ], 500);
        }
    }
}
