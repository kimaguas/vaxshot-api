<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    use LogsActivity;

    // Get all sales
    public function index(Request $request)
    {
        // Reusable filter closure
        $filters = function ($q) use ($request) {
            if ($request->status) {
                $q->where('status', $request->status);
            }
            if ($request->payment_status) {
                $q->where('payment_status', $request->payment_status);
            }
            if ($request->customer_id) {
                $q->where('customer_id', $request->customer_id);
            }

            if ($request->area_code_id) {
                $q->whereHas('customer', fn ($cq) =>
                    $cq->where('area_code_id', $request->area_code_id)
                );
            }

            // Auto-enforce area code filter for Sales Rep users
            $authUser = auth()->user();
            if ($authUser && $authUser->hasRole('sales_rep') && $authUser->area_code_id) {
                $q->whereHas('customer', fn ($cq) =>
                    $cq->where('area_code_id', $authUser->area_code_id)
                );
            }

            // Date filters (mutually exclusive)
            if ($request->date) {
                $q->whereDate('sale_date', $request->date);
            } elseif ($request->month && $request->year) {
                $q->whereMonth('sale_date', $request->month)
                  ->whereYear('sale_date', $request->year);
            } elseif ($request->from && $request->to) {
                $q->whereBetween('sale_date', [$request->from, $request->to]);
            } elseif ($request->as_of) {
                $q->whereDate('sale_date', '>=', $request->as_of)
                  ->whereDate('sale_date', '<=', now()->toDateString());
            }

            if ($request->search) {
                $q->where(function ($sq) use ($request) {
                    $sq->where('sale_number',    'like', "%{$request->search}%")
                       ->orWhere('invoice_number','like', "%{$request->search}%")
                       ->orWhere('or_number',     'like', "%{$request->search}%")
                       ->orWhereHas('customer', fn ($cq) =>
                           $cq->where('name', 'like', "%{$request->search}%")
                       );
                });
            }
        };

        // Totals across the full filtered result set (before pagination)
        $totals = Sale::where($filters)
            ->selectRaw('
                COUNT(*)                     AS total_count,
                COALESCE(SUM(total_amount),0) AS total_sales,
                COALESCE(SUM(amount_paid),0)  AS total_paid,
                COALESCE(SUM(balance),0)      AS total_balance
            ')
            ->first();

        // Sorting
        $allowedSorts = ['id', 'sale_date', 'invoice_number', 'total_amount'];
        $sortBy    = in_array($request->sort_by, [...$allowedSorts, 'customer'])
                        ? $request->sort_by
                        : 'id';
        $sortOrder = $request->sort_order === 'asc' ? 'asc' : 'desc';

        $query = Sale::with(['customer', 'createdBy', 'items'])->where($filters);

        if ($sortBy === 'customer') {
            $query->join('customers', 'sales.customer_id', '=', 'customers.id')
                  ->orderBy('customers.name', $sortOrder)
                  ->select('sales.*');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $sales = $query->paginate(10);

        return response()->json([
            'sales' => SaleResource::collection($sales),
            'pagination' => [
                'total'        => $sales->total(),
                'per_page'     => $sales->perPage(),
                'current_page' => $sales->currentPage(),
                'last_page'    => $sales->lastPage(),
                'from'         => $sales->firstItem(),
                'to'           => $sales->lastItem(),
            ],
            'totals' => [
                'count'         => (int)   $totals->total_count,
                'total_sales'   => (float) $totals->total_sales,
                'total_paid'    => (float) $totals->total_paid,
                'total_balance' => (float) $totals->total_balance,
            ],
        ], 200);
    }

    // Get single sale
    public function show(Sale $sale)
    {
        return response()->json([
            'sale' => new SaleResource(
                $sale->load(['customer', 'createdBy', 'items', 'payments.receivedBy'])
            )
        ], 200);
    }

    // Create sale
    public function store(Request $request)
    {
        $request->validate([
            'customer_id'        => 'required|exists:customers,id',
            'sale_date'          => 'required|date',
            'invoice_number'     => 'nullable|string|max:255|unique:sales,invoice_number',
            'payment_method'     => 'required|in:cash,check,bank_transfer',
            'notes'              => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $sale = Sale::create([
                'customer_id'    => $request->customer_id,
                'created_by'     => Auth::id(),
                'sale_date'      => $request->sale_date,
                'invoice_number' => $request->invoice_number,
                'payment_method' => $request->payment_method,
                'payment_status' => 'unpaid',
                'total_amount'   => 0,
                'amount_paid'    => 0,
                'balance'        => 0,
                'status'         => 'draft',
                'notes'          => $request->notes,
            ]);

            $totalAmount = 0;

            foreach ($request->items as $item) {
                $catalog      = Product::find($item['product_id']);
                $totalPrice   = $item['quantity'] * $item['unit_price'];
                $totalAmount += $totalPrice;

                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $item['product_id'],
                    'product_name' => $catalog?->brand_name,
                    'lot_number'   => $catalog?->lot_no,
                    'expiry_date'  => $catalog?->expiry_date,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'total_price'  => $totalPrice,
                ]);
            }

            $sale->update([
                'total_amount' => $totalAmount,
                'balance'      => $totalAmount,
            ]);

            $sale->load('customer');

            $this->logActivity(
                action      : 'CREATE',
                module      : 'Sales',
                description : "Created sale: {$sale->sale_number} for {$sale->customer->name} - ₱{$totalAmount}",
                newData     : $sale->toArray()
            );

            DB::commit();

            return response()->json([
                'message' => 'Sale created successfully',
                'sale'    => new SaleResource($sale->load(['customer', 'items']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create sale',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Update editable sale details (invoice number, OR number, notes, payment method)
    public function update(Request $request, Sale $sale)
    {
        $request->validate([
            'invoice_number' => "nullable|string|max:255|unique:sales,invoice_number,{$sale->id}",
            'or_number'      => 'nullable|string|max:255',
            'payment_method' => 'sometimes|in:cash,check,bank_transfer',
            'notes'          => 'nullable|string',
        ]);

        $oldData = $sale->only(['invoice_number', 'or_number', 'payment_method', 'notes']);

        $sale->update($request->only(['invoice_number', 'or_number', 'payment_method', 'notes']));

        $this->logActivity(
            action:      'UPDATE',
            module:      'Sales',
            description: "Updated details for sale: {$sale->sale_number}",
            oldData:     $oldData,
            newData:     $sale->only(['invoice_number', 'or_number', 'payment_method', 'notes'])
        );

        return response()->json([
            'message' => 'Sale updated successfully',
            'sale'    => new SaleResource($sale->load(['customer', 'items', 'payments.receivedBy'])),
        ], 200);
    }

    // Confirm sale → deduct stock
    public function confirm(Request $request, Sale $sale)
    {
        if ($sale->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft sales can be confirmed'
            ], 422);
        }

        $force = $request->boolean('force', false);

        DB::beginTransaction();
        try {
            $items = $sale->items()->with('product')->get();

            foreach ($items as $saleItem) {
                $qtyNeeded = $saleItem->quantity;
                $productId = $saleItem->product_id;

                $totalAvailable = ProductBatch::where('product_id', $productId)
                    ->active()
                    ->sum('remaining_quantity');

                if (!$force && $totalAvailable < $qtyNeeded) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Insufficient stock for \"{$saleItem->product_name}\". Available: {$totalAvailable}, Required: {$qtyNeeded}",
                    ], 422);
                }

                // Deduct using FEFO (earliest expiry first)
                $batches = ProductBatch::where('product_id', $productId)->FEFO()->get();

                $remaining        = $qtyNeeded;
                $firstBatchId     = null;
                $firstBatchLot    = null;
                $firstBatchExpiry = null;

                foreach ($batches as $batch) {
                    if ($remaining <= 0) break;

                    $deduct       = min($batch->remaining_quantity, $remaining);
                    $newRemaining = $batch->remaining_quantity - $deduct;

                    $batch->update([
                        'remaining_quantity' => $newRemaining,
                        'status'             => $newRemaining <= 0 ? 'depleted' : 'active',
                    ]);

                    if ($firstBatchId === null) {
                        $firstBatchId     = $batch->id;
                        $firstBatchLot    = $batch->lot_number;
                        $firstBatchExpiry = $batch->expiry_date;
                    }

                    $remaining -= $deduct;
                }

                $previousStock = (int) ($saleItem->product->stock ?? $totalAvailable);

                // When force=true and batches didn't cover everything, stock goes negative
                $newStock = $remaining > 0
                    ? $previousStock - $qtyNeeded
                    : (int) ProductBatch::where('product_id', $productId)
                        ->where('status', '!=', 'depleted')
                        ->sum('remaining_quantity');

                Product::where('id', $productId)->update(['stock' => $newStock]);

                $saleItem->update([
                    'product_batch_id' => $firstBatchId,
                    'lot_number'       => $firstBatchLot,
                    'expiry_date'      => $firstBatchExpiry,
                ]);

                $remarks = $remaining > 0
                    ? "Sale confirmed (backorder, stock: {$newStock}): {$sale->sale_number}"
                    : "Sale confirmed: {$sale->sale_number}";

                InventoryLog::create([
                    'product_id'       => $productId,
                    'product_batch_id' => $firstBatchId,
                    'created_by'       => Auth::id(),
                    'type'             => 'sale',
                    'quantity'         => -$qtyNeeded,
                    'previous_stock'   => $previousStock,
                    'new_stock'        => $newStock,
                    'reference'        => $sale->sale_number,
                    'remarks'          => $remarks,
                ]);
            }

            $sale->update(['status' => 'confirmed']);

            $this->logActivity(
                action      : 'CONFIRM',
                module      : 'Sales',
                description : "Confirmed sale: {$sale->sale_number} for {$sale->customer->name} - ₱{$sale->total_amount}",
            );

            DB::commit();

            return response()->json([
                'message' => 'Sale confirmed successfully',
                'sale'    => new SaleResource($sale->load(['customer', 'items']))
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to confirm sale: ' . $e->getMessage()
            ], 500);
        }
    }

    // Update items on a draft sale
    public function updateItems(Request $request, Sale $sale)
    {
        if ($sale->status !== 'draft') {
            return response()->json([
                'message' => 'Items can only be edited on draft sales'
            ], 422);
        }

        $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $sale->items()->delete();

            $totalAmount = 0;

            foreach ($request->items as $item) {
                $product     = Product::find($item['product_id']);
                $totalPrice  = $item['quantity'] * $item['unit_price'];
                $totalAmount += $totalPrice;

                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $item['product_id'],
                    'product_name' => $product?->brand_name,
                    'lot_number'   => $product?->lot_no,
                    'expiry_date'  => $product?->expiry_date,
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'total_price'  => $totalPrice,
                ]);
            }

            $sale->update([
                'total_amount' => $totalAmount,
                'balance'      => $totalAmount - $sale->amount_paid,
            ]);

            $this->logActivity(
                action      : 'UPDATE',
                module      : 'Sales',
                description : "Updated items for sale: {$sale->sale_number}",
                newData     : ['total_amount' => $totalAmount]
            );

            DB::commit();

            return response()->json([
                'message' => 'Items updated successfully',
                'sale'    => new SaleResource($sale->load(['customer', 'items']))
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to update items',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Cancel sale
    public function cancel(Sale $sale)
    {
        if ($sale->status === 'cancelled') {
            return response()->json(['message' => 'Sale is already cancelled'], 422);
        }

        DB::beginTransaction();
        try {
            // Restore batch stock if the sale was confirmed (stock was deducted at confirmation)
            if ($sale->status === 'confirmed') {
                $items = $sale->items()->with('product')->get();

                foreach ($items as $saleItem) {
                    if (!$saleItem->product_batch_id) continue;

                    $batch = ProductBatch::find($saleItem->product_batch_id);
                    if (!$batch) continue;

                    $restoredQty  = $saleItem->quantity;
                    $newRemaining = $batch->remaining_quantity + $restoredQty;

                    $batch->update([
                        'remaining_quantity' => $newRemaining,
                        'status'             => 'active',
                    ]);

                    $newStock = (int) ProductBatch::where('product_id', $saleItem->product_id)
                        ->where('status', '!=', 'depleted')
                        ->sum('remaining_quantity');

                    Product::where('id', $saleItem->product_id)->update(['stock' => $newStock]);

                    InventoryLog::create([
                        'product_id'       => $saleItem->product_id,
                        'product_batch_id' => $saleItem->product_batch_id,
                        'created_by'       => Auth::id(),
                        'type'             => 'adjustment',
                        'quantity'         => $restoredQty,
                        'previous_stock'   => $newStock - $restoredQty,
                        'new_stock'        => $newStock,
                        'reference'        => $sale->sale_number,
                        'remarks'          => "Sale cancelled (stock restored): {$sale->sale_number}",
                    ]);
                }
            }

            $sale->update(['status' => 'cancelled']);

            $this->logActivity(
                action      : 'CANCEL',
                module      : 'Sales',
                description : "Cancelled sale: {$sale->sale_number}",
            );

            DB::commit();

            return response()->json(['message' => 'Sale cancelled successfully'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to cancel sale: ' . $e->getMessage()], 500);
        }
    }

    // Delete sale (only draft)
    public function destroy(Sale $sale)
    {
        if ($sale->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft sales can be deleted'
            ], 422);
        }

        $this->logActivity(
            action      : 'DELETE',
            module      : 'Sales',
            description : "Deleted draft sale: {$sale->sale_number}",
            oldData     : $sale->toArray()
        );

        $sale->items()->delete();
        $sale->delete();

        return response()->json([
            'message' => 'Sale deleted successfully'
        ], 200);
    }
}