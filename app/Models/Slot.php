<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Slot extends Model {
    use HasFactory;
    protected $fillable = ['resource_id','name','start_time','end_time','max_capacity','is_active'];
    protected function casts(): array { return ['is_active'=>'boolean','max_capacity'=>'integer']; }
    public function resource() { return $this->belongsTo(Resource::class); }
    public function reservations() { return $this->hasMany(Reservation::class); }
    public function availableCountFor(string $date): int {
        $taken = $this->reservations()->where('reservation_date',$date)->count();
        return max(0, $this->max_capacity - $taken);
    }
}
