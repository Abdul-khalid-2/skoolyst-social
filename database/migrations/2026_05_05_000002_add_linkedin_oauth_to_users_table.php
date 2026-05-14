<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'linkedin_id')) {
                $table->string('linkedin_id')->nullable()->unique()->after('facebook_id');
            }
            if (! Schema::hasColumn('users', 'linkedin_access_token')) {
                $table->text('linkedin_access_token')->nullable()->after('linkedin_id');
            }
            if (! Schema::hasColumn('users', 'linkedin_refresh_token')) {
                $table->text('linkedin_refresh_token')->nullable()->after('linkedin_access_token');
            }
            if (! Schema::hasColumn('users', 'linkedin_token_expires_at')) {
                $table->timestamp('linkedin_token_expires_at')->nullable()->after('linkedin_refresh_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'linkedin_token_expires_at')) {
                $table->dropColumn('linkedin_token_expires_at');
            }
            if (Schema::hasColumn('users', 'linkedin_refresh_token')) {
                $table->dropColumn('linkedin_refresh_token');
            }
            if (Schema::hasColumn('users', 'linkedin_access_token')) {
                $table->dropColumn('linkedin_access_token');
            }
            if (Schema::hasColumn('users', 'linkedin_id')) {
                $table->dropColumn('linkedin_id');
            }
        });
    }
};
