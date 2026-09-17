<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public static function log($action, $userId = null, $eventId = null)
    {
        if (!$userId && Auth::check()) {
            $userId = Auth::user()->id;
        }

        try {
            DB::table('audit_logs')->insert([
                'action' => $action,
                'performed_by' => $userId,
                'event_id' => $eventId,
                'ip_address' => Request::ip(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            // Audit logging must never block the action it's logging.
        }
    }
}
