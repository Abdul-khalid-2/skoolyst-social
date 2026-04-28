<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('facebook_id')->nullable()->unique()->after('email');
            $table->text('facebook_access_token')->nullable()->after('facebook_id');
            $table->timestamp('facebook_token_expires_at')->nullable()->after('facebook_access_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['facebook_id', 'facebook_access_token', 'facebook_token_expires_at']);
        });
    }
};
