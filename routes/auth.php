<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

// MFA login challenge (no auth required - user stored in session)
Route::get('mfa/challenge', [\App\Http\Controllers\MfaController::class, 'challengeForm'])->name('mfa.challenge.form');
Route::post('mfa/challenge/send', [\App\Http\Controllers\MfaController::class, 'challengeSend'])->name('mfa.challenge.send');
Route::post('mfa/challenge', [\App\Http\Controllers\MfaController::class, 'challenge'])->name('mfa.challenge');
Route::post('mfa/challenge/webauthn', [\App\Http\Controllers\MfaController::class, 'challengeWebAuthn'])->name('mfa.challenge.webauthn');
Route::get('mfa/challenge/recovery', [\App\Http\Controllers\MfaController::class, 'challengeRecoveryForm'])->name('mfa.challenge.recovery.form');
Route::post('mfa/challenge/recovery', [\App\Http\Controllers\MfaController::class, 'challengeRecovery'])->name('mfa.challenge.recovery');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    // Connexion avec le compte de l'etablissement (OpenID Connect ou CAS), si elle est activee
    Route::middleware('throttle:sso')->group(function () {
        Route::get('login/etablissement', [\App\Http\Controllers\Auth\SsoController::class, 'redirect'])->name('sso.redirect');
        Route::get('login/etablissement/retour', [\App\Http\Controllers\Auth\SsoController::class, 'callback'])->name('sso.callback');
    });

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    // Limite par adresse saisie et plafond par IP (AppServiceProvider::limitGuestForms) : le formulaire envoie un e-mail
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset-link')
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1,email-verification'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1,email-verification')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    // Mot de passe verifie : limite pour qu'une session ouverte ne permette pas de l'essayer sans fin
    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store'])->middleware('throttle:6,1,password');

    Route::put('password', [PasswordController::class, 'update'])->middleware('throttle:6,1,password')->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
