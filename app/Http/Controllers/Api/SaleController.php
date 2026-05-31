<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    // Get all sales
    public function index(Request $request)
    {
        $query = Sale::with(['customer', 'createdBy', 'items.product']);

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->payment_status) {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by customer
        if ($request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by date range
        if ($request->from && $request->to) {
            $query->whereBetween('sale_date', [$request->from, $request->to]);
        }

        // Search by sale number or invoice number
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('sale_number', 'like', "%{$request->search}%")
                  ->orWhere('invoice_number', 'like', "%{$request->search}%")
                  ->orWhere('or_number', 'like', "%{$request->search}%");
            });
        }

        $sales = $query->latest()->paginate(10);

        return response()->json([
            'sales' => SaleResource::collection($sales),
            'pagination' => [
                'total'        => $sales->total(),
                'per_page'     => $sales->perPage(),
                'current_page' => $sales->currentPage(),
                'last_page'    => $sales->lastPage(),
                'from'         => $sales->firstItem(),
                'to'           => $sales->lastItem(),
            ]
        ], 200);


    }

    // Get single sale
    public function show(Sale $sale)
    {
        return response()->json([
            'sale' => new SaleResource(
                $sale->load(['customer', 'createdBy', 'items.product', 'payments.receivedBy'])
            )
        ], 200);
    }

    // Create sale
    public function store(Request $request)
    {
        $request->validate([
            'customer_id'      => 'required|exists:customers,id',
            'sale_date'        => 'required|date',
            'invoice_number'   => 'nullable|string|max:255',
            'payment_method'   => 'required|in:cash,check,bank_transfer',
            'notes'            => 'nullable|string',
            'items'            => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Create Sale
            $sale = Sale::create([
                'customer_id'    => $request->customer_id,
                'created_by'     => auth()->id(),
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

            // Create Sale Items
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);

                // Get batch using FEFO
                $batch = ProductBatch::where('product_id', $item['product_id'])
                            ->FEFO()
                            ->first();

                if (!$batch) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "No available stock for {$product->brand_name}"
                    ], 422);
                }

                if ($batch->remaining_quantity < $item['quantity']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Insufficient stock for {$product->brand_name}. Available: {$batch->remaining_quantity}"
                    ], 422);
                }

                $totalPrice = $item['quantity'] * $item['unit_price'];
                $totalAmount += $totalPrice;

                SaleItem::create([
                    'sale_id'          => $sale->id,
                    'product_id'       => $item['product_id'],
                    'product_batch_id' => $batch->id,
                    'lot_number'       => $batch->lot_number,
                    'expiry_date'      => $batch->expiry_date,
                    'quantity'         => $item['quantity'],
                    'unit_price'       => $item['unit_price'],
                    'total_price'      => $totalPrice,
                ]);
            }

            // Update total amount
            $sale->update([
                'total_amount' => $totalAmount,
                'balance'      => $totalAmount,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Sale created successfully',
                'sale'    => new SaleResource($sale->load(['customer', 'items.product']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create sale',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Confirm sale → deduct stock
    public function confirm(Sale $sale)
    {
        if ($sale->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft sales can be confirmed'
            ], 422);
        }

        DB::beginTransaction();
        try {
            foreach ($sale->items as $item) {
                $batch = $item->batch;

                // Deduct stock from batch
                $batch->decrement('remaining_quantity', $item->quantity);

                // Update batch status if depleted
                if ($batch->remaining_quantity <= 0) {
                    $batch->update(['status' => 'depleted']);
                }

                // Update product stock
                $item->product->decrement('stock', $item->quantity);

                // Create Inventory Log
                InventoryLog::create([
                    'product_id'       => $item->product_id,
                    'product_batch_id' => $batch->id,
                    'created_by'       => auth()->id(),
                    'type'             => 'sale',
                    'quantity'         => -$item->quantity,
                    'previous_stock'   => $item->product->stock + $item->quantity,
                    'new_stock'        => $item->product->stock,
                    'reference'        => $sale->sale_number,
                    'remarks'          => "Sale to {$sale->customer->name}. Invoice: {$sale->invoice_number}",
                ]);
            }

            // Confirm sale
            $sale->update(['status' => 'confirmed']);

            DB::commit();

            return response()->json([
                'message' => 'Sale confirmed successfully',
                'sale'    => new SaleResource($sale->load(['customer', 'items.product']))
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to confirm sale',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Cancel sale
    public function cancel(Sale $sale)
    {
        if ($sale->status === 'cancelled') {
            return response()->json([
                'message' => 'Sale is already cancelled'
            ], 422);
        }

        if ($sale->status === 'confirmed') {
            return response()->json([
                'message' => 'Confirmed sales cannot be cancelled. Please create a return instead.'
            ], 422);
        }

        $sale->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Sale cancelled successfully'
        ], 200);
    }

    // Delete sale (only draft)
    public function destroy(Sale $sale)
    {
        if ($sale->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft sales can be deleted'
            ], 422);
        }

        $sale->items()->delete();
        $sale->delete();

        return response()->json([
            'message' => 'Sale deleted successfully'
        ], 200);
    }
}