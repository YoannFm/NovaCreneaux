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

    public const FORMAT_JSON = 'json';
    public const FORMAT_DISCORD = 'discord';

    protected $fillable = ['name', 'url', 'secret', 'events', 'format', 'show_names', 'is_active'];
    protected $hidden = ['secret'];

    protected $casts = [
        'secret' => 'encrypted',
        'events' => 'array',
        'is_active' => 'boolean',
        'show_names' => 'boolean',
        'last_delivery_at' => 'datetime',
    ];

    public function deliveries()
    {
        return $this->hasMany(WebhookDelivery::class)->latest('id');
    }

    public function isDiscord(): bool
    {
        return $this->format === self::FORMAT_DISCORD;
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? [], true);
    }

    protected function activityContext(): string
    {
        return "{$this->name} ({$this->url})";
    }
}
