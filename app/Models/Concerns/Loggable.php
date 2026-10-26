<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;

/**
 * Inscrit la creation, la modification et la suppression du modele dans le journal d'activite.
 * Le modele declare ACTIVITY_NOUN, ACTIVITY_FEMININE (accord du participe) et activityContext().
 * Les suppressions faites par la base (cascade) ne passent pas par Eloquent et ne sont pas journalisees.
 */
trait Loggable
{
    protected static function bootLoggable(): void
    {
        foreach (['created' => 'créé', 'updated' => 'modifié', 'deleted' => 'supprimé'] as $event => $verb) {
            static::$event(function (self $model) use ($verb) {
                $feminine = static::ACTIVITY_FEMININE ? 'e' : '';
                ActivityLogger::log(static::ACTIVITY_NOUN . " {$verb}{$feminine}", $model->activityContext());
            });
        }
    }

    abstract protected function activityContext(): string;
}
