<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the primary key before modifying the column
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropPrimary('model_has_roles_role_model_type_primary');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable()->change();
        });

        // Re-create the primary key with null workspace_id allowed
        Schema::table('model_has_roles', function (Blueprint $table) {
            // MySQL doesn't allow nullable columns in primary keys; use a unique index instead
            $table->unique(['workspace_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });
    }

    public function down(): void
    {
        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropUnique('model_has_roles_role_model_type_primary');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('workspace_id')->nullable(false)->change();
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->primary(['workspace_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_role_model_type_primary');
        });
    }
};
