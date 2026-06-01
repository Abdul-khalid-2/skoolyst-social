<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY avatar TEXT NULL');
        DB::statement('ALTER TABLE social_accounts MODIFY avatar TEXT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY avatar VARCHAR(255) NULL');
        DB::statement('ALTER TABLE social_accounts MODIFY avatar VARCHAR(255) NULL');
    }
};
