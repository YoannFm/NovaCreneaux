<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SanctionController;
use App\Http\Controllers\Admin\LogController;
use App\Http\Controllers\Admin\UpdateController;
use App\Http\Controllers\Admin\RoleController;
use App\Support\Permission;
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

// Logo du site, servi depuis storage (App\Support\SiteLogo)
Route::get('/logo', \App\Http\Controllers\SiteLogoController::class)->name('site.logo');

// Pages publiques
Route::get('/p/{slug}', function (string $slug) {
    $page = \App\Models\Page::where('slug', $slug)->where('is_enabled', true)->firstOrFail();
    if ($page->is_restricted) {
        if (!auth()->check()) return redirect()->route('login');
        abort_unless($page->isVisibleTo(auth()->user()), 403);
    }
    return view('pages.show', compact('page'));
})->name('pages.show');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        $upcoming = auth()->user()->upcomingReservations()->take(5);
        return view('dashboard', compact('upcoming'));
    })->name('dashboard');

    Route::get('/', [\App\Http\Controllers\HomeController::class, 'index'])->name('home');
    Route::get('/reserver', [\App\Http\Controllers\ReservationController::class, 'index'])->name('reservations.index');
    Route::post('/reservations', [\App\Http\Controllers\ReservationController::class, 'store'])->name('reservations.store');
    Route::delete('/reservations/{id}', [\App\Http\Controllers\ReservationController::class, 'destroy'])->name('reservations.destroy');
    Route::get('/mes-reservations', [\App\Http\Controllers\ReservationController::class, 'my'])->name('reservations.my');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:6,1,password')->name('profile.update');
    Route::patch('/profile/theme', [ProfileController::class, 'updateTheme'])->name('profile.theme');
    Route::patch('/profile/mails', [ProfileController::class, 'updateMails'])->name('profile.mails');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->middleware('throttle:6,1,password')->name('profile.destroy');
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\NotificationController::class, 'read'])->whereNumber('notification')->name('notifications.read');
    Route::delete('/notifications/{notification}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->whereNumber('notification')->name('notifications.destroy');
    Route::post('/profile/api-tokens', [\App\Http\Controllers\ProfileApiTokenController::class, 'store'])->middleware('throttle:10,1,api-token')->name('profile.api-tokens.store');
    Route::delete('/profile/api-tokens/{token}', [\App\Http\Controllers\ProfileApiTokenController::class, 'destroy'])->whereNumber('token')->name('profile.api-tokens.destroy');

    // Double authentification. Ajouter une application, un numero ou une cle demande le mot de passe (password.confirm) :
    // sinon une session restee ouverte suffisait a installer sa propre methode et a enfermer le titulaire dehors.
    // Codes de confirmation (mfa-code) et mot de passe actuel (password) limites : sans limite, une session ouverte
    // permettait de les essayer tous. Le 3e parametre de throttle nomme le compteur : sans lui, toutes les routes
    // limitees partagent le meme compteur par compte (7 recherches du planning bloquaient l'envoi d'un code).
    $mfa = \App\Http\Controllers\MfaController::class;

    // MFA - TOTP
    Route::middleware('password.confirm')->group(function () use ($mfa) {
        Route::get('/profile/mfa/totp/enable', [$mfa, 'totpEnableForm'])->name('mfa.totp.enable.form');
        Route::post('/profile/mfa/totp/enable', [$mfa, 'totpEnable'])->middleware('throttle:6,1,mfa-code')->name('mfa.totp.enable');
    });
    Route::delete('/profile/mfa/totp', [$mfa, 'totpDisable'])->middleware('throttle:6,1,password')->name('mfa.totp.disable');
    Route::get('/profile/mfa/recovery-codes', [$mfa, 'recoveryCodesShow'])->name('mfa.recovery-codes');
    Route::post('/profile/mfa/recovery-codes/confirm', [$mfa, 'recoveryCodesConfirm'])->name('mfa.recovery-codes.confirm');
    Route::post('/profile/mfa/recovery-codes/regenerate', [$mfa, 'recoveryCodesRegenerate'])->middleware('throttle:6,1,password')->name('mfa.recovery-codes.regenerate');

    // MFA - Email OTP (le code part a l'adresse du compte : pas de mot de passe demande pour l'activer)
    Route::post('/profile/mfa/email/enable', [$mfa, 'emailEnable'])->middleware('throttle:5,1,mfa-send')->name('mfa.email.enable');
    Route::get('/profile/mfa/email/confirm', [$mfa, 'emailConfirmForm'])->name('mfa.email.confirm');
    Route::post('/profile/mfa/email/confirm', [$mfa, 'emailConfirm'])->middleware('throttle:6,1,mfa-code')->name('mfa.email.confirm.post');
    Route::delete('/profile/mfa/email', [$mfa, 'emailDisable'])->middleware('throttle:6,1,password')->name('mfa.email.disable');

    // MFA - SMS OTP
    Route::middleware('password.confirm')->group(function () use ($mfa) {
        Route::get('/profile/mfa/sms/setup', [$mfa, 'smsSetupForm'])->name('mfa.sms.setup');
        Route::post('/profile/mfa/sms/setup', [$mfa, 'smsSetup'])->middleware('throttle:5,1,mfa-send')->name('mfa.sms.setup.post');
        Route::get('/profile/mfa/sms/confirm', [$mfa, 'smsConfirmForm'])->name('mfa.sms.confirm');
        Route::post('/profile/mfa/sms/confirm', [$mfa, 'smsConfirm'])->middleware('throttle:6,1,mfa-code')->name('mfa.sms.confirm.post');
    });
    Route::delete('/profile/mfa/sms', [$mfa, 'smsDisable'])->middleware('throttle:6,1,password')->name('mfa.sms.disable');

    // MFA - WebAuthn
    Route::middleware('password.confirm')->group(function () use ($mfa) {
        Route::get('/profile/mfa/webauthn/register-options', [$mfa, 'webAuthnRegisterOptions'])->name('mfa.webauthn.options');
        Route::get('/profile/mfa/webauthn/add', fn () => view('profile.mfa-webauthn-add'))->name('mfa.webauthn.add');
        Route::post('/profile/mfa/webauthn/register', [$mfa, 'webAuthnRegister'])->name('mfa.webauthn.register');
    });
    Route::delete('/profile/mfa/webauthn/{credentialId}', [$mfa, 'webAuthnRemove'])->middleware('throttle:6,1,password')->name('mfa.webauthn.remove');

    // Legacy redirects (backward compat)
    Route::middleware('password.confirm')->group(function () use ($mfa) {
        Route::get('/profile/mfa/enable', [$mfa, 'totpEnableForm'])->name('mfa.enable.form');
        Route::post('/profile/mfa/enable', [$mfa, 'totpEnable'])->middleware('throttle:6,1,mfa-code')->name('mfa.enable');
    });
    Route::delete('/profile/mfa', [$mfa, 'totpDisable'])->middleware('throttle:6,1,password')->name('mfa.disable');
});

Route::middleware(['auth','staff'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::middleware('permission:'.Permission::NAVIGATION)->group(function () {
        Route::get('/nav', [\App\Http\Controllers\Admin\NavController::class, 'index'])->name('nav.index');
        Route::get('/nav/create', [\App\Http\Controllers\Admin\NavController::class, 'create'])->name('nav.create');
        Route::post('/nav', [\App\Http\Controllers\Admin\NavController::class, 'store'])->name('nav.store');
        Route::post('/nav/reorder', [\App\Http\Controllers\Admin\NavController::class, 'reorder'])->name('nav.reorder');
        Route::get('/nav/{nav}/edit', [\App\Http\Controllers\Admin\NavController::class, 'edit'])->name('nav.edit');
        Route::put('/nav/{nav}', [\App\Http\Controllers\Admin\NavController::class, 'update'])->name('nav.update');
        Route::delete('/nav/{nav}', [\App\Http\Controllers\Admin\NavController::class, 'destroy'])->name('nav.destroy');
    });

    Route::middleware('permission:'.Permission::RESOURCES)->group(function () {
        // Pas de page de detail : la liste et la page de modification suffisent
        Route::resource('resources', \App\Http\Controllers\Admin\ResourceController::class)->except('show');
        // scopeBindings : un creneau n'est trouve que dans la ressource de l'adresse (sinon 404)
        Route::prefix('resources/{resource}/slots')->name('slots.')->scopeBindings()->group(function () {
            Route::get('/', [\App\Http\Controllers\Admin\SlotController::class, 'index'])->name('index');
            Route::get('/create', [\App\Http\Controllers\Admin\SlotController::class, 'create'])->name('create');
            Route::post('/', [\App\Http\Controllers\Admin\SlotController::class, 'store'])->name('store');
            Route::get('/{slot}/edit', [\App\Http\Controllers\Admin\SlotController::class, 'edit'])->name('edit');
            Route::put('/{slot}', [\App\Http\Controllers\Admin\SlotController::class, 'update'])->name('update');
            Route::delete('/{slot}', [\App\Http\Controllers\Admin\SlotController::class, 'destroy'])->name('destroy');
        });
    });

    Route::middleware('permission:'.Permission::USERS)->group(function () {
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
    });

    Route::middleware('permission:'.Permission::RESERVATIONS)->group(function () {
        Route::get('/reservations', [\App\Http\Controllers\Admin\ReservationController::class, 'index'])->name('reservations.index');
        Route::post('/reservations', [\App\Http\Controllers\Admin\ReservationController::class, 'store'])->name('reservations.store');
        Route::get('/reservations/users', [\App\Http\Controllers\Admin\ReservationController::class, 'searchUsers'])->middleware('throttle:60,1,planning-search')->name('reservations.users');
        Route::delete('/reservations/{reservation}', [\App\Http\Controllers\Admin\ReservationController::class, 'destroy'])->name('reservations.destroy');
    });

    Route::middleware('permission:'.Permission::APPEARANCE)->group(function () {
        Route::get('/themes', [\App\Http\Controllers\Admin\ThemeController::class, 'index'])->name('themes.index');
        Route::put('/themes', [\App\Http\Controllers\Admin\ThemeController::class, 'update'])->name('themes.update');
    });

    Route::middleware('permission:'.Permission::SANCTIONS)->group(function () {
        Route::get('/sanctions', [SanctionController::class, 'index'])->name('sanctions.index');
        Route::post('/users/{user}/sanctions', [SanctionController::class, 'store'])->name('sanctions.store');
        Route::delete('/sanctions/{sanction}', [SanctionController::class, 'destroy'])->name('sanctions.destroy');
    });

    Route::middleware('permission:'.Permission::PAGES)->group(function () {
        Route::resource('pages', \App\Http\Controllers\Admin\PageController::class)
            ->except(['show'])
            ->names(['index'=>'pages.index','create'=>'pages.create','store'=>'pages.store','edit'=>'pages.edit','update'=>'pages.update','destroy'=>'pages.destroy']);
    });

    Route::middleware('permission:'.Permission::NOTIFICATIONS)->group(function () {
        Route::get('/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/create', [\App\Http\Controllers\Admin\NotificationController::class, 'create'])->name('notifications.create');
        Route::post('/notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'store'])->name('notifications.store');
        Route::delete('/notifications/{notification}', [\App\Http\Controllers\Admin\NotificationController::class, 'destroy'])->name('notifications.destroy');
    });

    Route::middleware('permission:'.Permission::LOGS)->group(function () {
        Route::get('/logs', [LogController::class, 'index'])->name('logs.index');
    });

    Route::middleware('admin')->group(function () {
        Route::prefix('plugins')->name('plugins.')->controller(\App\Http\Controllers\Admin\PluginController::class)->where(['plugin' => '[a-z][a-z0-9-]*'])->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/refresh', 'refresh')->name('refresh');
            Route::post('/{plugin}/install', 'install')->name('install');
            Route::post('/{plugin}/enable', 'enable')->name('enable');
            Route::post('/{plugin}/disable', 'disable')->name('disable');
            Route::delete('/{plugin}', 'destroy')->name('destroy');
        });
        Route::resource('roles', RoleController::class)->names([
            'index'=>'roles.index','create'=>'roles.create','store'=>'roles.store',
            'edit'=>'roles.edit','update'=>'roles.update','destroy'=>'roles.destroy','show'=>'roles.show',
        ])->except(['show']);
        Route::post('/roles/reorder', [\App\Http\Controllers\Admin\RoleController::class, 'reorder'])->name('roles.reorder');
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
            Route::get('/sso',         [\App\Http\Controllers\Admin\SettingController::class, 'sso'])->name('sso');
            Route::post('/sso',        [\App\Http\Controllers\Admin\SettingController::class, 'updateSso'])->name('sso.update');
            Route::post('/security/proxies', [\App\Http\Controllers\Admin\SettingController::class, 'updateProxies'])->name('security.proxies');
            Route::get('/mail',        [\App\Http\Controllers\Admin\SettingController::class, 'mail'])->name('mail');
            Route::post('/mail',       [\App\Http\Controllers\Admin\SettingController::class, 'updateMail'])->name('mail.update');
            Route::post('/mail/test',  [\App\Http\Controllers\Admin\SettingController::class, 'testMail'])->name('mail.test');
            Route::get('/maintenance', [\App\Http\Controllers\Admin\SettingController::class, 'maintenance'])->name('maintenance');
            Route::post('/maintenance',[\App\Http\Controllers\Admin\SettingController::class, 'updateMaintenance'])->name('maintenance.update');
            Route::get('/reservations',[\App\Http\Controllers\Admin\SettingController::class, 'reservations'])->name('reservations');
            Route::post('/reservations',[\App\Http\Controllers\Admin\SettingController::class, 'updateReservations'])->name('reservations.update');
            Route::prefix('configuration')->name('configuration.')->controller(\App\Http\Controllers\Admin\ConfigurationController::class)->group(function () {
                Route::get('/', 'index')->name('index');
                // Mot de passe exige, essais limites
                Route::middleware('throttle:6,1,configuration')->group(function () {
                    Route::post('/export', 'export')->name('export');
                    Route::post('/import', 'import')->name('import');
                    Route::post('/templates/{template}', 'template')->where('template', '[a-z0-9-]+')->name('template');
                });
            });
        });
        Route::post('/logs/purge', [LogController::class, 'purge'])->name('logs.purge');
        Route::get('/api-tokens', [\App\Http\Controllers\Admin\ApiTokenController::class, 'index'])->name('api-tokens.index');
        Route::post('/api-tokens', [\App\Http\Controllers\Admin\ApiTokenController::class, 'store'])->name('api-tokens.store');
        Route::resource('webhooks', \App\Http\Controllers\Admin\WebhookController::class)->except(['show'])->names([
            'index'=>'webhooks.index','create'=>'webhooks.create','store'=>'webhooks.store',
            'edit'=>'webhooks.edit','update'=>'webhooks.update','destroy'=>'webhooks.destroy',
        ]);
        Route::post('/webhooks/{webhook}/test', [\App\Http\Controllers\Admin\WebhookController::class, 'test'])->middleware('throttle:10,1,webhook-test')->name('webhooks.test');
        Route::post('/webhooks/{webhook}/secret', [\App\Http\Controllers\Admin\WebhookController::class, 'regenerateSecret'])->name('webhooks.secret');
        Route::put('/api-tokens/status', [\App\Http\Controllers\Admin\ApiTokenController::class, 'updateStatus'])->name('api-tokens.status');
        Route::delete('/api-tokens/{token}', [\App\Http\Controllers\Admin\ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
        Route::prefix('update')->name('update.')->group(function () {
            Route::get('/', [UpdateController::class, 'index'])->name('index');
            Route::post('/check', [UpdateController::class, 'check'])->name('check');
            Route::middleware('throttle:10,1,update')->group(function () {
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
            Route::middleware('throttle:6,1,backup')->group(function () use ($backupController) {
                Route::post('/{name}/download', [$backupController, 'download'])->name('download');
                Route::delete('/{name}', [$backupController, 'destroy'])->name('destroy');
            });
        });
    });
});

require __DIR__.'/auth.php';
