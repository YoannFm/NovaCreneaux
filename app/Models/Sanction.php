<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Avertissement (type warning) ou suspension (type ban). Une suspension bloque les reservations
 * (site et API) jusqu'au soir de banned_until inclus, ou pour toujours si banned_until est vide.
 */
class Sanction extends Model
{
    /** Gravite d'une suspension : cle enregistree => [libelle, couleur Bootstrap]. */
    public const SEVERITIES = [
        'minor' => ['Mineure', 'info'],
        'moderate' => ['Moyenne', 'warning'],
        'severe' => ['Grave', 'danger'],
    ];

    protected $fillable = ['user_id', 'admin_id', 'type', 'severity', 'reason', 'banned_until'];

    protected $casts = ['banned_until' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
    public function admin() { return $this->belongsTo(User::class, 'admin_id'); }

    /** Suspensions en cours : definitives, ou dont la date de fin n'est pas passee. */
    public function scopeActiveBan(Builder $query): Builder
    {
        return $query->where('type', 'ban')->where(function (Builder $q) {
            $q->whereNull('banned_until')->orWhere('banned_until', '>=', now()->toDateString());
        });
    }

    public function isPermanent(): bool
    {
        return $this->type === 'ban' && $this->banned_until === null;
    }

    public function isActive(): bool
    {
        if ($this->type === 'warning') return true;
        if ($this->type === 'ban') {
            return $this->banned_until === null || $this->banned_until->gte(now()->startOfDay());
        }
        return false;
    }

    public function severityLabel(): ?string
    {
        return self::SEVERITIES[$this->severity][0] ?? null;
    }

    public function severityColor(): string
    {
        return self::SEVERITIES[$this->severity][1] ?? 'secondary';
    }
}
