<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Casts\EncryptedOrUnreadable;
use App\Mail\EmailVerificationLink;
use App\Mail\PasswordResetLink;
use App\Services\ApiTokenService;
use App\Services\ReservationService;
use App\Services\Webhook\WebhookDispatcher;
use App\Services\Webhook\WebhookPayload;
use App\Support\WebhookEvent;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /** Reservation a l'avance d'un compte sans role. */
    public const DEFAULT_ADVANCE_DAYS = 30;

    /** Longueur maximale de la classe, la meme partout (inscription, profil, administration, import CSV). */
    public const CLASSE_MAX = 100;

    /** Regle de validation de la classe : une seule, sinon une classe acceptee a un endroit bloquait la fiche ailleurs. */
    public static function classeRules(): array
    {
        return ['nullable', 'string', 'max:' . self::CLASSE_MAX];
    }

    /** Adresse e-mail telle qu'elle est enregistree : sans espaces autour, en minuscules. */
    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** Quel que soit le chemin (inscription, import, administration, profil), l'adresse est enregistree en minuscules. */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => $value === null ? null : self::normalizeEmail($value));
    }

    protected $fillable = [
        'name', 'email', 'password', 'classe', 'is_admin', 'is_active', 'role_id',
        'theme_mode', 'accent_color',
        'two_factor_secret', 'two_factor_confirmed_at', 'two_factor_recovery_codes', 'two_factor_last_step',
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
            // Chiffree avec APP_KEY : une copie de la base ou d'une sauvegarde ne permet pas de generer les codes
            'two_factor_secret'       => EncryptedOrUnreadable::class,
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at'           => 'datetime',
            'password_changed_at'     => 'datetime',
            'mail_opt_out'            => 'array',
        ];
    }

    /**
     * Valeur stockee et non dechiffree : une cle illisible (APP_KEY changee) reste une double authentification
     * activee. Aucun code ne la valide alors ; les codes de secours et les autres methodes restent possibles.
     */
    public function hasTotp(): bool
    {
        return ($this->getAttributes()['two_factor_secret'] ?? null) !== null && $this->two_factor_confirmed_at !== null;
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

    /** Cet e-mail facultatif (Support\MailPreferences) est voulu : tous le sont tant que la personne n'en a pas refuse. */
    public function wantsMail(string $kind): bool
    {
        return !in_array($kind, $this->mail_opt_out ?? [], true);
    }

    /** Reservations dont le creneau n'est pas termine (une seance finie plus tot aujourd'hui n'est plus "a venir"), par date et heure. */
    public function upcomingReservations(): Collection
    {
        return $this->reservations()->with('slot.resource')->whereDate('reservation_date', '>=', today())->get()
            ->reject(fn (Reservation $reservation) => $reservation->hasEnded())
            ->sortBy(fn (Reservation $reservation) => $reservation->startsAt()->timestamp)
            ->values();
    }
    protected static function booted(): void
    {
        static::created(fn (self $user) => app(WebhookDispatcher::class)->dispatch(WebhookEvent::USER_CREATED, ['user' => WebhookPayload::user($user)]));
        // Tous les chemins de suppression (profil, fiche, action groupee). Ne rien renvoyer : une valeur renvoyee par un
        // ecouteur "deleting" arrete les ecouteurs suivants (et false annulerait la suppression).
        static::deleting(function (self $user): void {
            // Places a venir liberees et annoncees aux webhooks
            app(ReservationService::class)->releaseUpcomingOfDeletedAccount($user);
            // Jetons d'API : rien ne les relie au compte par une cle etrangere (relation polymorphe), ils restaient en base
            $user->tokens()->delete();
        });
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

    /**
     * Le role fait foi. La colonne users.is_admin n'en est qu'une copie (requetes : installation, tableau de bord),
     * tenue a jour par Role ; elle ne sert seule que pour un compte sans role. Avant, la colonne restee a vrai
     * gardait administrateur un compte dont le role ne l'etait plus.
     */
    public function isAdmin(): bool
    {
        return $this->role ? (bool) $this->role->is_admin : (bool) $this->is_admin;
    }

    /**
     * Seul administrateur actif : lui retirer ses droits ou le desactiver laisserait le site sans administrateur
     * (plus d'acces aux reglages, aux roles ni aux mises a jour). La colonne is_admin suit le role (Role::booted).
     */
    public function isLastActiveAdmin(): bool
    {
        return $this->isAdmin() && $this->is_active
            && !static::whereKeyNot($this->id)->where('is_admin', true)->where('is_active', true)->exists();
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

    /**
     * Comptes que $staff peut gerer (meme regle que canManage, en requete) : tous pour un administrateur, sinon les
     * comptes sans acces a l'administration (role sans permission, ou sans role et non administrateur).
     */
    public function scopeManageableBy(Builder $query, self $staff): Builder
    {
        if ($staff->isAdmin()) {
            return $query;
        }
        $ordinaryRoles = Role::all()->reject(fn (Role $role) => $role->grantsAdminAccess())->modelKeys();

        return $query->where(fn (Builder $q) => $q->whereIn('role_id', $ordinaryRoles)
            ->orWhere(fn (Builder $q) => $q->whereNull('role_id')->where('is_admin', false)));
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
        return $this->sanctions()->activeBan()->first();
    }

    /**
     * Lien de reinitialisation en francais, au format des autres e-mails du site (remplace la notification
     * anglaise de Laravel). Une erreur d'envoi remonte a l'appelant, qui decide quoi en dire.
     */
    public function sendPasswordResetNotification($token): void
    {
        $url = route('password.reset', ['token' => $token, 'email' => $this->getEmailForPasswordReset()]);
        $expires = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        Mail::to($this->email)->send(new PasswordResetLink($this->name, $url, $expires));
    }

    /** E-mail francais de confirmation de l'adresse, a la place de la notification anglaise de Laravel. */
    public function sendEmailVerificationNotification(): void
    {
        $expires = (int) config('auth.verification.expire', 60);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes($expires), [
            'id' => $this->getKey(),
            'hash' => sha1($this->getEmailForVerification()),
        ]);

        Mail::to($this->email)->send(new EmailVerificationLink($this->name, $url, $expires));
    }

    /** Nombre de jours a l'avance ou ce compte peut reserver (limite de son role ; sans role, 30). */
    public function advanceDays(): int
    {
        return max(1, (int) ($this->role?->advance_days ?? self::DEFAULT_ADVANCE_DAYS));
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
