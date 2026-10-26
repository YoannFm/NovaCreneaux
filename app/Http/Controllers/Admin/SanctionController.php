<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Http\Request;

class SanctionController extends Controller
{
    public function index()
    {
        $sanctions = Sanction::with(['user', 'admin'])->latest()->paginate(30);
        return view('admin.sanctions.index', compact('sanctions'));
    }

    public function store(Request $request, User $user)
    {
        abort_unless(auth()->user()->canManage($user), 403);

        $data = $request->validate([
            'type'        => 'required|in:warning,ban',
            'reason'      => 'required|string|max:1000',
            'permanent'   => 'nullable|boolean',
            'banned_until'=> 'nullable|date|after:today',
        ]);

        $bannedUntil = null;
        if ($data['type'] === 'ban' && empty($data['permanent'])) {
            $request->validate(['banned_until' => 'required|date|after:today']);
            $bannedUntil = $data['banned_until'];
        }

        Sanction::create([
            'user_id'     => $user->id,
            'admin_id'    => auth()->id(),
            'type'        => $data['type'],
            'reason'      => $data['reason'],
            'banned_until'=> $bannedUntil,
        ]);

        return back()->with('success', 'Sanction appliquée à ' . $user->name . '.');
    }

    public function destroy(Sanction $sanction)
    {
        abort_unless(auth()->user()->canManage($sanction->user), 403);

        $sanction->delete();
        return back()->with('success', 'Sanction levée.');
    }
}
