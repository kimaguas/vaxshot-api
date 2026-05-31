<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    // Get all products
    public function index(Request $request)
    {
        $query = Product::with('supplier');

        // Filter by status
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Filter low stock
        if ($request->low_stock) {
            $query->lowStock();
        }

        // Search by brand name or product code
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('brand_name', 'like', "%{$request->search}%")
                  ->orWhere('product_code', 'like', "%{$request->search}%");
            });
        }

        $products = $query->latest()->paginate(10);

        return response()->json([
            'products' => ProductResource::collection($products),
            'pagination' => [
                'total'        => $products->total(),
                'per_page'     => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'from'         => $products->firstItem(),
                'to'           => $products->lastItem(),
            ]
        ], 200);



    }

    // Get single product
    public function show(Product $product)
    {
        return response()->json([
            'product' => new ProductResource($product->load('supplier'))
        ], 200);
    }

    // Create product
    public function store(Request $request)
    {
        $request->validate([
            'product_code'     => 'required|string|unique:products,product_code',
            'brand_name'       => 'required|string|max:255',
            'supplier_id'      => 'nullable|exists:suppliers,id',
            'description'      => 'nullable|string',
            'acquisition_cost' => 'required|numeric|min:0',
            'selling_price'    => 'required|numeric|min:0',
            'stock'            => 'required|integer|min:0',
            'maintaining_stock'=> 'required|integer|min:0',
            'status'           => 'in:active,inactive',
        ]);

        $product = Product::create($request->all());

        return response()->json([
            'message' => 'Product created successfully',
            'product' => new ProductResource($product)
        ], 201);
    }

    // Update product
    public function update(Request $request, Product $product)
    {
        $request->validate([
            'product_code'     => 'sometimes|string|unique:products,product_code,' . $product->id,
            'brand_name'       => 'sometimes|string|max:255',
            'supplier_id'      => 'nullable|exists:suppliers,id',
            'description'      => 'nullable|string',
            'acquisition_cost' => 'sometimes|numeric|min:0',
            'selling_price'    => 'sometimes|numeric|min:0',
            'stock'            => 'sometimes|integer|min:0',
            'maintaining_stock'=> 'sometimes|integer|min:0',
            'status'           => 'sometimes|in:active,inactive',
        ]);

        $product->update($request->all());

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => new ProductResource($product)
        ], 200);
    }

    // Delete product
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully'
        ], 200);
    }
}