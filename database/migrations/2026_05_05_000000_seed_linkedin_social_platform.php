<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('social_platforms')->insertOrIgnore([
            'name' => 'LinkedIn',
            'slug' => 'linkedin',
            'icon' => 'linkedin',
            'color' => '#0A66C2',
            'is_active' => true,
            'supports_scheduling' => true,
            'supports_media' => true,
            'character_limit' => 3000,
            'connection_options' => json_encode([
                'oauth_flow' => 'oauth2',
                'supports_pages' => true,
                'supports_personal' => true,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('social_platforms')->where('slug', 'linkedin')->delete();
    }
};
