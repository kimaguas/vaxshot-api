<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    // Get all purchase orders
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'createdBy', 'items.product']);

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter by supplier
        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by date range
        if ($request->from && $request->to) {
            $query->whereBetween('order_date', [$request->from, $request->to]);
        }

        // Search by PO number
        if ($request->search) {
            $query->where('po_number', 'like', "%{$request->search}%");
        }

        $orders = $query->latest()->get();

        return response()->json([
            'purchase_orders' => PurchaseOrderResource::collection($orders)
        ], 200);
    }

    // Get single purchase order
    public function show(PurchaseOrder $purchaseOrder)
    {
        return response()->json([
            'purchase_order' => new PurchaseOrderResource(
                $purchaseOrder->load(['supplier', 'createdBy', 'items.product', 'receipts.items.product'])
            )
        ], 200);
    }

    // Create purchase order
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'             => 'required|exists:suppliers,id',
            'order_date'              => 'required|date',
            'expected_delivery_date'  => 'nullable|date|after_or_equal:order_date',
            'notes'                   => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.quantity_ordered'=> 'required|integer|min:1',
            'items.*.unit_cost'       => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Create PO
            $po = PurchaseOrder::create([
                'supplier_id'            => $request->supplier_id,
                'created_by'             => auth()->id(),
                'order_date'             => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes'                  => $request->notes,
                'status'                 => 'draft',
            ]);

            // Create PO Items
            foreach ($request->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id'        => $item['product_id'],
                    'quantity_ordered'  => $item['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_cost'         => $item['unit_cost'],
                    'total_cost'        => $item['quantity_ordered'] * $item['unit_cost'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message'        => 'Purchase Order created successfully',
                'purchase_order' => new PurchaseOrderResource($po->load(['supplier', 'items.product']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create Purchase Order',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Update PO status
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        $request->validate([
            'status' => 'required|in:draft,ordered,cancelled',
            'notes'  => 'nullable|string',
        ]);

        // Can't update if already received
        if (in_array($purchaseOrder->status, ['received', 'cancelled'])) {
            return response()->json([
                'message' => 'Cannot update a received or cancelled Purchase Order'
            ], 422);
        }

        $purchaseOrder->update($request->only('status', 'notes'));

        return response()->json([
            'message'        => 'Purchase Order updated successfully',
            'purchase_order' => new PurchaseOrderResource($purchaseOrder)
        ], 200);
    }

    // Delete PO (only draft)
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft Purchase Orders can be deleted'
            ], 422);
        }

        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();

        return response()->json([
            'message' => 'Purchase Order deleted successfully'
        ], 200);
    }
}