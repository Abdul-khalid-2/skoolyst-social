<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('social_platform_id')->constrained()->cascadeOnDelete();
            $table->string('platform_page_id')->nullable();
            $table->string('platform_user_id')->nullable();
            $table->string('account_name');
            $table->string('account_handle')->nullable();
            $table->string('avatar')->nullable();
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->unsignedInteger('followers_count')->default(0);
            $table->boolean('is_connected')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('workspace_id');
            $table->index('social_platform_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
