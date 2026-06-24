<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use LogsActivity;

    // Get all suppliers
    public function index(Request $request)
    {
        $query = Supplier::withCount('products');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('company', 'like', "%{$request->search}%")
                  ->orWhere('contact_person', 'like', "%{$request->search}%")
                  ->orWhere('tin_no', 'like', "%{$request->search}%");
            });
        }

        $suppliers = $query->orderBy('company', 'asc')->paginate(10);

        return response()->json([
            'suppliers'  => SupplierResource::collection($suppliers),
            'pagination' => [
                'total'        => $suppliers->total(),
                'per_page'     => $suppliers->perPage(),
                'current_page' => $suppliers->currentPage(),
                'last_page'    => $suppliers->lastPage(),
                'from'         => $suppliers->firstItem(),
                'to'           => $suppliers->lastItem(),
            ]
        ], 200);
    }

    // Get single supplier
    public function show(Supplier $supplier)
    {
        return response()->json([
            'supplier' => new SupplierResource($supplier->load('products'))
        ], 200);
    }

    // Create supplier
    public function store(StoreSupplierRequest $request)
    {
        $supplier = Supplier::create($request->validated());

        $this->logActivity(
            action      : 'CREATE',
            module      : 'Suppliers',
            description : "Created supplier: {$supplier->company}",
            newData     : $supplier->toArray()
        );

        return response()->json([
            'message'  => 'Supplier created successfully',
            'supplier' => new SupplierResource($supplier)
        ], 201);
    }

    // Update supplier
    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $oldData = $supplier->toArray();
        $supplier->update($request->validated());

        $this->logActivity(
            action      : 'UPDATE',
            module      : 'Suppliers',
            description : "Updated supplier: {$supplier->company}",
            oldData     : $oldData,
            newData     : $supplier->fresh()->toArray()
        );

        return response()->json([
            'message'  => 'Supplier updated successfully',
            'supplier' => new SupplierResource($supplier)
        ], 200);
    }

    // Delete supplier
    public function destroy(Supplier $supplier)
    {
        $this->logActivity(
            action      : 'DELETE',
            module      : 'Suppliers',
            description : "Deleted supplier: {$supplier->company}",
            oldData     : $supplier->toArray()
        );

        $supplier->delete();

        return response()->json([
            'message' => 'Supplier deleted successfully'
        ], 200);
    }
}