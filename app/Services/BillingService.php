<?php

namespace App\Services;

use App\Mail\PlanChangedMail;
use App\Mail\SubscriptionCancelledMail;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PlanConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BillingService
{
    public function changePlan(User $user, Workspace $workspace, string $newPlan): Subscription
    {
        abort_unless(in_array($newPlan, PlanConfig::names()), 422, 'Invalid plan.');

        $config = PlanConfig::get($newPlan);

        return DB::transaction(function () use ($user, $workspace, $newPlan, $config) {
            Subscription::where('workspace_id', $workspace->id)
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $expiresAt = $config['duration_days']
                ? Carbon::now()->addDays($config['duration_days'])
                : null;

            $subscription = Subscription::create([
                'user_id'      => $user->id,
                'workspace_id' => $workspace->id,
                'plan'         => $newPlan,
                'status'       => 'active',
                'started_at'   => now(),
                'expires_at'   => $expiresAt,
            ]);

            $workspace->plan = $newPlan;
            $workspace->save();

            if ($config['price_pkr'] > 0) {
                PaymentTransaction::create([
                    'user_id'                => $user->id,
                    'workspace_id'           => $workspace->id,
                    'subscription_id'        => $subscription->id,
                    'amount'                 => $config['price_pkr'],
                    'currency'               => 'PKR',
                    'status'                 => 'success',
                    'gateway'                => 'manual',
                    'gateway_transaction_id' => 'BYPASS-' . strtoupper(uniqid()),
                    'meta'                   => ['note' => 'Payment bypassed — gateway not yet integrated.'],
                ]);
            }

            try {
                Mail::to($user->email)->send(new PlanChangedMail($user, $workspace, $subscription, $config));
            } catch (\Throwable) {
            }

            return $subscription;
        });
    }

    public function cancelSubscription(User $user, Workspace $workspace): void
    {
        $subscription = Subscription::where('workspace_id', $workspace->id)
            ->whereIn('status', ['active', 'trialing'])
            ->latest('started_at')
            ->firstOrFail();

        DB::transaction(function () use ($user, $workspace, $subscription) {
            $subscription->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $workspace->plan = 'free';
            $workspace->save();

            try {
                Mail::to($user->email)->send(new SubscriptionCancelledMail($user, $workspace, $subscription));
            } catch (\Throwable) {
            }
        });
    }

    public function billingHistory(Workspace $workspace): \Illuminate\Database\Eloquent\Collection
    {
        return PaymentTransaction::where('workspace_id', $workspace->id)
            ->with('subscription')
            ->latest()
            ->limit(50)
            ->get();
    }
}
