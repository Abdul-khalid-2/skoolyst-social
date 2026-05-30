<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_targets', function (Blueprint $table) {
            if (! Schema::hasColumn('post_targets', 'likes_count')) {
                $table->unsignedInteger('likes_count')->nullable()->after('platform_post_id');
            }
            if (! Schema::hasColumn('post_targets', 'comments_count')) {
                $table->unsignedInteger('comments_count')->nullable()->after('likes_count');
            }
            if (! Schema::hasColumn('post_targets', 'shares_count')) {
                $table->unsignedInteger('shares_count')->nullable()->after('comments_count');
            }
            if (! Schema::hasColumn('post_targets', 'reactions_count')) {
                $table->unsignedInteger('reactions_count')->nullable()->after('shares_count');
            }
            if (! Schema::hasColumn('post_targets', 'stats_synced_at')) {
                $table->timestamp('stats_synced_at')->nullable()->after('reactions_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('post_targets', function (Blueprint $table) {
            foreach (['stats_synced_at', 'reactions_count', 'shares_count', 'comments_count', 'likes_count'] as $col) {
                if (Schema::hasColumn('post_targets', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
