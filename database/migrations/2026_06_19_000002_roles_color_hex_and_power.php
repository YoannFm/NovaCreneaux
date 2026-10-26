<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $map = [
        'primary'   => '#0d6efd',
        'secondary' => '#6c757d',
        'success'   => '#198754',
        'warning'   => '#ffc107',
        'danger'    => '#dc3545',
        'info'      => '#0dcaf0',
    ];

    public function up(): void {
        // Convert existing Bootstrap class names to hex
        foreach ($this->map as $class => $hex) {
            DB::table('roles')->where('color', $class)->update(['color' => $hex]);
        }

        // Add power column for sorting (highest power = highest in list)
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedSmallInteger('power')->default(0)->after('locked');
        });

        // Default power = id so existing order is preserved
        foreach (DB::table('roles')->orderBy('id')->get() as $role) {
            DB::table('roles')->where('id', $role->id)->update(['power' => $role->id]);
        }
    }

    public function down(): void {
        foreach ($this->map as $class => $hex) {
            DB::table('roles')->where('color', $hex)->update(['color' => $class]);
        }
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('power');
        });
    }
};
