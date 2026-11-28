<?php
namespace App\Models;

use App\Models\Concerns\Loggable;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /** Le HTML de l'editeur est nettoye des l'enregistrement (et encore a l'affichage). */
    protected function content(): Attribute
    {
        return Attribute::set(fn (?string $value) => HtmlSanitizer::clean($value));
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
