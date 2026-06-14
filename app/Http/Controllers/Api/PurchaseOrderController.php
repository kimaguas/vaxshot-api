<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    use LogsActivity;

    // Get all purchase orders
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'createdBy', 'items.product']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->from && $request->to) {
            $query->whereBetween('order_date', [$request->from, $request->to]);
        }

        if ($request->search) {
            $query->where('po_number', 'like', "%{$request->search}%");
        }

        $orders = $query->latest()->paginate(10);

        return response()->json([
            'purchase_orders' => PurchaseOrderResource::collection($orders),
            'pagination'      => [
                'total'        => $orders->total(),
                'per_page'     => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'from'         => $orders->firstItem(),
                'to'           => $orders->lastItem(),
            ]
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
            'supplier_id'              => 'required|exists:suppliers,id',
            'order_date'               => 'required|date',
            'expected_delivery_date'   => 'nullable|date|after_or_equal:order_date',
            'notes'                    => 'nullable|string',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_cost'        => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $po = PurchaseOrder::create([
                'supplier_id'            => $request->supplier_id,
                'created_by'             => auth()->id(),
                'order_date'             => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes'                  => $request->notes,
                'status'                 => 'draft',
            ]);

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

            $po->load('supplier');

            $this->logActivity(
                action      : 'CREATE',
                module      : 'Purchase Orders',
                description : "Created PO: {$po->po_number} from {$po->supplier->company} - ₱{$po->total_amount}",
                newData     : $po->toArray()
            );

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
        if (in_array($purchaseOrder->status, ['received', 'cancelled'])) {
            return response()->json([
                'message' => 'Cannot update a received or cancelled Purchase Order'
            ], 422);
        }

        // Status-only update (confirm flow)
        if ($request->has('status') && !$request->has('items')) {
            $request->validate([
                'status' => 'required|in:draft,ordered,cancelled',
                'notes'  => 'nullable|string',
            ]);

            $oldStatus = $purchaseOrder->status;
            $purchaseOrder->update($request->only('status', 'notes'));

            $this->logActivity(
                action      : 'UPDATE',
                module      : 'Purchase Orders',
                description : "Updated PO: {$purchaseOrder->po_number} status from {$oldStatus} to {$purchaseOrder->status}",
            );

            return response()->json([
                'message'        => 'Purchase Order updated successfully',
                'purchase_order' => new PurchaseOrderResource($purchaseOrder->load(['supplier', 'items.product']))
            ], 200);
        }

        // Full edit (draft only)
        if ($purchaseOrder->status !== 'draft') {
            return response()->json(['message' => 'Only draft Purchase Orders can be edited'], 422);
        }

        $request->validate([
            'expected_delivery_date'   => 'nullable|date',
            'notes'                    => 'nullable|string',
            'items'                    => 'required|array|min:1',
            'items.*.id'               => 'required|exists:purchase_order_items,id',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_cost'        => 'required|numeric|min:0',
        ]);

        $purchaseOrder->update($request->only('expected_delivery_date', 'notes'));

        $totalCost = 0;
        foreach ($request->items as $itemData) {
            $item = $purchaseOrder->items()->find($itemData['id']);
            if ($item) {
                $total = $itemData['quantity_ordered'] * $itemData['unit_cost'];
                $item->update([
                    'quantity_ordered' => $itemData['quantity_ordered'],
                    'unit_cost'        => $itemData['unit_cost'],
                    'total_cost'       => $total,
                ]);
                $totalCost += $total;
            }
        }

        $purchaseOrder->update(['total_amount' => $totalCost]);

        $this->logActivity(
            action      : 'UPDATE',
            module      : 'Purchase Orders',
            description : "Edited draft PO: {$purchaseOrder->po_number}",
        );

        return response()->json([
            'message'        => 'Purchase Order updated successfully',
            'purchase_order' => new PurchaseOrderResource($purchaseOrder->load(['supplier', 'items.product']))
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

        $this->logActivity(
            action      : 'DELETE',
            module      : 'Purchase Orders',
            description : "Deleted PO: {$purchaseOrder->po_number}",
            oldData     : $purchaseOrder->toArray()
        );

        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();

        return response()->json([
            'message' => 'Purchase Order deleted successfully'
        ], 200);
    }
}