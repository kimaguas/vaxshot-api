<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AreaCodeResource;
use App\Models\AreaCode;
use App\Traits\LogsActivity;
use Illuminate\Http\Request;

class AreaCodeController extends Controller
{
    use LogsActivity;

    public function index(Request $request)
    {
        $query = AreaCode::withCount('customers');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('code', 'like', "%{$request->search}%")
                  ->orWhere('name', 'like', "%{$request->search}%");
            });
        }

        // Full list for dropdowns (no pagination)
        if ($request->list) {
            return response()->json([
                'area_codes' => AreaCodeResource::collection($query->orderBy('code')->get()),
            ], 200);
        }

        $areaCodes = $query->orderBy('code')->paginate(15);

        return response()->json([
            'area_codes' => AreaCodeResource::collection($areaCodes),
            'pagination' => [
                'total'        => $areaCodes->total(),
                'per_page'     => $areaCodes->perPage(),
                'current_page' => $areaCodes->currentPage(),
                'last_page'    => $areaCodes->lastPage(),
                'from'         => $areaCodes->firstItem(),
                'to'           => $areaCodes->lastItem(),
            ],
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:area_codes,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $areaCode = AreaCode::create($request->only('code', 'name', 'description'));

        $this->logActivity(
            action:      'CREATE',
            module:      'Area Codes',
            description: "Created area code: {$areaCode->code} - {$areaCode->name}",
            newData:     $areaCode->toArray()
        );

        return response()->json([
            'message'   => 'Area code created successfully',
            'area_code' => new AreaCodeResource($areaCode),
        ], 201);
    }

    public function update(Request $request, AreaCode $areaCode)
    {
        $request->validate([
            'code' => "required|string|max:50|unique:area_codes,code,{$areaCode->id}",
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $oldData = $areaCode->toArray();
        $areaCode->update($request->only('code', 'name', 'description'));

        $this->logActivity(
            action:      'UPDATE',
            module:      'Area Codes',
            description: "Updated area code: {$areaCode->code} - {$areaCode->name}",
            oldData:     $oldData,
            newData:     $areaCode->toArray()
        );

        return response()->json([
            'message'   => 'Area code updated successfully',
            'area_code' => new AreaCodeResource($areaCode->loadCount('customers')),
        ], 200);
    }

    public function destroy(AreaCode $areaCode)
    {
        $this->logActivity(
            action:      'DELETE',
            module:      'Area Codes',
            description: "Deleted area code: {$areaCode->code} - {$areaCode->name}",
            oldData:     $areaCode->toArray()
        );

        $areaCode->delete();

        return response()->json(['message' => 'Area code deleted successfully'], 200);
    }
}
