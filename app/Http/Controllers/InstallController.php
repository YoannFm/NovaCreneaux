<?php
namespace App\Http\Controllers;
use App\Models\Setting;
use App\Models\User;
use App\Services\InstallationState;
use App\Support\EnvFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InstallController extends Controller {
    public function __construct(private InstallationState $installation, private EnvFile $env) {}

    public function index() {
        if ($this->isInstalled()) return redirect('/');
        return view('install.welcome');
    }

    public function prerequisites() {
        if ($this->isInstalled()) return redirect('/');
        $checks = [
            ['name'=>'PHP 8.3+','passed'=>version_compare(PHP_VERSION,'8.3.0','>='),'detail'=>PHP_VERSION],
            ['name'=>'Extension PDO','passed'=>extension_loaded('pdo'),'detail'=>null],
            ['name'=>'Extension PDO MySQL','passed'=>extension_loaded('pdo_mysql'),'detail'=>null],
            ['name'=>'Extension mbstring','passed'=>extension_loaded('mbstring'),'detail'=>null],
            ['name'=>'Extension openssl','passed'=>extension_loaded('openssl'),'detail'=>null],
            ['name'=>'Dossier storage/ accessible en écriture','passed'=>is_writable(storage_path()),'detail'=>null],
            ['name'=>'Dossier bootstrap/ accessible en écriture','passed'=>is_writable(base_path('bootstrap')),'detail'=>null],
        ];
        $allPassed = collect($checks)->every(fn($c) => $c['passed']);
        return view('install.prerequisites', compact('checks','allPassed'));
    }

    public function dbConfig() {
        if ($this->isInstalled()) return redirect('/');
        return view('install.db-config');
    }

    public function storeDbConfig(Request $request) {
        if ($this->isInstalled()) return redirect('/');
        $data = $request->validate([
            'db_host'     => ['required', 'string', 'regex:/^[^\r\n]*$/'],
            'db_port'     => 'required|numeric',
            'db_database' => ['required', 'string', 'regex:/^[^\r\n]*$/'],
            'db_username' => ['required', 'string', 'regex:/^[^\r\n]*$/'],
            'db_password' => ['nullable', 'string', 'regex:/^[^\r\n]*$/'],
        ]);

        // Test the connection
        try {
            $pdo = new \PDO(
                "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_database']};charset=utf8mb4",
                $data['db_username'],
                $data['db_password'] ?? '',
                [\PDO::ATTR_TIMEOUT => 5]
            );
        } catch (\PDOException $e) {
            return back()->withInput()->withErrors(['error' => 'Connexion échouée : ' . $e->getMessage()]);
        }

        $this->env->set([
            'DB_HOST'     => $data['db_host'],
            'DB_PORT'     => $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => $data['db_password'] ?? '',
        ]);

        // Reload config
        \Illuminate\Support\Facades\Artisan::call('config:clear');

        return redirect()->route('install.database');
    }

    public function database() {
        if ($this->isInstalled()) return redirect('/');
        try {
            Artisan::call('migrate', ['--force'=>true]);
            $defaults = [
                'app_name'                 => 'NovaCreneaux',
                'registration_open'        => '0',
                'max_reservations_per_day' => '1',
                'maintenance_mode'         => '0',
                'maintenance_message'      => 'Le service est temporairement indisponible.',
            ];
            foreach ($defaults as $key => $value) {
                Setting::firstOrCreate(['key' => $key], ['value' => $value]);
            }

            // Nav items par défaut
            if (DB::table('nav_items')->count() === 0) {
                $now = now();
                DB::table('nav_items')->insert([
                    ['label' => 'Accueil',           'type' => 'home',            'url' => null, 'icon' => 'bi-house',          'target' => '_self', 'is_active' => true, 'admin_only' => false, 'position' => 0, 'created_at' => $now, 'updated_at' => $now],
                    ['label' => 'Réserver',          'type' => 'reservations',    'url' => null, 'icon' => 'bi-calendar-plus',  'target' => '_self', 'is_active' => true, 'admin_only' => false, 'position' => 1, 'created_at' => $now, 'updated_at' => $now],
                    ['label' => 'Mes réservations',  'type' => 'my_reservations', 'url' => null, 'icon' => 'bi-bookmark-check', 'target' => '_self', 'is_active' => true, 'admin_only' => false, 'position' => 2, 'created_at' => $now, 'updated_at' => $now],
                ]);
            }
        } catch (\Exception $e) {
            return back()->withErrors(['error'=>'Erreur migration : '.$e->getMessage()]);
        }
        return view('install.database');
    }

    public function siteConfig() {
        if ($this->isInstalled()) return redirect('/');
        return view('install.site-config');
    }

    public function storeSiteConfig(Request $request) {
        if ($this->isInstalled()) return redirect('/');
        $data = $request->validate([
            'app_name'                 => ['required', 'string', 'max:100', 'regex:/^[^\r\n]*$/'],
            'app_url'                  => 'required|url|max:255',
            'timezone'                 => 'required|timezone',
            'max_reservations_per_day' => 'required|integer|min:1|max:100',
            'registration_open'        => 'required|in:0,1',
        ]);

        $this->env->set(['APP_NAME' => $data['app_name'], 'APP_URL' => $data['app_url']]);

        // Update settings table
        $settings = [
            'app_name'                 => $data['app_name'],
            'app_url'                  => $data['app_url'],
            'timezone'                 => $data['timezone'],
            'max_reservations_per_day' => $data['max_reservations_per_day'],
            'registration_open'        => $data['registration_open'],
        ];
        foreach ($settings as $key => $value) {
            \App\Models\Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        \Illuminate\Support\Facades\Artisan::call('config:clear');

        return redirect()->route('install.admin');
    }

    public function createAdmin() {
        if ($this->isInstalled()) return redirect('/');
        return view('install.admin');
    }

    public function storeAdmin(Request $request) {
        // Un administrateur existe deja : l'installation est terminee, ou l'a ete a une etape pres. On n'en cree pas un second.
        if ($this->isInstalled() || User::where('is_admin', true)->exists()) return redirect('/');
        $data = $request->validate([
            'name'=>'required|string|max:255',
            'email'=>'required|email|max:255|unique:users',
            'password'=>'required|string|min:8|confirmed',
        ]);
        User::create([
            'name'=>$data['name'],'email'=>$data['email'],
            'password'=>Hash::make($data['password']),
            'is_admin'=>true,'is_active'=>true,
            'role_id'=>\App\Models\Role::where('name','admin')->value('id'),
        ]);

        // L'installation est verrouillee des la creation de l'administrateur : fermer le navigateur ici ne laisse pas /install ouvert
        return $this->finish();
    }

    /** Ancienne derniere etape : garde pour les liens existants, l'installation est deja verrouillee a l'etape precedente. */
    public function complete() {
        if ($this->isInstalled()) return redirect()->route('login');
        // Sans administrateur, verrouiller l'installation laisserait un site inutilisable
        if (!User::where('is_admin', true)->exists()) return redirect()->route('install.admin');
        return $this->finish();
    }

    private function finish() {
        try {
            $this->installation->markInstalled();
            $this->env->set(['APP_INSTALLED' => 'true']);
        } catch (\Exception $e) {
            return back()->withErrors(['error'=>'Erreur : '.$e->getMessage()]);
        }
        return redirect()->route('login')->with('success','Installation réussie ! Vous pouvez maintenant vous connecter.');
    }

    private function isInstalled(): bool {
        return $this->installation->isInstalled();
    }
}
