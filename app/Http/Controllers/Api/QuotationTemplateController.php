<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuotationTemplateResource;
use App\Models\QuotationTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuotationTemplateController extends Controller
{
    public function index()
    {
        $templates = QuotationTemplate::with('items.product')
            ->orderBy('name')
            ->get();

        return response()->json([
            'templates' => QuotationTemplateResource::collection($templates),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.quantity'        => 'required|integer|min:1',
            'items.*.unit_price'      => 'required|numeric|min:0',
            'items.*.description'     => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $template = QuotationTemplate::create([
                'name'        => $request->name,
                'description' => $request->description,
                'created_by'  => auth()->id(),
            ]);

            foreach ($request->items as $item) {
                $template->items()->create([
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'description' => $item['description'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'message'  => 'Template created successfully',
                'template' => new QuotationTemplateResource($template->load('items.product')),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create template', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, QuotationTemplate $template)
    {
        $request->validate([
            'name'                => 'required|string|max:255',
            'description'         => 'nullable|string',
            'items'               => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.unit_price'  => 'required|numeric|min:0',
            'items.*.description' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        try {
            $template->update([
                'name'        => $request->name,
                'description' => $request->description,
            ]);

            $template->items()->delete();

            foreach ($request->items as $item) {
                $template->items()->create([
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'description' => $item['description'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'message'  => 'Template updated successfully',
                'template' => new QuotationTemplateResource($template->load('items.product')),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update template', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(QuotationTemplate $template)
    {
        $template->delete();

        return response()->json(['message' => 'Template deleted successfully']);
    }
}
