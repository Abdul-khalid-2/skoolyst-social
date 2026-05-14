<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (! Schema::hasColumn('posts', 'li_post_id')) {
                $table->string('li_post_id')->nullable()->after('ig_post_id');
            }
            if (! Schema::hasColumn('posts', 'li_error')) {
                $table->text('li_error')->nullable()->after('ig_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            if (Schema::hasColumn('posts', 'li_error')) {
                $table->dropColumn('li_error');
            }
            if (Schema::hasColumn('posts', 'li_post_id')) {
                $table->dropColumn('li_post_id');
            }
        });
    }
};
