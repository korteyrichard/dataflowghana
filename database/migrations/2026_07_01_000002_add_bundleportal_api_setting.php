<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        Setting::set('bundleportal_api_enabled', '0');
    }

    public function down(): void
    {
        Setting::where('key', 'bundleportal_api_enabled')->delete();
    }
};
