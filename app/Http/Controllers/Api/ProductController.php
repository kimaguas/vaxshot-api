<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Imports\ProductImport;
use App\Models\Product;
use App\Models\ProductTier;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $query = Product::with(['supplier', 'tiers']);

        if ($request->supplier_id) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('brand_name', 'like', "%{$request->search}%")
                  ->orWhere('generic_name', 'like', "%{$request->search}%");
            });
        }

        $perPage  = min((int) ($request->per_page ?? 15), 500);
        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'products'   => ProductResource::collection($products),
            'pagination' => [
                'total'        => $products->total(),
                'per_page'     => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'from'         => $products->firstItem(),
                'to'           => $products->lastItem(),
            ],
        ], 200);
    }

    public function store(StoreProductRequest $request)
    {
        DB::beginTransaction();

        try {
            $product = Product::create([
                'supplier_id'      => $request->supplier_id,
                'brand_name'       => $request->brand_name,
                'lot_no'           => $request->lot_no,
                'generic_name'     => $request->generic_name,
                'acquisition_cost' => $request->acquisition_cost ?: null,
                'indication'       => $request->indication,
                'expiry_date'      => $request->expiry_date,
                'effective_date'   => $request->effective_date,
                'notes'            => $request->notes,
                'status'           => $request->status ?? 'active',
            ]);

            foreach ($request->tiers as $index => $tier) {
                $minQty = $tier['min_qty'] ?? 1;
                $maxQty = isset($tier['max_qty']) && $tier['max_qty'] !== '' ? (int) $tier['max_qty'] : null;
                $label  = $tier['tier_label'] ?? ($maxQty ? "{$minQty}-{$maxQty}vls" : "{$minQty}vls & up");
                ProductTier::create([
                    'catalog_id'  => $product->id,
                    'tier_label'  => $label,
                    'min_qty'     => $minQty,
                    'max_qty'     => $maxQty,
                    'price'       => $tier['price'],
                    'sort_order'  => $tier['sort_order'] ?? $index,
                ]);
            }

            DB::commit();

            $this->logActivity(
                action      : 'CREATE',
                module      : 'Products',
                description : "Created product: {$product->brand_name} for supplier #{$product->supplier_id}",
                newData     : $product->toArray()
            );

            return response()->json([
                'message' => 'Product created successfully',
                'product' => new ProductResource($product->load(['supplier', 'tiers'])),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create product'], 500);
        }
    }

    public function update(StoreProductRequest $request, Product $product)
    {
        DB::beginTransaction();

        try {
            $oldData = $product->toArray();

            $product->update([
                'supplier_id'      => $request->supplier_id,
                'brand_name'       => $request->brand_name,
                'lot_no'           => $request->lot_no,
                'generic_name'     => $request->generic_name,
                'acquisition_cost' => $request->acquisition_cost ?: null,
                'indication'       => $request->indication,
                'expiry_date'      => $request->expiry_date,
                'effective_date'   => $request->effective_date,
                'notes'            => $request->notes,
                'status'           => $request->status ?? $product->status,
            ]);

            $product->tiers()->delete();

            foreach ($request->tiers as $index => $tier) {
                $minQty = $tier['min_qty'] ?? 1;
                $maxQty = isset($tier['max_qty']) && $tier['max_qty'] !== '' ? (int) $tier['max_qty'] : null;
                $label  = $tier['tier_label'] ?? ($maxQty ? "{$minQty}-{$maxQty}vls" : "{$minQty}vls & up");
                ProductTier::create([
                    'catalog_id'  => $product->id,
                    'tier_label'  => $label,
                    'min_qty'     => $minQty,
                    'max_qty'     => $maxQty,
                    'price'       => $tier['price'],
                    'sort_order'  => $tier['sort_order'] ?? $index,
                ]);
            }

            DB::commit();

            $this->logActivity(
                action      : 'UPDATE',
                module      : 'Products',
                description : "Updated product: {$product->brand_name}",
                oldData     : $oldData,
                newData     : $product->fresh()->toArray()
            );

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => new ProductResource($product->load(['supplier', 'tiers'])),
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update product'], 500);
        }
    }

    public function destroy(Product $product)
    {
        $this->logActivity(
            action      : 'DELETE',
            module      : 'Products',
            description : "Deleted product: {$product->brand_name}",
            oldData     : $product->toArray()
        );

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully'], 200);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|mimes:xlsx,xls,csv',
            'supplier_id' => 'required|exists:suppliers,id',
        ]);

        try {
            $import = new ProductImport($request->supplier_id);
            Excel::import($import, $request->file('file'));

            $count = $import->getImportedCount();

            $this->logActivity(
                action      : 'IMPORT',
                module      : 'Products',
                description : "Imported {$count} products for supplier #{$request->supplier_id}",
            );

            return response()->json([
                'message' => "{$count} products imported successfully",
                'count'   => $count,
                'errors'  => $import->getErrors(),
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Import failed: ' . $e->getMessage()], 422);
        }
    }
}
