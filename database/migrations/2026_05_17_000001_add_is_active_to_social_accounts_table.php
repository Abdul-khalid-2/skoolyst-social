<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_connected');
        });

        // Ensure all existing connected accounts remain active
        DB::table('social_accounts')
            ->where('is_connected', true)
            ->update(['is_active' => true]);
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
