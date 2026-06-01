<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::query();

        // Filter by module
        if ($request->module) {
            $query->where('module', $request->module);
        }

        // Filter by action
        if ($request->action) {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->user_name) {
            $query->where('user_name', 'like', "%{$request->user_name}%");
        }

        // Filter by date range
        if ($request->from && $request->to) {
            $query->whereBetween('created_at', [
                $request->from . ' 00:00:00',
                $request->to   . ' 23:59:59'
            ]);
        }

        // Search
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('description', 'like', "%{$request->search}%")
                  ->orWhere('user_name', 'like', "%{$request->search}%")
                  ->orWhere('module', 'like', "%{$request->search}%");
            });
        }

        $logs = $query->latest()->paginate(10);

        return response()->json([
            'logs'       => ActivityLogResource::collection($logs),
            'pagination' => [
                'total'        => $logs->total(),
                'per_page'     => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'from'         => $logs->firstItem(),
                'to'           => $logs->lastItem(),
            ]
        ], 200);
    }
}