<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sanction extends Model
{
    protected $fillable = ['user_id', 'admin_id', 'type', 'reason', 'banned_until'];

    protected $casts = ['banned_until' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
    public function admin() { return $this->belongsTo(User::class, 'admin_id'); }

    public function isPermanent(): bool
    {
        return $this->type === 'ban' && $this->banned_until === null;
    }

    public function isActive(): bool
    {
        if ($this->type === 'warning') return true;
        if ($this->type === 'ban') {
            return $this->banned_until === null || $this->banned_until->gte(now());
        }
        return false;
    }
}
