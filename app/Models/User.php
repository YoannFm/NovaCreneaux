<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Services\ApiTokenService;
use App\Services\Webhook\WebhookDispatcher;
use App\Services\Webhook\WebhookPayload;
use App\Support\WebhookEvent;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'classe', 'is_admin', 'is_active', 'role_id',
        'theme_mode', 'accent_color',
        'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes',
        'mfa_email_enabled', 'mfa_sms_enabled', 'mfa_phone', 'mfa_webauthn_credentials',
        'last_login_at', 'last_ip_address', 'password_changed_at',
    ];
    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'password'                => 'hashed',
            'is_admin'                => 'boolean',
            'is_active'               => 'boolean',
            'mfa_email_enabled'       => 'boolean',
            'mfa_sms_enabled'         => 'boolean',
            'email_verified_at'       => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at'           => 'datetime',
            'password_changed_at'     => 'datetime',
        ];
    }

    public function hasTotp(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function hasEmailMfa(): bool
    {
        return (bool) $this->mfa_email_enabled;
    }

    public function hasSmsMfa(): bool
    {
        return (bool) $this->mfa_sms_enabled && $this->mfa_phone !== null;
    }

    public function hasWebAuthn(): bool
    {
        $credentials = $this->webAuthnCredentials();
        return count($credentials) > 0;
    }

    public function hasAnyMfa(): bool
    {
        return $this->hasTotp() || $this->hasEmailMfa() || $this->hasSmsMfa() || $this->hasWebAuthn();
    }

    public function enabledMfaMethods(): array
    {
        $methods = [];
        if ($this->hasTotp())     $methods[] = 'totp';
        if ($this->hasEmailMfa()) $methods[] = 'email';
        if ($this->hasSmsMfa())   $methods[] = 'sms';
        if ($this->hasWebAuthn()) $methods[] = 'webauthn';
        return $methods;
    }

    public function webAuthnCredentials(): array
    {
        if (!$this->mfa_webauthn_credentials) return [];
        return json_decode($this->mfa_webauthn_credentials, true) ?? [];
    }

    /** @deprecated Use hasTotp() */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->hasTotp();
    }

    public function role()         { return $this->belongsTo(Role::class); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    protected static function booted(): void
    {
        static::created(fn (self $user) => app(WebhookDispatcher::class)->dispatch(WebhookEvent::USER_CREATED, ['user' => WebhookPayload::user($user)]));
        static::deleted(fn (self $user) => app(WebhookDispatcher::class)->dispatch(WebhookEvent::USER_DELETED, [
            'user' => WebhookPayload::user($user),
            // Qui a supprime le compte : lui-meme, un administrateur, ou le systeme (commande, nettoyage)
            'deleted_by' => auth()->check() ? (auth()->id() === $user->id ? 'self' : 'admin') : 'system',
        ]));
    }

    /** Notifications affichees sur le site (`notifications()` est deja pris par le trait Notifiable de Laravel). */
    public function siteNotifications() { return $this->hasMany(UserNotification::class)->latest('id'); }

    public function unreadNotificationCount(): int { return $this->siteNotifications()->unread()->count(); }

    public function sanctions()    { return $this->hasMany(Sanction::class)->latest(); }

    public function isAdmin(): bool
    {
        return ($this->role && $this->role->is_admin) || $this->is_admin;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isAdmin() || ($this->role?->hasPermission($permission) ?? false);
    }

    /** Entre dans l'administration : administrateur, ou role avec au moins une permission. */
    public function hasAdminAccess(): bool
    {
        return $this->isAdmin() || ($this->role?->grantsAdminAccess() ?? false);
    }

    /** Le personnel delegue ne gere que les comptes ordinaires : jamais un administrateur ni un autre membre du personnel. */
    public function canManage(self $target): bool
    {
        return $this->isAdmin() || ! $target->hasAdminAccess();
    }

    public function canAssign(Role $role): bool
    {
        return $this->isAdmin() || ! $role->grantsAdminAccess();
    }

    /** L'API est activee et le role du compte l'autorise (sans role : autorise). */
    public function canUseApi(): bool
    {
        return ApiTokenService::enabled() && ($this->role?->api_access ?? true);
    }

    public function roleName(): string
    {
        return $this->role?->label ?? 'Utilisateur';
    }

    public function roleColor(): string
    {
        return $this->role?->color ?? '#6c757d';
    }

    public function activeBan(): ?Sanction
    {
        return $this->sanctions()
            ->where('type', 'ban')
            ->where(function ($q) {
                $q->whereNull('banned_until')
                  ->orWhere('banned_until', '>=', now()->toDateString());
            })->first();
    }

    public function warningCount(): int
    {
        return $this->sanctions()->where('type', 'warning')->count();
    }

    /** Initiales affichees a la place d'un avatar : aucun service externe n'est appele. */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        $letters = array_map(fn ($word) => mb_substr($word, 0, 1), array_slice($words, 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: '?';
    }
}
