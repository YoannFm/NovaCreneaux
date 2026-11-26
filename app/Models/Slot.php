<?php
namespace App\Models;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
class Slot extends Model {
    // Supprime : garde en base pour l'historique de ses reservations passees, mais plus reservable
    use HasFactory, Loggable, SoftDeletes;

    protected const ACTIVITY_NOUN = 'Créneau';
    protected const ACTIVITY_FEMININE = false;

    protected function activityContext(): string
    {
        return $this->resource?->name . ' - ' . $this->name;
    }

    protected $fillable = ['resource_id','name','start_time','end_time','max_capacity','is_active'];

    /** Regles d'un creneau (formulaire et import de configuration) ; $prefix pour un tableau, ex. "slots.*.". */
    public static function rules(string $prefix = ''): array {
        return [
            "{$prefix}name" => 'required|string|max:50',
            "{$prefix}start_time" => 'required|date_format:H:i',
            "{$prefix}end_time" => "required|date_format:H:i|after:{$prefix}start_time",
            "{$prefix}max_capacity" => 'required|integer|min:1|max:500',
            "{$prefix}is_active" => 'boolean',
        ];
    }
    protected function casts(): array { return ['is_active'=>'boolean','max_capacity'=>'integer']; }
    /** Regle de validation d'un slot_id envoye pour reserver : un creneau supprime n'existe plus. */
    public static function existsRule(): Exists {
        return Rule::exists('slots', 'id')->whereNull('deleted_at');
    }
    /** La ressource meme supprimee : une reservation passee garde son nom. */
    public function resource() { return $this->belongsTo(Resource::class)->withTrashed(); }
    /** Peut recevoir une nouvelle reservation (actif et non supprime). */
    public function isOpenForBooking(): bool { return $this->is_active && !$this->trashed(); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function availableCountFor(string $date): int {
        $taken = $this->reservations()->where('reservation_date',$date)->count();
        return max(0, $this->max_capacity - $taken);
    }
}
