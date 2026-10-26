<?php
namespace App\Models;

use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use Loggable;

    protected const ACTIVITY_NOUN = 'Page';
    protected const ACTIVITY_FEMININE = true;

    protected function activityContext(): string
    {
        return $this->title;
    }

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
