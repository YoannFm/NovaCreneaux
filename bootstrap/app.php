<?php

use App\Http\ApiError;
use App\Services\ApiAccessLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminMiddleware::class,
            'staff' => \App\Http\Middleware\EnsureStaff::class,
            'permission' => \App\Http\Middleware\RequirePermission::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\CheckInstallation::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SecurityHeaders::class);
        // Un changement de mot de passe deconnecte les autres sessions du compte
        $middleware->appendToGroup('web', \App\Http\Middleware\EndSessionsOnPasswordChange::class);
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\EnforceMaintenanceMode::class,
            \App\Http\Middleware\EnforceAdminTwoFactor::class,
            \App\Http\Middleware\EnforceEmailVerification::class,
            \App\Http\Middleware\RunDueBackupOnRequest::class,
            \App\Http\Middleware\DeliverWebhooksOnRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        // Toutes les erreurs de l'API ont le meme format (voir App\Http\ApiError)
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            // Laravel a deja converti les exceptions d'autorisation et de modele introuvable en erreurs HTTP
            return match (true) {
                $e instanceof ValidationException => ApiError::response('validation_failed', 'Les données envoyées sont invalides.', 422, $e->errors()),
                $e instanceof AuthenticationException => tap(ApiError::response('unauthenticated', 'Jeton manquant, invalide ou expiré.', 401), fn () => ApiAccessLog::denied('unauthenticated', $request)),
                $e instanceof HttpExceptionInterface => ApiError::fromHttpException($e),
                config('app.debug') => null,
                default => ApiError::response('server_error', 'Erreur interne du serveur.', 500),
            };
        });
    })->create();
