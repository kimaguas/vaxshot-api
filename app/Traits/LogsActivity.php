<?php

namespace App\Traits;

use App\Models\ActivityLog;

trait LogsActivity
{
    protected function logActivity(
        string $action,
        string $module,
        string $description,
        $oldData = null,
        $newData = null
    ) {
        $user = auth()->user()  ;

        ActivityLog::create([
            'user_id'     => $user?->id,
            'user_name'   => $user?->name,
            'action'      => $action,
            'module'      => $module,
            'description' => $description,
            'old_data'    => $oldData,
            'new_data'    => $newData,
            'ip_address'  => request()->ip(),
        ]);
    }
}