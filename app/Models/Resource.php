<?php
namespace App\Models;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Resource extends Model {
    use HasFactory, Loggable;

    protected const ACTIVITY_NOUN = 'Ressource';
    protected const ACTIVITY_FEMININE = true;

    protected function activityContext(): string
    {
        return $this->name;
    }

    protected $fillable = ['name','description','is_active','open_time','close_time','closed_days'];
    protected function casts(): array {
        return ['is_active'=>'boolean','closed_days'=>'array'];
    }
    public function slots() { return $this->hasMany(Slot::class); }
    public function activeSlots() { return $this->hasMany(Slot::class)->where('is_active',true); }
    public function isOpenOn(string $date): bool {
        $dow = (int) date('N', strtotime($date));
        return !in_array($dow, $this->closed_days ?? []);
    }
    /**
     * Fenetre de reservation : le jour meme jusqu'a close_time, et chaque jour a open_time une journee de plus
     * s'ouvre, jusqu'a $advanceDays jours a l'avance (limite du role). Avec 1 jour : demain s'ouvre aujourd'hui a open_time.
     */
    public function canReserveFor(string $date, int $advanceDays): array {
        $today = now()->toDateString();
        $now   = now()->format('H:i:s');

        // Refuser toute date passée
        if ($date < $today) return ['allowed'=>false,'code'=>'date_past','reason'=>'Cette date est passée.'];

        // Vérifier que la ressource est ouverte ce jour
        if (!$this->isOpenOn($date)) return ['allowed'=>false,'code'=>'resource_closed_day','reason'=>'Ressource fermée ce jour.'];

        // Aujourd'hui : vérifier que l'heure actuelle est avant la fermeture
        if ($date === $today) {
            if ($now >= $this->close_time) return ['allowed'=>false,'code'=>'resource_closed_today','reason'=>'Réservations fermées pour aujourd\'hui.'];
            return ['allowed'=>true];
        }

        if ($date <= $this->lastBookableDate($advanceDays)) return ['allowed'=>true];

        // Le prochain jour a s'ouvrir : il ouvre aujourd'hui a open_time
        if ($date === now()->addDays($advanceDays)->toDateString()) {
            return ['allowed'=>false,'code'=>'advance_limit','reason'=>'Ouverture des réservations aujourd\'hui à ' . substr($this->open_time, 0, 5) . '.'];
        }

        return ['allowed'=>false,'code'=>'advance_limit','reason'=>"Vous ne pouvez pas réserver plus de {$advanceDays} jour(s) à l'avance."];
    }

    /** Jour le plus lointain reservable maintenant : celui a $advanceDays jours ne s'ouvre qu'a open_time. */
    public function lastBookableDate(int $advanceDays): string {
        $opened = now()->format('H:i:s') >= $this->open_time;

        return now()->addDays($opened ? $advanceDays : $advanceDays - 1)->toDateString();
    }
}
