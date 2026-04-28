<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'content')) {
                $table->text('content')->nullable()->after('caption');
            }
            if (! Schema::hasColumn('posts', 'image_url')) {
                $table->string('image_url')->nullable()->after('content');
            }
            if (! Schema::hasColumn('posts', 'link_url')) {
                $table->string('link_url')->nullable()->after('image_url');
            }
            if (! Schema::hasColumn('posts', 'platforms')) {
                $table->json('platforms')->nullable()->after('link_url');
            }
            if (! Schema::hasColumn('posts', 'fb_post_id')) {
                $table->string('fb_post_id')->nullable()->after('platforms');
            }
            if (! Schema::hasColumn('posts', 'ig_post_id')) {
                $table->string('ig_post_id')->nullable()->after('fb_post_id');
            }
            if (! Schema::hasColumn('posts', 'fb_error')) {
                $table->text('fb_error')->nullable()->after('ig_post_id');
            }
            if (! Schema::hasColumn('posts', 'ig_error')) {
                $table->text('ig_error')->nullable()->after('fb_error');
            }
        });

        DB::statement("ALTER TABLE posts MODIFY status ENUM('draft','published','partial','failed','scheduled','publishing') NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE posts MODIFY status ENUM('draft','scheduled','publishing','published','failed') NOT NULL DEFAULT 'draft'");

        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'ig_error')) {
                $table->dropColumn('ig_error');
            }
            if (Schema::hasColumn('posts', 'fb_error')) {
                $table->dropColumn('fb_error');
            }
            if (Schema::hasColumn('posts', 'ig_post_id')) {
                $table->dropColumn('ig_post_id');
            }
            if (Schema::hasColumn('posts', 'fb_post_id')) {
                $table->dropColumn('fb_post_id');
            }
            if (Schema::hasColumn('posts', 'platforms')) {
                $table->dropColumn('platforms');
            }
            if (Schema::hasColumn('posts', 'link_url')) {
                $table->dropColumn('link_url');
            }
            if (Schema::hasColumn('posts', 'image_url')) {
                $table->dropColumn('image_url');
            }
            if (Schema::hasColumn('posts', 'content')) {
                $table->dropColumn('content');
            }
        });
    }
};

