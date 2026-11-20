<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('maxdata_order_pusher_enabled', '0');
    }

    public function down(): void
    {
        Setting::where('key', 'maxdata_order_pusher_enabled')->delete();
    }
};
