<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make per-account stat columns nullable so the UI can distinguish
 * "unavailable" (null) from "actually zero" (0), and add a
 * `stats_synced_at` timestamp so we know when each row was last refreshed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            // Null = "stat could not be fetched"; 0 = "API confirmed zero".
            $table->unsignedInteger('followers_count')->nullable()->default(null)->change();
            $table->unsignedInteger('fan_count')->nullable()->default(null)->change();
            $table->unsignedInteger('following_count')->nullable()->default(null)->change();
            $table->unsignedInteger('posts_count')->nullable()->default(null)->change();

            $table->timestamp('stats_synced_at')->nullable()->after('posts_count');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->dropColumn('stats_synced_at');

            // Coerce any nulls back to 0 before restoring NOT NULL defaults so the
            // down migration is reversible on populated tables.
            $table->unsignedInteger('followers_count')->default(0)->nullable(false)->change();
            $table->unsignedInteger('fan_count')->default(0)->nullable(false)->change();
            $table->unsignedInteger('following_count')->default(0)->nullable(false)->change();
            $table->unsignedInteger('posts_count')->default(0)->nullable(false)->change();
        });
    }
};
