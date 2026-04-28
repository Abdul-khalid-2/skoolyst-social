<?php

namespace App\Services;

use App\Models\SocialAccount;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Gate;

class AccountListingService
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    /**
     * @return array{workspace: ?Workspace, rows: BaseCollection}
     */
    public function getIndexData(User $user): array
    {
        $id = $this->dashboardService->resolveWorkspaceId($user);
        if ($id === null) {
            return [
                'workspace' => null,
                'rows' => collect(),
            ];
        }

        $workspace = Workspace::query()->find($id);
        if ($workspace === null) {
            return [
                'workspace' => null,
                'rows' => collect(),
            ];
        }

        $rows = $this->buildPlatformRows($workspace);

        return [
            'workspace' => $workspace,
            'rows' => $rows,
        ];
    }

    public function deleteAccount(User $user, SocialAccount $account): void
    {
        Gate::forUser($user)->authorize('disconnect', $account);
        $account->delete();
    }

    /**
     * @return BaseCollection<int, object{
     *   platform: SocialPlatform,
     *   accounts: Collection,
     *   connected: bool,
     *   status: string
     * }>
     */
    public function buildPlatformRows(Workspace $workspace): BaseCollection
    {
        $platforms = SocialPlatform::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->with(['socialAccounts' => function ($q) use ($workspace): void {
                $q->where('workspace_id', $workspace->id)
                    ->orderBy('id')
                    ->with('platform');
            }])
            ->get();

        return $platforms->map(function (SocialPlatform $platform) {
            /** @var Collection<int, SocialAccount> $accountModels */
            $accountModels = $platform->relationLoaded('socialAccounts')
                ? $platform->socialAccounts
                : new Collection;

            $connected = (bool) $accountModels
                ->first(fn (SocialAccount $a) => (bool) $a->is_connected);
            $status = $this->platformStatus($accountModels);

            return (object) [
                'platform' => $platform,
                'accounts' => $accountModels,
                'connected' => $connected,
                'status' => $status,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, SocialAccount>  $accounts
     */
    public function accountRowStatus(SocialAccount $account): string
    {
        if (! $account->is_connected) {
            return 'disconnected';
        }
        if ($account->token_expires_at?->isPast()) {
            return 'expired';
        }

        return 'connected';
    }

    public function gradientFor(SocialPlatform $platform): array
    {
        $color = (string) ($platform->color ?? '');
        if ($color !== '' && $color !== 'null') {
            return ['from' => $color, 'to' => $color];
        }
        $fallbacks = [
            'facebook' => ['from' => '#2563eb', 'to' => '#1d4ed8'],
            'instagram' => ['from' => '#a855f7', 'to' => '#ec4899'],
            'linkedin' => ['from' => '#4338ca', 'to' => '#3730a3'],
            'twitter' => ['from' => '#1f2937', 'to' => '#111827'],
        ];

        return $fallbacks[$platform->slug] ?? ['from' => '#6b7280', 'to' => '#4b5563'];
    }

    public function shortBadgeLabel(SocialPlatform $platform): string
    {
        $n = trim($platform->name);
        if ($n === '') {
            return '?';
        }
        if (str_starts_with(strtolower($n), 'twitter')) {
            return 'X';
        }
        $parts = preg_split('/\s+/', $n) ?: [];
        if (count($parts) === 1) {
            return strtoupper(substr($parts[0] ?? 'X', 0, 2));
        }

        return strtoupper(
            (string) (substr($parts[0] ?? '', 0, 1).substr($parts[1] ?? '', 0, 1))
        );
    }

    /**
     * @param  Collection<int, SocialAccount>  $accounts
     */
    private function platformStatus(Collection $accounts): string
    {
        $active = $accounts->filter(fn (SocialAccount $a) => (bool) $a->is_connected);
        if ($active->isEmpty()) {
            return 'disconnected';
        }

        $allExpired = $active->every(function (SocialAccount $a): bool {
            $t = $a->token_expires_at;
            if ($t === null) {
                return false;
            }

            return $t->isPast();
        });

        return $allExpired ? 'expired' : 'connected';
    }
}
