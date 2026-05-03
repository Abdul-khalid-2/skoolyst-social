<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->unsignedInteger('following_count')->default(0)->after('fan_count');
            $table->unsignedInteger('posts_count')->default(0)->after('following_count');
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->dropColumn(['following_count', 'posts_count']);
        });
    }
};
