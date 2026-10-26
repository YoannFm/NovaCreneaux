<?php
namespace Database\Seeders;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder {
    public function run(): void {
        User::create([
            'name'=>'Admin','email'=>'admin@example.com',
            'password'=>Hash::make('password'),'is_admin'=>true,'is_active'=>true,
        ]);
        $defaults = [
            'app_name'=>'NovaCreneaux','registration_open'=>'0',
            'max_reservations_per_day'=>'1','maintenance_mode'=>'0',
            'maintenance_message'=>'Le service est temporairement indisponible.',
        ];
        foreach ($defaults as $key => $value) Setting::create(['key'=>$key,'value'=>$value]);
    }
}
