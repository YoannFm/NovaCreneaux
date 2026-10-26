<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SanctionController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\RoleController;
use Illuminate\Support\Facades\Route;

// Install wizard (no auth required)
Route::prefix('install')->name('install.')->group(function () {
    Route::get('/', [App\Http\Controllers\InstallController::class, 'index'])->name('index');
    Route::get('/prerequisites', [App\Http\Controllers\InstallController::class, 'prerequisites'])->name('prerequisites');
    Route::get('/db-config', [App\Http\Controllers\InstallController::class, 'dbConfig'])->name('db-config');
    Route::post('/db-config', [App\Http\Controllers\InstallController::class, 'storeDbConfig'])->name('db-config.store');
    Route::get('/database', [App\Http\Controllers\InstallController::class, 'database'])->name('database');
    Route::get('/site-config', [App\Http\Controllers\InstallController::class, 'siteConfig'])->name('site-config');
    Route::post('/site-config', [App\Http\Controllers\InstallController::class, 'storeSiteConfig'])->name('site-config.store');
    Route::get('/admin', [App\Http\Controllers\InstallController::class, 'createAdmin'])->name('admin');
    Route::post('/admin', [App\Http\Controllers\InstallController::class, 'storeAdmin'])->name('admin.store');
    Route::get('/complete', [App\Http\Controllers\InstallController::class, 'complete'])->name('complete');
});

// Documentation de l'API (Swagger UI, fichier public/docs/openapi.yaml)
Route::view('/api/docs', 'api.docs')->name('api.docs');

// Pages publiques
Route::get('/p/{slug}', function (string $slug) {
    $page = \App\Models\Page::where('slug', $slug)->where('is_enabled', true)->firstOrFail();
    if ($page->is_restricted) {
        if (!auth()->check()) return redirect()->route('login');
        $userRoleId = auth()->user()->role_id;
        if ($page->roles->isNotEmpty() && !$page->roles->contains('id', $userRoleId)) {
            abort(403);
        }
    }
    return view('pages.show', compact('page'));
})->name('pages.show');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $upcoming = auth()->user()->reservations()
            ->with('slot.resource')
            ->whereDate('reservation_date', '>=', today())
            ->orderBy('reservation_date')
            ->limit(5)
            ->get();
        return view('dashboard', compact('upcoming'));
    })->name('dashboard');

    Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/reserver', [\App\Http\Controllers\ReservationController::class, 'index'])->name('reservations.index');
    Route::post('/reservations', [\App\Http\Controllers\ReservationController::class, 'store'])->name('reservations.store');
    Route::delete('/reservations/{id}', [\App\Http\Controllers\ReservationController::class, 'destroy'])->name('reservations.destroy');
    Route::get('/mes-reservations', [\App\Http\Controllers\ReservationController::class, 'my'])->name('reservations.my');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // MFA — TOTP
    Route::get('/profile/mfa/totp/enable', [\App\Http\Controllers\MfaController::class, 'totpEnableForm'])->name('mfa.totp.enable.form');
    Route::post('/profile/mfa/totp/enable', [\App\Http\Controllers\MfaController::class, 'totpEnable'])->name('mfa.totp.enable');
    Route::delete('/profile/mfa/totp', [\App\Http\Controllers\MfaController::class, 'totpDisable'])->name('mfa.totp.disable');
    Route::get('/profile/mfa/recovery-codes', [\App\Http\Controllers\MfaController::class, 'recoveryCodesShow'])->name('mfa.recovery-codes');
    Route::post('/profile/mfa/recovery-codes/confirm', [\App\Http\Controllers\MfaController::class, 'recoveryCodesConfirm'])->name('mfa.recovery-codes.confirm');
    Route::post('/profile/mfa/recovery-codes/regenerate', [\App\Http\Controllers\MfaController::class, 'recoveryCodesRegenerate'])->name('mfa.recovery-codes.regenerate');

    // MFA — Email OTP
    Route::post('/profile/mfa/email/enable', [\App\Http\Controllers\MfaController::class, 'emailEnable'])->name('mfa.email.enable');
    Route::get('/profile/mfa/email/confirm', [\App\Http\Controllers\MfaController::class, 'emailConfirmForm'])->name('mfa.email.confirm');
    Route::post('/profile/mfa/email/confirm', [\App\Http\Controllers\MfaController::class, 'emailConfirm'])->name('mfa.email.confirm.post');
    Route::delete('/profile/mfa/email', [\App\Http\Controllers\MfaController::class, 'emailDisable'])->name('mfa.email.disable');

    // MFA — SMS OTP
    Route::get('/profile/mfa/sms/setup', [\App\Http\Controllers\MfaController::class, 'smsSetupForm'])->name('mfa.sms.setup');
    Route::post('/profile/mfa/sms/setup', [\App\Http\Controllers\MfaController::class, 'smsSetup'])->name('mfa.sms.setup.post');
    Route::get('/profile/mfa/sms/confirm', [\App\Http\Controllers\MfaController::class, 'smsConfirmForm'])->name('mfa.sms.confirm');
    Route::post('/profile/mfa/sms/confirm', [\App\Http\Controllers\MfaController::class, 'smsConfirm'])->name('mfa.sms.confirm.post');
    Route::delete('/profile/mfa/sms', [\App\Http\Controllers\MfaController::class, 'smsDisable'])->name('mfa.sms.disable');

    // MFA — WebAuthn
    Route::get('/profile/mfa/webauthn/register-options', [\App\Http\Controllers\MfaController::class, 'webAuthnRegisterOptions'])->name('mfa.webauthn.options');
    Route::get('/profile/mfa/webauthn/add', fn() => view('profile.mfa-webauthn-add'))->name('mfa.webauthn.add');
    Route::post('/profile/mfa/webauthn/register', [\App\Http\Controllers\MfaController::class, 'webAuthnRegister'])->name('mfa.webauthn.register');
    Route::delete('/profile/mfa/webauthn/{credentialId}', [\App\Http\Controllers\MfaController::class, 'webAuthnRemove'])->name('mfa.webauthn.remove');

    // Legacy redirects (backward compat)
    Route::get('/profile/mfa/enable', [\App\Http\Controllers\MfaController::class, 'totpEnableForm'])->name('mfa.enable.form');
    Route::post('/profile/mfa/enable', [\App\Http\Controllers\MfaController::class, 'totpEnable'])->name('mfa.enable');
    Route::delete('/profile/mfa', [\App\Http\Controllers\MfaController::class, 'totpDisable'])->name('mfa.disable');
});

Route::middleware(['auth','admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/nav', [\App\Http\Controllers\Admin\NavController::class, 'index'])->name('nav.index');
    Route::get('/nav/create', [\App\Http\Controllers\Admin\NavController::class, 'create'])->name('nav.create');
    Route::post('/nav', [\App\Http\Controllers\Admin\NavController::class, 'store'])->name('nav.store');
    Route::post('/nav/reorder', [\App\Http\Controllers\Admin\NavController::class, 'reorder'])->name('nav.reorder');
    Route::get('/nav/{nav}/edit', [\App\Http\Controllers\Admin\NavController::class, 'edit'])->name('nav.edit');
    Route::put('/nav/{nav}', [\App\Http\Controllers\Admin\NavController::class, 'update'])->name('nav.update');
    Route::delete('/nav/{nav}', [\App\Http\Controllers\Admin\NavController::class, 'destroy'])->name('nav.destroy');
    Route::resource('resources', \App\Http\Controllers\Admin\ResourceController::class)->names([
        'index'=>'resources.index','create'=>'resources.create','store'=>'resources.store',
        'edit'=>'resources.edit','update'=>'resources.update','destroy'=>'resources.destroy','show'=>'resources.show',
    ]);
    Route::prefix('resources/{resource}/slots')->name('slots.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\SlotController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\SlotController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Admin\SlotController::class, 'store'])->name('store');
        Route::get('/{slot}/edit', [\App\Http\Controllers\Admin\SlotController::class, 'edit'])->name('edit');
        Route::put('/{slot}', [\App\Http\Controllers\Admin\SlotController::class, 'update'])->name('update');
        Route::delete('/{slot}', [\App\Http\Controllers\Admin\SlotController::class, 'destroy'])->name('destroy');
    });
    Route::resource('roles', RoleController::class)->names([
        'index'=>'roles.index','create'=>'roles.create','store'=>'roles.store',
        'edit'=>'roles.edit','update'=>'roles.update','destroy'=>'roles.destroy','show'=>'roles.show',
    ])->except(['show']);
    Route::post('/roles/reorder', [\App\Http\Controllers\Admin\RoleController::class, 'reorder'])->name('roles.reorder');

    Route::post('/users/bulk', \App\Http\Controllers\Admin\UserBulkController::class)->name('users.bulk');
    Route::get('/users/export-zip', [\App\Http\Controllers\Admin\UserController::class, 'exportZip'])->name('users.export-zip');
    Route::get('/users/import', [\App\Http\Controllers\Admin\UserController::class, 'importForm'])->name('users.import');
    Route::post('/users/import', [\App\Http\Controllers\Admin\UserController::class, 'import'])->name('users.import.store');
    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [\App\Http\Controllers\Admin\UserController::class, 'create'])->name('users.create');
    Route::post('/users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [\App\Http\Controllers\Admin\UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
    Route::patch('/users/{user}/toggle', [\App\Http\Controllers\Admin\UserController::class, 'toggle'])->name('users.toggle');
    Route::post('/users/{user}/verify', [\App\Http\Controllers\Admin\UserController::class, 'verifyEmail'])->name('users.verify');
    Route::post('/users/{user}/mfa-disable', [\App\Http\Controllers\Admin\UserController::class, 'mfaDisable'])->name('users.mfa-disable');
    Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');
    Route::get('/reservations', [\App\Http\Controllers\Admin\ReservationController::class, 'index'])->name('reservations.index');
    Route::delete('/reservations/{reservation}', [\App\Http\Controllers\Admin\ReservationController::class, 'destroy'])->name('reservations.destroy');
    Route::get('/themes', [\App\Http\Controllers\Admin\ThemeController::class, 'index'])->name('themes.index');
    Route::put('/themes', [\App\Http\Controllers\Admin\ThemeController::class, 'update'])->name('themes.update');
    Route::get('/settings', fn() => redirect()->route('admin.settings.general'))->name('settings.index');
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/general',     [\App\Http\Controllers\Admin\SettingController::class, 'general'])->name('general');
        Route::post('/general',    [\App\Http\Controllers\Admin\SettingController::class, 'updateGeneral'])->name('general.update');
        Route::post('/logo',       [\App\Http\Controllers\Admin\SettingController::class, 'uploadLogo'])->name('logo.upload');
        Route::delete('/logo',     [\App\Http\Controllers\Admin\SettingController::class, 'deleteLogo'])->name('logo.delete');
        Route::get('/home',        [\App\Http\Controllers\Admin\SettingController::class, 'home'])->name('home');
        Route::post('/home',       [\App\Http\Controllers\Admin\SettingController::class, 'updateHome'])->name('home.update');
        Route::get('/auth',        [\App\Http\Controllers\Admin\SettingController::class, 'auth'])->name('auth');
        Route::post('/auth',       [\App\Http\Controllers\Admin\SettingController::class, 'updateAuth'])->name('auth.update');
        Route::get('/security',    [\App\Http\Controllers\Admin\SettingController::class, 'security'])->name('security');
        Route::post('/security',   [\App\Http\Controllers\Admin\SettingController::class, 'updateSecurity'])->name('security.update');
        Route::post('/security/proxies', [\App\Http\Controllers\Admin\SettingController::class, 'updateProxies'])->name('security.proxies');
        Route::get('/mail',        [\App\Http\Controllers\Admin\SettingController::class, 'mail'])->name('mail');
        Route::post('/mail',       [\App\Http\Controllers\Admin\SettingController::class, 'updateMail'])->name('mail.update');
        Route::post('/mail/test',  [\App\Http\Controllers\Admin\SettingController::class, 'testMail'])->name('mail.test');
        Route::get('/maintenance', [\App\Http\Controllers\Admin\SettingController::class, 'maintenance'])->name('maintenance');
        Route::post('/maintenance',[\App\Http\Controllers\Admin\SettingController::class, 'updateMaintenance'])->name('maintenance.update');
        Route::get('/reservations',[\App\Http\Controllers\Admin\SettingController::class, 'reservations'])->name('reservations');
        Route::post('/reservations',[\App\Http\Controllers\Admin\SettingController::class, 'updateReservations'])->name('reservations.update');
    });

    Route::get('/sanctions', [SanctionController::class, 'index'])->name('sanctions.index');
    Route::post('/users/{user}/sanctions', [SanctionController::class, 'store'])->name('sanctions.store');
    Route::delete('/sanctions/{sanction}', [SanctionController::class, 'destroy'])->name('sanctions.destroy');

    Route::resource('pages', \App\Http\Controllers\Admin\PageController::class)
        ->except(['show'])
        ->names(['index'=>'pages.index','create'=>'pages.create','store'=>'pages.store','edit'=>'pages.edit','update'=>'pages.update','destroy'=>'pages.destroy']);

    Route::get('/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/create', [\App\Http\Controllers\Admin\NotificationController::class, 'create'])->name('notifications.create');
    Route::post('/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'store'])->name('notifications.store');
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\Admin\NotificationController::class, 'destroy'])->name('notifications.destroy');

    Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
    Route::post('/logs/purge', [LogController::class, 'purge'])->name('logs.purge');

    Route::get('/api-tokens', [\App\Http\Controllers\Admin\ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('/api-tokens', [\App\Http\Controllers\Admin\ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/api-tokens/{token}', [\App\Http\Controllers\Admin\ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');

    Route::prefix('update')->name('update.')->group(function () {
        Route::get('/', [UpdateController::class, 'index'])->name('index');
        Route::post('/check', [UpdateController::class, 'check'])->name('check');
        Route::middleware('throttle:10,1')->group(function () {
            Route::post('/download', [UpdateController::class, 'download'])->name('download');
            Route::post('/install', [UpdateController::class, 'install'])->name('install');
            Route::post('/finalize', [UpdateController::class, 'finalize'])->name('finalize');
            Route::post('/restore', [UpdateController::class, 'restore'])->name('restore');
        });
    });

    Route::prefix('backups')->name('backups.')->group(function () {
        $backupController = \App\Http\Controllers\Admin\BackupController::class;
        Route::post('/', [$backupController, 'store'])->name('store');
        Route::put('/schedule', [$backupController, 'updateSchedule'])->name('schedule');
        // Telechargement et suppression : mot de passe exige, essais limites
        Route::middleware('throttle:6,1')->group(function () use ($backupController) {
            Route::post('/{name}/download', [$backupController, 'download'])->name('download');
            Route::delete('/{name}', [$backupController, 'destroy'])->name('destroy');
        });
    });
});

require __DIR__.'/auth.php';
