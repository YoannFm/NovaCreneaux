<?php

namespace App\Models;

use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Model;

/** Adresse externe prevenue des evenements du site. Le secret (chiffre) signe chaque envoi. */
class Webhook extends Model
{
    use Loggable;

    protected const ACTIVITY_NOUN = 'Webhook';
    protected const ACTIVITY_FEMININE = false;

    /** Charge utile standard signee, prereglage du catalogue (`preset`) ou format personnalise (`settings.custom`). */
    public const FORMAT_JSON = 'json';
    public const FORMAT_PRESET = 'preset';
    public const FORMAT_CUSTOM = 'custom';

    protected $fillable = ['name', 'url', 'secret', 'events', 'format', 'preset', 'settings', 'show_names', 'is_active'];
    protected $hidden = ['secret', 'settings'];

    protected $casts = [
        'secret' => 'encrypted',
        // Champs du prereglage ou reglages du format personnalise : jetons et en-tetes y sont chiffres
        'settings' => 'encrypted:array',
        'events' => 'array',
        'is_active' => 'boolean',
        'show_names' => 'boolean',
        'last_delivery_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class)->latest('id');
    }

    /** Identifiant du format tel qu'il apparait dans le formulaire : `json`, `custom` ou celui du prereglage. */
    public function formatChoice(): string
    {
        return $this->format === self::FORMAT_PRESET ? (string) $this->preset : (string) ($this->format ?: self::FORMAT_JSON);
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }

    protected function activityContext(): string
    {
        // Le chemin de l'adresse peut contenir un jeton : le journal n'en garde que le domaine
        return "{$this->name} (" . parse_url($this->url, PHP_URL_HOST) . ')';
    }
}
