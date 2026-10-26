<?php
namespace App\Models;

use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'label', 'icon', 'description', 'color', 'is_admin', 'permissions', 'locked', 'power',
        'max_reservations_day', 'max_reservations_week', 'cancel_deadline_minutes', 'advance_days'];
    protected $casts = [
        'is_admin' => 'boolean', 'locked' => 'boolean', 'permissions' => 'array',
        'max_reservations_day' => 'integer', 'max_reservations_week' => 'integer',
        'cancel_deadline_minutes' => 'integer', 'advance_days' => 'integer',
    ];

    use Loggable;

    protected const ACTIVITY_NOUN = 'Rôle';
    protected const ACTIVITY_FEMININE = false;

    public function users() { return $this->hasMany(User::class); }

    public function hasPermission(string $permission): bool
    {
        return $this->is_admin || in_array($permission, $this->permissions ?? [], true);
    }

    /** Un compte avec ce role entre dans l'administration (tous les droits ou au moins une permission). */
    public function grantsAdminAccess(): bool
    {
        return $this->is_admin || ! empty($this->permissions);
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
        $text = $this->getContrastColor($bg);
        return "background-color:{$bg};color:{$text};";
    }

    private function getContrastColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 128 ? '#000000' : '#ffffff';
    }
}
