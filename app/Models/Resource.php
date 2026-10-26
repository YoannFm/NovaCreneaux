<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Resource extends Model {
    use HasFactory;
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
    public function canReserveFor(string $date): array {
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

        // Dates futures : autorisé dès que la ressource est ouverte ce jour-là
        return ['allowed'=>true];
    }
}
