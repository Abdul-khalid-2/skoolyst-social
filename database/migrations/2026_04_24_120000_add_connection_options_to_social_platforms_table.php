<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_platforms', function (Blueprint $table) {
            if (! Schema::hasColumn('social_platforms', 'connection_options')) {
                $table->json('connection_options')->nullable()->after('character_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('social_platforms', function (Blueprint $table) {
            if (Schema::hasColumn('social_platforms', 'connection_options')) {
                $table->dropColumn('connection_options');
            }
        });
    }
};
