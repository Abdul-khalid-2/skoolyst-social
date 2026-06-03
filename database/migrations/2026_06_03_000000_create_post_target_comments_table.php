<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_target_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_target_id')->constrained()->cascadeOnDelete();
            $table->string('platform_comment_id');
            $table->foreignId('parent_id')->nullable()->constrained('post_target_comments')->cascadeOnDelete();
            $table->string('author_name');
            $table->text('message');
            $table->timestamp('platform_created_at')->nullable();
            $table->timestamps();

            $table->unique(['post_target_id', 'platform_comment_id']);
            $table->index('post_target_id');
            $table->index('parent_id');
        });

        Schema::table('post_targets', function (Blueprint $table) {
            if (! Schema::hasColumn('post_targets', 'comments_synced_at')) {
                $table->timestamp('comments_synced_at')->nullable()->after('stats_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('post_targets', function (Blueprint $table) {
            if (Schema::hasColumn('post_targets', 'comments_synced_at')) {
                $table->dropColumn('comments_synced_at');
            }
        });

        Schema::dropIfExists('post_target_comments');
    }
};
