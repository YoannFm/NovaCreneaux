<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\UserExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Role;
use Illuminate\Validation\Rule;

class UserController extends Controller {

    public function index(Request $request) {
        $query = User::with('role');
        if ($search = $request->get('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name','like',"%{$search}%")
                  ->orWhere('email','like',"%{$search}%")
                  ->orWhere('classe','like',"%{$search}%");
            });
        }
        if ($roleId = $request->get('role')) {
            $query->where('role_id', $roleId);
        }
        return view('admin.users.index', [
            'users' => $query->orderBy('name')->paginate(30)->withQueryString(),
            'roles' => Role::orderBy('id')->get(),
        ]);
    }

    public function create() {
        return view('admin.users.create', [
            'roles' => Role::orderBy('id')->get(),
            // Le role propose par defaut est le moins privilegie, jamais le premier de la liste (administrateur)
            'defaultRoleId' => Role::defaultUser()?->id,
        ]);
    }

    public function store(Request $request) {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'classe'   => 'nullable|string|max:50',
            'role_id'  => 'required|exists:roles,id',
            'password' => 'nullable|string|min:8',
            'is_active'=> 'boolean',
        ]);

        $role = Role::findOrFail($data['role_id']);
        User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'email_verified_at' => now(),
            'classe'            => $data['classe'] ?? null,
            'role_id'           => $role->id,
            'is_admin'          => $role->is_admin,
            'is_active'         => $request->boolean('is_active', true),
            'password'          => Hash::make($data['password'] ?? Str::random(12)),
        ]);

        ActivityLogger::log('Utilisateur créé', "{$data['name']} ({$data['email']})");
        return redirect()->route('admin.users.index')->with('success', 'Utilisateur créé avec succès.');
    }

    public function edit(User $user) {
        $logs = \App\Models\ActivityLog::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(15);
        return view('admin.users.edit', [
            'user'  => $user,
            'roles' => Role::orderBy('id')->get(),
            'logs'  => $logs,
        ]);
    }

    public function update(Request $request, User $user) {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required','email', Rule::unique('users')->ignore($user->id)],
            'classe'   => 'nullable|string|max:50',
            'role_id'  => 'required|exists:roles,id',
            'password' => 'nullable|string|min:8',
            'is_active'=> 'boolean',
        ]);

        $role = Role::findOrFail($data['role_id']);
        $update = [
            'name'     => $data['name'],
            'email'    => $data['email'],
            'classe'   => $data['classe'] ?? null,
            'role_id'  => $role->id,
            'is_admin' => $role->is_admin,
            'is_active'=> $request->boolean('is_active', true),
        ];
        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }
        $user->update($update);

        ActivityLogger::log('Utilisateur modifié', "{$user->name} ({$user->email})");
        return redirect()->route('admin.users.index')->with('success', 'Utilisateur mis à jour.');
    }

    public function verifyEmail(User $user) {
        $user->markEmailAsVerified();
        ActivityLogger::log('Email vérifié (admin)', $user->name);
        return back()->with('success', "Email de {$user->name} marqué comme vérifié.");
    }

    public function mfaDisable(User $user) {
        $user->update([
            'two_factor_secret'       => null,
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

    public function toggle(User $user) {
        $user->update(['is_active' => !$user->is_active]);
        ActivityLogger::log($user->is_active ? 'Utilisateur activé' : 'Utilisateur désactivé', $user->name);
        return back()->with('success', 'Statut mis à jour.');
    }

    public function destroy(User $user) {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Vous ne pouvez pas supprimer votre propre compte.');
        }
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

    public function import(Request $request) {
        $request->validate(['csv' => 'required|file|mimes:csv,txt|max:2048']);
        $path = $request->file('csv')->getRealPath();
        $lines = array_filter(array_map('str_getcsv', file($path)));
        $created = $skipped = 0;
        $defaultRoleId = Role::defaultUser()?->id;
        foreach ($lines as $i => $row) {
            if ($i === 0 && strtolower(trim($row[0])) === 'nom') continue;
            [$nom,$prenom,$classe,$email] = array_pad(array_map('trim',$row),4,'');
            if (!$email || !filter_var($email,FILTER_VALIDATE_EMAIL)) { $skipped++; continue; }
            if (User::where('email',$email)->exists()) { $skipped++; continue; }
            User::create([
                'name'     => "{$prenom} {$nom}",
                'email'    => $email,
                'password' => Hash::make(Str::random(10)),
                'classe'   => $classe,
                'role_id'  => $defaultRoleId,
                'is_active'=> true,
            ]);
            $created++;
        }
        ActivityLogger::log('Import CSV utilisateurs', "{$created} créé(s), {$skipped} ignoré(s)");
        return redirect()->route('admin.users.index')
            ->with('success', "{$created} utilisateur(s) importé(s), {$skipped} ignoré(s).");
    }
}
