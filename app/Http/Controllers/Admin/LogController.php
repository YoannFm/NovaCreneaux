<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\ActivityLogger;
use App\Support\QueryFilters;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        // Filtres de l'adresse : un filtre invalide est ignore (QueryFilters), jamais d'erreur 500 ni de redirection
        $filters = QueryFilters::valid($request, [
            'user'   => 'string|max:100',
            'from'   => 'date_format:Y-m-d',
            'to'     => 'date_format:Y-m-d',
            'action' => 'string|max:150',
        ]);
        $query = ActivityLog::orderByDesc('created_at');

        if ($search = $filters['user'] ?? null) {
            $query->where('user_name', 'like', "%{$search}%");
        }
        if ($from = $filters['from'] ?? null) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $filters['to'] ?? null) {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($action = $filters['action'] ?? null) {
            $query->where('action', $action);
        }

        $logs     = $query->paginate(50)->withQueryString();
        $oldCount = ActivityLog::where('created_at', '<', now()->subDays(90))->count();
        $actions  = ActivityLog::distinct()->orderBy('action')->pluck('action');

        return view('admin.logs.index', compact('logs', 'oldCount', 'actions', 'filters'));
    }

    public function purge()
    {
        $deleted = ActivityLog::where('created_at', '<', now()->subDays(90))->delete();
        // Inscrit apres la purge : cette entree, recente, reste
        ActivityLogger::log('Journal purgé', "{$deleted} entrée(s) de plus de 90 jours supprimée(s)");
        return back()->with('success', "{$deleted} log(s) de plus de 90 jours supprimé(s).");
    }
}
