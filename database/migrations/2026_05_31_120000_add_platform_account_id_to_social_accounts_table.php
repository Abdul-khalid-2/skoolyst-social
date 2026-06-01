<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->string('platform_account_id', 255)->nullable()->after('social_platform_id');
        });

        DB::table('social_accounts')->orderBy('id')->lazyById()->each(function (object $row): void {
            $platformAccountId = $this->resolvePlatformAccountId($row);

            DB::table('social_accounts')
                ->where('id', $row->id)
                ->update(['platform_account_id' => $platformAccountId]);
        });

        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->unique(
                ['workspace_id', 'social_platform_id', 'platform_account_id'],
                'social_accounts_workspace_platform_account_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->dropUnique('social_accounts_workspace_platform_account_unique');
            $table->dropColumn('platform_account_id');
        });
    }

    private function resolvePlatformAccountId(object $row): string
    {
        $pageId = trim((string) ($row->platform_page_id ?? ''));
        if ($pageId !== '') {
            return $pageId;
        }

        $userId = trim((string) ($row->platform_user_id ?? ''));
        if ($userId !== '') {
            return 'member:'.$userId;
        }

        $meta = json_decode((string) ($row->meta ?? ''), true);
        if (is_array($meta)) {
            $memberUrn = trim((string) ($meta['li_member_id'] ?? ''));
            if ($memberUrn !== '') {
                return $memberUrn;
            }
        }

        return 'legacy:'.$row->id;
    }
};
