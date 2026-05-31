<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderReceiptResource;
use App\Models\InventoryLog;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderReceiptController extends Controller
{
    // Get all receipts for a PO
    public function index(PurchaseOrder $purchaseOrder)
    {
        $receipts = $purchaseOrder->receipts()
                    ->with(['receivedBy', 'items.product'])
                    ->latest()
                    ->get();

        return response()->json([
            'receipts' => PurchaseOrderReceiptResource::collection($receipts)
        ], 200);
    }

    // Receive delivery
    public function store(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate([
            'receipt_date'              => 'required|date',
            'notes'                     => 'nullable|string',
            'items'                     => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.product_id'        => 'required|exists:products,id',
            'items.*.lot_number'        => 'required|string',
            'items.*.expiry_date'       => 'required|date|after:today',
            'items.*.quantity_received' => 'required|integer|min:1',
            'items.*.unit_cost'         => 'required|numeric|min:0',
        ]);

        // Can't receive cancelled PO
        if ($purchaseOrder->status === 'cancelled') {
            return response()->json([
                'message' => 'Cannot receive a cancelled Purchase Order'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Create Receipt
            $receipt = PurchaseOrderReceipt::create([
                'purchase_order_id' => $purchaseOrder->id,
                'received_by'       => auth()->id(),
                'receipt_date'      => $request->receipt_date,
                'notes'             => $request->notes,
                'status'            => 'complete',
            ]);

            foreach ($request->items as $item) {
                // Create Receipt Item
                $receiptItem = PurchaseOrderReceiptItem::create([
                    'purchase_order_receipt_id' => $receipt->id,
                    'purchase_order_item_id'    => $item['purchase_order_item_id'],
                    'product_id'                => $item['product_id'],
                    'lot_number'                => $item['lot_number'],
                    'expiry_date'               => $item['expiry_date'],
                    'quantity_received'         => $item['quantity_received'],
                    'unit_cost'                 => $item['unit_cost'],
                ]);

                // Create Product Batch
                $batch = ProductBatch::create([
                    'product_id'                     => $item['product_id'],
                    'purchase_order_receipt_item_id' => $receiptItem->id,
                    'lot_number'                     => $item['lot_number'],
                    'expiry_date'                    => $item['expiry_date'],
                    'quantity'                       => $item['quantity_received'],
                    'remaining_quantity'             => $item['quantity_received'],
                    'unit_cost'                      => $item['unit_cost'],
                    'status'                         => 'active',
                ]);

                // Update PO Item quantity received
                $poItem = $purchaseOrder->items()->find($item['purchase_order_item_id']);
                $poItem->increment('quantity_received', $item['quantity_received']);

                // Update Product stock
                $poItem->product->increment('stock', $item['quantity_received']);

                // Create Inventory Log
                $previousStock = $poItem->product->stock - $item['quantity_received'];
                InventoryLog::create([
                    'product_id'       => $item['product_id'],
                    'product_batch_id' => $batch->id,
                    'created_by'       => auth()->id(),
                    'type'             => 'purchase',
                    'quantity'         => $item['quantity_received'],
                    'previous_stock'   => $previousStock,
                    'new_stock'        => $poItem->product->stock,
                    'reference'        => $purchaseOrder->po_number,
                    'remarks'          => "Received via {$receipt->receipt_number}. Lot: {$item['lot_number']}",
                ]);
            }

            // Update PO status
            $allReceived = $purchaseOrder->items->every(fn($i) => $i->isFullyReceived());
            $purchaseOrder->update([
                'status' => $allReceived ? 'received' : 'partial'
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Delivery received successfully',
                'receipt' => new PurchaseOrderReceiptResource(
                    $receipt->load(['receivedBy', 'items.product'])
                )
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to process receipt',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}