<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\Permission;
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
        $data = $request->validate(['name' => Role::NAME_RULE . '|unique:roles,name'] + $this->rules());

        Role::create([
            'name'                    => $data['name'],
            'label'                   => $data['label'],
            'icon'                    => ($data['icon'] ?? null) ?: Role::DEFAULT_ICON,
            'color'                   => $data['color'],
            'is_admin'                => $request->boolean('is_admin'),
            'permissions'             => $this->permissions($request),
            'api_access'              => $request->boolean('api_access', true),
            'locked'                  => false,
            'power'                   => 0,
            // Limites de reservation : celles du role par defaut, a ajuster dans Parametres > Reservations
            ...Role::defaultLimits(),
        ]);

        return redirect()->route('admin.roles.index')->with('success', "Rôle \"{$data['label']}\" créé.");
    }

    public function edit(Role $role)
    {
        return view('admin.roles.edit', compact('role'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate($this->rules());

        $update = [
            'label'                   => $data['label'],
            'icon'                    => ($data['icon'] ?? null) ?: Role::DEFAULT_ICON,
            'color'                   => $data['color'],
        ];
        if ($request->has('api_access')) {
            $update['api_access'] = $request->boolean('api_access');
        }
        if (!$role->locked) {
            $update['is_admin'] = $request->boolean('is_admin');
        }
        // Retirer le statut d'administrateur a ce role ne doit pas laisser le site sans administrateur actif
        // (un administrateur desactive ne compte pas : personne ne pourrait plus entrer dans l'administration)
        if ($role->is_admin && !($update['is_admin'] ?? true) && !$this->otherActiveAdminExists($role)) {
            return back()->withInput()->with('error', "Aucun autre compte actif n'est administrateur : ce rôle doit le rester.");
        }
        $update['permissions'] = $this->permissions($request, $update['is_admin'] ?? $role->is_admin);
        $role->update($update);
        if ($role->wasChanged('api_access')) {
            ActivityLogger::log("Accès à l'API modifié", "Rôle {$role->label} : " . ($role->api_access ? 'autorisé' : 'refusé'));
        }

        return redirect()->route('admin.roles.index')->with('success', "Rôle \"{$role->label}\" mis à jour.");
    }

    /** Un compte actif reste administrateur hors de ce role (users.is_admin est la copie du statut du role). */
    private function otherActiveAdminExists(Role $role): bool
    {
        return User::where('is_admin', true)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('role_id')->orWhere('role_id', '!=', $role->id))
            ->exists();
    }

    /** Le formulaire refuse une permission inconnue (l'import, lui, l'ecarte sans erreur). */
    private function rules(): array
    {
        return ['is_admin' => 'boolean', 'permissions' => 'nullable|array', 'permissions.*' => 'string|in:' . implode(',', array_keys(Permission::all()))] + Role::rules();
    }

    /** Un role administrateur a deja tous les droits : ses permissions detaillees ne servent a rien. */
    private function permissions(Request $request, ?bool $isAdmin = null): ?array
    {
        if ($isAdmin ?? $request->boolean('is_admin')) {
            return null;
        }

        return Permission::only($request->input('permissions', []));
    }

    public function destroy(Role $role)
    {
        if ($role->locked) {
            return back()->with('error', 'Ce rôle système ne peut pas être supprimé.');
        }
        if ($role->users()->count() > 0) {
            return back()->with('error', 'Impossible de supprimer : des utilisateurs ont ce rôle.');
        }
        // Une page restreinte sans role est ouverte a tout compte connecte : supprimer son seul role l'ouvrirait a tous
        $pages = $role->pages()->pluck('title');
        if ($pages->isNotEmpty()) {
            return back()->with('error', 'Impossible de supprimer : ce rôle donne accès à des pages restreintes (' . $pages->implode(', ') . '). Retirez-le d\'abord de ces pages.');
        }
        $role->delete();
        return redirect()->route('admin.roles.index')->with('success', "Rôle \"{$role->label}\" supprimé.");
    }

    public function reorder(Request $request)
    {
        // Identifiants entiers : MySQL 8 (mode strict) repond par une erreur 500 a "where id = 'x'"
        $data = $request->validate(['roles' => 'required|array', 'roles.*' => 'integer']);
        foreach ($data['roles'] as $power => $id) {
            Role::where('id', $id)->update(['power' => $power]);
        }
        return response()->json(['message' => 'Ordre sauvegardé.']);
    }
}
