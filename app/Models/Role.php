<?php
namespace App\Models;

use App\Models\Concerns\Loggable;
use App\Support\Color;
use App\Support\Permission;
use App\Support\ReservationLimits;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'icon', 'description', 'color', 'is_admin', 'permissions', 'api_access', 'locked', 'power',
        'max_reservations_day', 'max_reservations_week', 'cancel_deadline_minutes', 'advance_days'];
    protected $casts = [
        'is_admin' => 'boolean', 'locked' => 'boolean', 'permissions' => 'array', 'api_access' => 'boolean',
        'max_reservations_day' => 'integer', 'max_reservations_week' => 'integer',
        'cancel_deadline_minutes' => 'integer', 'advance_days' => 'integer',
    ];

    use Loggable;

    protected const ACTIVITY_NOUN = 'Rôle';
    protected const ACTIVITY_FEMININE = false;

    /** Identifiant technique d'un role (unique, jamais modifie apres la creation). */
    public const NAME_RULE = 'required|string|max:50|regex:/^[a-z0-9_]+$/';

    public const DEFAULT_ICON = 'bi bi-star';

    public const DEFAULT_COLOR = '#6c757d';

    public function users() { return $this->hasMany(User::class); }

    /** Les comptes du role recopient son statut d'administrateur (users.is_admin), aussi quand on modifie le role. */
    protected static function booted(): void
    {
        static::saved(function (Role $role) {
            if ($role->wasChanged('is_admin')) {
                $role->users()->update(['is_admin' => $role->is_admin]);
            }
        });
    }

    /** Limites d'un nouveau role : celles du role par defaut ("Utilisateur") ; sans lui, celles de la base (sans limite). */
    public static function defaultLimits(): array
    {
        return self::defaultUser()?->only(array_keys(ReservationLimits::fields())) ?? [];
    }

    /**
     * Regles communes a un role (formulaire et import de configuration) ; $prefix pour un tableau, ex. "roles.*.".
     * $partial : libelle et couleur facultatifs (un import ne change que ce qu'il contient).
     * Les permissions inconnues ne sont pas refusees ici : Permission::only() les ecarte.
     */
    public static function rules(string $prefix = '', bool $partial = false): array
    {
        $required = $partial ? 'sometimes|required' : 'required';

        return [
            "{$prefix}label" => "{$required}|string|max:100",
            "{$prefix}icon" => 'nullable|string|max:60',
            "{$prefix}color" => "{$required}|string|regex:/^#[0-9a-fA-F]{6}$/",
            "{$prefix}api_access" => 'boolean',
            "{$prefix}permissions" => 'nullable|array',
            "{$prefix}permissions.*" => 'string',
        ];
    }

    public function hasPermission(string $permission): bool
    {
        // Une permission d'extension desactivee n'accorde plus rien (elle reste enregistree : elle revient avec l'extension)
        return $this->is_admin || (in_array($permission, $this->permissions ?? [], true) && array_key_exists($permission, Permission::all()));
    }

    /** Un compte avec ce role entre dans l'administration (tous les droits ou au moins une permission). */
    /** Pages restreintes qui donnent acces a ce role. */
    public function pages()
    {
        return $this->belongsToMany(Page::class, 'page_role');
    }

    public function grantsAdminAccess(): bool
    {
        return $this->is_admin || Permission::only($this->permissions ?? []) !== [];
    }

    protected function activityContext(): string
    {
        return $this->label;
    }

    /** Role attribue aux comptes crees sans role explicite (inscription, import CSV). */
    public static function defaultUser(): ?self
    {
        return static::where('name', 'user')->first();
    }

    public function getBadgeStyle(): string
    {
        $bg   = $this->color ?: self::DEFAULT_COLOR;
        $text = Color::contrastText($bg);
        return "background-color:{$bg};color:{$text};";
    }
}
