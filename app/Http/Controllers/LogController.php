<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The admin-wide, paginated audit log viewer — every AuditLogService::log() entry across
 * the whole system, searchable and filterable by actor/date. Distinct from the per-event
 * log pane in the Event Console (EventConsoleController), which scopes to one event_id.
 */
class LogController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'Admin') {
            abort(403, 'Unauthorized access.');
        }

        $query = DB::table('audit_logs')
            ->leftJoin('users', 'audit_logs.performed_by', '=', 'users.id')
            ->leftJoin('events', 'audit_logs.event_id', '=', 'events.event_id')
            ->select(
                'audit_logs.id',
                'audit_logs.action',
                'audit_logs.ip_address',
                'audit_logs.created_at',
                'users.name as performed_by_name',
                'events.event_name'
            );

        $search = $request->get('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('audit_logs.action', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%");
            });
        }

        $dateFrom = $request->get('date_from');
        if ($dateFrom) {
            $query->whereDate('audit_logs.created_at', '>=', $dateFrom);
        }

        $dateTo = $request->get('date_to');
        if ($dateTo) {
            $query->whereDate('audit_logs.created_at', '<=', $dateTo);
        }

        $logs = $query->orderBy('audit_logs.created_at', 'desc')->paginate(25)->withQueryString();

        return view('admin.logs', compact('logs', 'search', 'dateFrom', 'dateTo'));
    }
}
