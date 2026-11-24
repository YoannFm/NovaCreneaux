<?php
namespace App\Models;

use App\Models\Concerns\Loggable;
use App\Support\Color;
use App\Support\Permission;
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

    public function users() { return $this->hasMany(User::class); }

    public function hasPermission(string $permission): bool
    {
        // Une permission d'extension desactivee n'accorde plus rien (elle reste enregistree : elle revient avec l'extension)
        return $this->is_admin || (in_array($permission, $this->permissions ?? [], true) && array_key_exists($permission, Permission::all()));
    }

    /** Un compte avec ce role entre dans l'administration (tous les droits ou au moins une permission). */
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
        $bg   = $this->color ?: '#6c757d';
        $text = Color::contrastText($bg);
        return "background-color:{$bg};color:{$text};";
    }
}
