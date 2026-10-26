<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = UserNotification::with('user')->latest()->paginate(25);
        return view('admin.notifications.index', ['notifications' => $notifications, 'pageTitle' => 'Notifications']);
    }

    public function create()
    {
        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $roles = \App\Models\Role::orderBy('power', 'desc')->get();
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
            'user_ids.*'=> 'exists:users,id',
            'role_ids'  => 'required_if:target,roles|array',
            'role_ids.*'=> 'exists:roles,id',
        ]);

        $recipients = collect();

        if ($data['target'] === 'all') {
            $recipients = User::all();
        } elseif ($data['target'] === 'users') {
            $recipients = User::whereIn('id', $data['user_ids'])->get();
        } elseif ($data['target'] === 'roles') {
            $recipients = User::whereIn('role_id', $data['role_ids'])->get();
        }

        foreach ($recipients as $user) {
            UserNotification::create([
                'user_id' => $user->id,
                'title'   => $data['title'],
                'message' => $data['content'] ?? null,
                'type'    => $data['level'],
            ]);
        }

        $count = $recipients->count();

        LogHelper::log('sent_system_notification', 'notification', null, ['target' => $data['target'], 'count' => $count]);

        return redirect()->route('admin.notifications.index')
            ->with('success', "Notification envoyee a {$count} utilisateur(s).");
    }

    public function destroy(UserNotification $notification)
    {
        LogHelper::log('deleted_system_notification', 'notification', $notification->id, [], 'warning');
        $notification->delete();
        return back()->with('success', 'Notification supprimee.');
    }
}
