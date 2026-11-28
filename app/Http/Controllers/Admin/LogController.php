<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::orderByDesc('created_at');

        if ($search = $request->get('user')) {
            $query->where('user_name', 'like', "%{$search}%");
        }
        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($action = $request->get('action')) {
            $query->where('action', $action);
        }

        $logs     = $query->paginate(50)->withQueryString();
        $oldCount = ActivityLog::where('created_at', '<', now()->subDays(90))->count();
        $actions  = ActivityLog::distinct()->orderBy('action')->pluck('action');

        return view('admin.logs.index', compact('logs', 'oldCount', 'actions'));
    }

    public function purge()
    {
        $deleted = ActivityLog::where('created_at', '<', now()->subDays(90))->delete();
        return back()->with('success', "{$deleted} log(s) de plus de 90 jours supprimé(s).");
    }
}
