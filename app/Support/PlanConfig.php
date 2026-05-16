<?php

namespace App\Support;

final class PlanConfig
{
    public static function all(): array
    {
        return [
            'free' => [
                'name'          => 'Free',
                'price_pkr'     => 0,
                'posts'         => 5,
                'accounts'      => 2,
                'members'       => 1,
                'analytics'     => false,
                'export'        => false,
                'support'       => 'community',
                'duration_days' => null,
                'features'      => [
                    '5 scheduled posts/month',
                    '2 social accounts',
                    '1 workspace member',
                    'Basic analytics',
                ],
            ],
            'starter' => [
                'name'          => 'Starter',
                'price_pkr'     => 2900,
                'posts'         => 50,
                'accounts'      => 5,
                'members'       => 3,
                'analytics'     => true,
                'export'        => false,
                'support'       => 'email',
                'duration_days' => 30,
                'features'      => [
                    '50 scheduled posts/month',
                    '5 social accounts',
                    '3 workspace members',
                    'Analytics dashboard',
                    'Email support',
                ],
            ],
            'pro' => [
                'name'          => 'Pro',
                'price_pkr'     => 7900,
                'posts'         => -1,
                'accounts'      => 10,
                'members'       => 10,
                'analytics'     => true,
                'export'        => true,
                'support'       => 'priority',
                'duration_days' => 30,
                'features'      => [
                    'Unlimited scheduled posts',
                    '10 social accounts',
                    '10 workspace members',
                    'Analytics + Export',
                    'Priority support',
                    'Custom hashtag groups',
                ],
            ],
            'enterprise' => [
                'name'          => 'Enterprise',
                'price_pkr'     => 0,
                'posts'         => -1,
                'accounts'      => -1,
                'members'       => -1,
                'analytics'     => true,
                'export'        => true,
                'support'       => 'dedicated',
                'duration_days' => 365,
                'features'      => [
                    'Unlimited everything',
                    'Unlimited social accounts',
                    'Unlimited workspace members',
                    'Advanced analytics + Export',
                    'Dedicated account manager',
                    'Custom integrations',
                    'SLA guarantee',
                ],
            ],
        ];
    }

    public static function get(string $plan): array
    {
        return self::all()[$plan] ?? self::all()['free'];
    }

    public static function names(): array
    {
        return array_keys(self::all());
    }
}
