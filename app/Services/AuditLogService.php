<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public static function log($action, $userId = null)
    {
        if (!$userId && Auth::check()) {
            $userId = Auth::user()->id;
        }

        DB::table('audit_logs')->insert([
            'action' => $action,
            'performed_by' => $userId,
            'ip_address' => Request::ip(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
