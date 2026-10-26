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

    /** Adresse d'un lien externe : http, https, mailto, tel, un chemin du site ou une ancre. Jamais `javascript:` ni `data:`. */
    public static function isSafeUrl(?string $url): bool
    {
        return $url !== null && preg_match('#^(?:https?://[^\s]+|mailto:[^\s]+|tel:[^\s]+|/(?!/)[^\s]*|\#[^\s]*)$#i', $url) === 1;
    }

    public function resolvedUrl(): string
    {
        try {
            return match ($this->type) {
                'home'            => route('home'),
                'reservations'    => route('reservations.index'),
                'my_reservations' => route('reservations.my'),
                'profile'         => route('profile.edit'),
                default           => $this->safeUrl(),
            };
        } catch (\Throwable) {
            return $this->safeUrl();
        }
    }

    /** Un lien enregistre avant cette verification est neutralise a l'affichage. */
    private function safeUrl(): string
    {
        return self::isSafeUrl($this->url) ? $this->url : '#';
    }
}
