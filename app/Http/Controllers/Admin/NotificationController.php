<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

/**
 * Notifications de la cloche envoyees par le personnel. Comme partout ailleurs, le personnel delegue ne voit, ne notifie
 * ni ne supprime les notifications des administrateurs et des autres membres du personnel (User::manageableBy).
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = UserNotification::with('user')
            ->whereHas('user', fn ($query) => $query->manageableBy($request->user()))
            ->latest()->paginate(25);
        return view('admin.notifications.index', ['notifications' => $notifications, 'pageTitle' => 'Notifications']);
    }

    public function create(Request $request)
    {
        $users = User::manageableBy($request->user())->orderBy('name')->get(['id', 'name', 'email']);
        $roles = Role::orderBy('power', 'desc')->get()->filter(fn (Role $role) => $request->user()->canAssign($role))->values();
        return view('admin.notifications.create', ['users' => $users, 'roles' => $roles, 'pageTitle' => 'Envoyer une notification']);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'     => 'required|string|max:255',
            'content'   => 'nullable|string|max:5000',
            'level'     => 'required|in:info,success,warning,danger',
            'target'    => 'required|in:all,users,roles',
            'user_ids'  => 'required_if:target,users|array',
            'user_ids.*'=> 'integer|exists:users,id',
            'role_ids'  => 'required_if:target,roles|array',
            'role_ids.*'=> 'integer|exists:roles,id',
        ]);

        // Destinataires : toujours restreints aux comptes que l'on gere
        $recipients = User::manageableBy($request->user());
        match ($data['target']) {
            'users' => $recipients->whereIn('id', $data['user_ids']),
            'roles' => $recipients->whereIn('role_id', $data['role_ids']),
            'all'   => null,
        };
        $recipients = $recipients->get();

        foreach ($recipients as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'title'   => $data['title'],
                'message' => $data['content'] ?? null,
                'type'    => $data['level'],
            ]);
        }

        $count = $recipients->count();

        ActivityLogger::log('Notification envoyée', "« {$data['title']} » : {$count} destinataire(s)");

        return redirect()->route('admin.notifications.index')
            ->with('success', "Notification envoyée à {$count} utilisateur(s).");
    }

    public function destroy(Request $request, UserNotification $notification)
    {
        abort_unless($notification->user === null || $request->user()->canManage($notification->user), 403);
        ActivityLogger::log('Notification supprimée', "« {$notification->title} »");
        $notification->delete();
        return back()->with('success', 'Notification supprimée.');
    }
}
