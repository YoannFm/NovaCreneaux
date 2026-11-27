<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountDeletionNotice;
use App\Services\ActivityLogger;
use App\Services\ReservationService;
use App\Services\UserCsvImport;
use App\Services\UserExporter;
use App\Support\ActivityChanges;
use App\Support\QueryFilters;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Role;
use Illuminate\Validation\Rule;

class UserController extends Controller {
    /** Lignes ignorees citees dans le message apres un import (toutes sont dans le journal d'activite). */
    private const SKIPPED_SHOWN = 5;


    public function index(Request $request) {
        // Filtres de l'adresse : un filtre invalide est ignore (QueryFilters), jamais d'erreur 500 ni de redirection
        $filters = QueryFilters::valid($request, ['search' => 'string|max:100', 'role' => 'integer']);
        $query = User::with('role')->withExists(['sanctions as is_suspended' => fn ($q) => $q->activeBan()]);
        if ($search = $filters['search'] ?? null) {
            $query->where(function($q) use ($search) {
                $q->where('name','like',"%{$search}%")
                  ->orWhere('email','like',"%{$search}%")
                  ->orWhere('classe','like',"%{$search}%");
            });
        }
        if ($roleId = $filters['role'] ?? null) {
            $query->where('role_id', $roleId);
        }
        return view('admin.users.index', [
            'users' => $query->orderBy('name')->paginate(30)->withQueryString(),
            'roles' => Role::orderBy('id')->get(),
            'filters' => $filters,
        ]);
    }

    public function create() {
        return view('admin.users.create', [
            'roles' => $this->assignableRoles(),
            // Le role propose par defaut est le moins privilegie, jamais le premier de la liste (administrateur)
            'defaultRoleId' => Role::defaultUser()?->id,
        ]);
    }

    public function store(Request $request) {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'classe'   => User::classeRules(),
            'role_id'  => 'required|exists:roles,id',
            'password' => ['nullable', 'string', Password::defaults()],
            'is_active'=> 'boolean',
        ]);

        $role = $this->assignableRole($data['role_id']);
        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'email_verified_at' => now(),
            'classe'            => $data['classe'] ?? null,
            'role_id'           => $role->id,
            'is_admin'          => $role->is_admin,
            'is_active'         => $request->boolean('is_active', true),
            'password'          => Hash::make($data['password'] ?? Str::random(12)),
        ]);

        ActivityLogger::log('Utilisateur créé', "{$data['name']} ({$data['email']})",
            array_map(fn ($value) => ['new' => $value], $this->activitySnapshot($user)));
        return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé avec succès.');
    }

    public function edit(User $user) {
        $this->authorizeManage($user);
        $logs = \App\Models\ActivityLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15);
        return view('admin.users.edit', [
            'user'  => $user,
            'roles' => $this->assignableRoles(),
            'logs'  => $logs,
        ]);
    }

    public function update(Request $request, User $user) {
        $this->authorizeManage($user);
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required','email', Rule::unique('users')->ignore($user->id)],
            'classe'   => User::classeRules(),
            'role_id'  => 'required|exists:roles,id',
            'password' => ['nullable', 'string', Password::defaults()],
        ]);

        $role = $this->assignableRole($data['role_id']);
        // Sans administrateur, plus personne n'accede aux reglages, aux roles ni aux mises a jour
        if (!$role->is_admin && $user->isLastActiveAdmin()) {
            return back()->withInput()->withErrors(['role_id' => "C'est le seul administrateur actif : il doit le rester. Donnez d'abord ce rôle à un autre compte."]);
        }
        $update = [
            'name'     => $data['name'],
            'email'    => $data['email'],
            'classe'   => $data['classe'] ?? null,
            'role_id'  => $role->id,
            'is_admin' => $role->is_admin,
        ];
        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }
        $before = $this->activitySnapshot($user);
        $user->update($update);
        $changes = ActivityChanges::diff($before, $this->activitySnapshot($user->refresh()));
        if (!empty($data['password'])) {
            $changes['password'] = ['old' => ActivityChanges::MASKED, 'new' => ActivityChanges::MASKED];
        }

        ActivityLogger::log('Utilisateur modifié', "{$user->name} ({$user->email})", $changes);
        return redirect()->route('admin.users.index')->with('success', 'Utilisateur mis à jour.');
    }

    /** Champs d'un compte montres dans le detail du journal (jamais le mot de passe). */
    private function activitySnapshot(User $user): array {
        return [
            'name' => $user->name, 'email' => $user->email, 'classe' => $user->classe,
            'role' => $user->role?->label, 'is_active' => (bool) $user->is_active,
        ];
    }

    public function verifyEmail(User $user) {
        $this->authorizeManage($user);
        $user->markEmailAsVerified();
        ActivityLogger::log('Email vérifié (admin)', $user->name);
        return back()->with('success', "Email de {$user->name} marqué comme vérifié.");
    }

    public function mfaDisable(User $user) {
        $this->authorizeManage($user);
        $user->update([
            'two_factor_secret'       => null,
            'two_factor_last_step'    => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'mfa_email_enabled'       => false,
            'mfa_sms_enabled'         => false,
            'mfa_phone'               => null,
            'mfa_webauthn_credentials' => null,
        ]);
        ActivityLogger::log('MFA désactivée (admin)', $user->name);
        return back()->with('success', "MFA de {$user->name} désactivée.");
    }

    public function toggle(ReservationService $reservations, User $user) {
        $this->authorizeManage($user);
        // On s'enfermerait dehors (comme les actions groupees, qui ignorent son propre compte)
        if ($user->is_active && $user->is(auth()->user())) {
            return back()->with('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        }
        $user->update(['is_active' => !$user->is_active]);
        ActivityLogger::log($user->is_active ? 'Utilisateur activé' : 'Utilisateur désactivé', $user->name);
        // Un compte desactive ne viendra plus : ses places a venir sont liberees
        $cancelled = $user->is_active ? 0 : $reservations->cancelUpcomingForUsers([$user->id], ReservationService::DEACTIVATION_REASON);
        return back()->with('success', 'Statut mis à jour.' . ReservationService::cancelledNote($cancelled));
    }

    public function destroy(AccountDeletionNotice $notice, User $user) {
        $this->authorizeManage($user);
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }
        $notice->send($user, true);
        ActivityLogger::log('Utilisateur supprimé', "{$user->name} ({$user->email})");
        $user->delete();
        // Pas de back() : depuis la page de modification, on reviendrait sur l'adresse de l'utilisateur supprime (404)
        return redirect()->route('admin.users.index')->with('success', 'Utilisateur supprimé.');
    }

    public function exportZip(UserExporter $exporter) {
        $users = User::with('role')->withCount('reservations')->orderBy('name')->get();
        $csv = $exporter->csv($users);

        $zipPath = storage_path('app/exports/utilisateurs-' . now()->format('Ymd-His') . '.zip');
        $dir = dirname($zipPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('utilisateurs.csv', $csv);
        $zip->close();

        ActivityLogger::log('Export ZIP utilisateurs', $users->count() . ' utilisateur(s)');

        return response()->download($zipPath, 'utilisateurs-' . now()->format('Ymd') . '.zip')
            ->deleteFileAfterSend();
    }

    public function importForm() { return view('admin.users.import'); }

    public function import(Request $request, UserCsvImport $importer) {
        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:2048']);
        $result = $importer->import($request->file('csv')->getRealPath());
        $skipped = $result['skipped'];
        ActivityLogger::log('Import CSV utilisateurs', "{$result['created']} créé(s), " . count($skipped) . ' ignoré(s)', $skipped ? ['lignes ignorées' => ['new' => $skipped]] : []);

        $message = "{$result['created']} utilisateur(s) importé(s), " . count($skipped) . ' ignoré(s).';
        if ($skipped) {
            $shown = array_slice($skipped, 0, self::SKIPPED_SHOWN);
            $more = count($skipped) - count($shown);
            $message .= ' ' . ucfirst(implode(' ; ', $shown)) . ($more > 0 ? " ; et {$more} autre(s), voir le journal d'activité." : '.');
        }

        return redirect()->route('admin.users.index')->with('success', $message);
    }

    /** Le personnel delegue ne touche ni aux administrateurs ni aux autres membres du personnel. */
    private function authorizeManage(User $user): void {
        abort_unless(auth()->user()->canManage($user), 403);
    }

    private function assignableRoles() {
        return Role::orderBy('id')->get()->filter(fn (Role $role) => auth()->user()->canAssign($role))->values();
    }

    private function assignableRole(int|string $id): Role {
        $role = Role::findOrFail($id);
        abort_unless(auth()->user()->canAssign($role), 403);
        return $role;
    }
}
