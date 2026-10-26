<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->orderByDesc('power')->orderBy('id')->get();
        return view('admin.roles.index', compact('roles'));
    }

    public function create()
    {
        return view('admin.roles.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                    => 'required|string|max:50|unique:roles,name|regex:/^[a-z0-9_]+$/',
            'label'                   => 'required|string|max:100',
            'icon'                    => 'nullable|string|max:60',
            'color'                   => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'is_admin'                => 'boolean',
            'max_reservations_day'    => 'required|integer|min:1|max:99',
            'max_reservations_week'   => 'required|integer|min:1|max:99',
            'cancel_deadline_minutes' => 'required|integer|min:0|max:1440',
            'advance_days'            => 'required|integer|min:1|max:365',
        ]);

        Role::create([
            'name'                    => $data['name'],
            'label'                   => $data['label'],
            'icon'                    => $data['icon'] ?: 'bi bi-star',
            'color'                   => $data['color'],
            'is_admin'                => $request->boolean('is_admin'),
            'locked'                  => false,
            'power'                   => 0,
            'max_reservations_day'    => $data['max_reservations_day'],
            'max_reservations_week'   => $data['max_reservations_week'],
            'cancel_deadline_minutes' => $data['cancel_deadline_minutes'],
            'advance_days'            => $data['advance_days'],
        ]);

        return redirect()->route('admin.roles.index')->with('success', "Rôle \"{$data['label']}\" créé.");
    }

    public function edit(Role $role)
    {
        return view('admin.roles.edit', compact('role'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'label'                   => 'required|string|max:100',
            'icon'                    => 'nullable|string|max:60',
            'color'                   => 'required|string|regex:/^#[0-9a-fA-F]{6}$/',
            'is_admin'                => 'boolean',
            'max_reservations_day'    => 'required|integer|min:1|max:99',
            'max_reservations_week'   => 'required|integer|min:1|max:99',
            'cancel_deadline_minutes' => 'required|integer|min:0|max:1440',
            'advance_days'            => 'required|integer|min:1|max:365',
        ]);

        $update = [
            'label'                   => $data['label'],
            'icon'                    => $data['icon'] ?: 'bi bi-star',
            'color'                   => $data['color'],
            'max_reservations_day'    => $data['max_reservations_day'],
            'max_reservations_week'   => $data['max_reservations_week'],
            'cancel_deadline_minutes' => $data['cancel_deadline_minutes'],
            'advance_days'            => $data['advance_days'],
        ];
        if (!$role->locked) {
            $update['is_admin'] = $request->boolean('is_admin');
        }
        $role->update($update);

        return redirect()->route('admin.roles.index')->with('success', "Rôle \"{$role->label}\" mis à jour.");
    }

    public function destroy(Role $role)
    {
        if ($role->locked) {
            return back()->with('error', 'Ce rôle système ne peut pas être supprimé.');
        }
        if ($role->users()->count() > 0) {
            return back()->with('error', 'Impossible de supprimer : des utilisateurs ont ce rôle.');
        }
        $role->delete();
        return redirect()->route('admin.roles.index')->with('success', "Rôle \"{$role->label}\" supprimé.");
    }

    public function reorder(Request $request)
    {
        $request->validate(['roles' => 'required|array']);
        foreach ($request->roles as $power => $id) {
            Role::where('id', $id)->update(['power' => $power]);
        }
        return response()->json(['message' => 'Ordre sauvegardé.']);
    }
}
