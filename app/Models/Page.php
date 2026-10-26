<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['title', 'description', 'slug', 'content', 'is_enabled', 'is_restricted'];

    protected function casts(): array
    {
        return [
            'is_enabled'    => 'boolean',
            'is_restricted' => 'boolean',
        ];
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'page_role');
    }

    public function isRestricted(): bool
    {
        return (bool) $this->is_restricted;
    }
}
