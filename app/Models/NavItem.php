<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavItem extends Model
{
    protected $fillable = ['label', 'type', 'url', 'icon', 'target', 'is_active', 'admin_only', 'position'];
    protected $casts    = ['is_active' => 'boolean', 'admin_only' => 'boolean'];

    private static array $typeLabels = [
        'home'            => 'Page d\'accueil',
        'reservations'    => 'Réservations',
        'my_reservations' => 'Mes réservations',
        'profile'         => 'Mon profil',
        'external'        => 'Lien externe',
    ];

    public function typeLabel(): string
    {
        return self::$typeLabels[$this->type] ?? 'Lien externe';
    }

    public static function typeOptions(): array
    {
        return self::$typeLabels;
    }

    public function resolvedUrl(): string
    {
        try {
            return match ($this->type) {
                'home'            => route('home'),
                'reservations'    => route('reservations.index'),
                'my_reservations' => route('reservations.my'),
                'profile'         => route('profile.edit'),
                default           => $this->url ?? '#',
            };
        } catch (\Throwable) {
            return $this->url ?? '#';
        }
    }
}
