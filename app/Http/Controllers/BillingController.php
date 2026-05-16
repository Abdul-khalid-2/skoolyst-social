<?php

namespace App\Http\Controllers;

use App\Services\BillingService;
use App\Services\WorkspaceSettingsService;
use App\Support\PlanConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingService           $billing,
        private readonly WorkspaceSettingsService $workspaceSettings,
    ) {}

    public function changePlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:' . implode(',', PlanConfig::names())],
        ]);

        $user      = $request->user();
        $workspace = $this->workspaceSettings->getCurrentWorkspace($user);

        abort_if(! $workspace, 404);
        abort_unless($user->can('workspace.billing.manage'), 403, 'You do not have permission to manage billing.');

        $currentPlan = $workspace->plan ?? 'free';
        if ($currentPlan === $validated['plan']) {
            return redirect()->route('settings.tab', 'billing')
                ->with('info', 'You are already on the ' . ucfirst($validated['plan']) . ' plan.');
        }

        $this->billing->changePlan($user, $workspace, $validated['plan']);

        return redirect()->route('settings.tab', 'billing')
            ->with('success', 'Plan changed to ' . ucfirst($validated['plan']) . '. Confirmation email sent.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $user      = $request->user();
        $workspace = $this->workspaceSettings->getCurrentWorkspace($user);

        abort_if(! $workspace, 404);
        abort_unless($user->can('workspace.billing.manage'), 403);

        $this->billing->cancelSubscription($user, $workspace);

        return redirect()->route('settings.tab', 'billing')
            ->with('success', 'Subscription cancelled. You have been moved to the Free plan.');
    }
}
