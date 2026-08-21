<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['key' => 'datamasta_order_pusher_enabled'],
            ['value' => '0']
        );
    }

    public function down(): void
    {
        Setting::where('key', 'datamasta_order_pusher_enabled')->delete();
    }
};
