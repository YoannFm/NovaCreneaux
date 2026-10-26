<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class Reservation extends Model {
    use HasFactory;
    protected $fillable = ['user_id','slot_id','reservation_date'];
    protected function casts(): array { return ['reservation_date'=>'date:Y-m-d']; }
    public function user() { return $this->belongsTo(User::class); }
    public function slot() { return $this->belongsTo(Slot::class); }
}
