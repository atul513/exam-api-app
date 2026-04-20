<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'          => 'Free',
                'slug'          => 'free',
                'description'   => 'Get started with basic access to quizzes and practice sets.',
                'price'         => 0.00,
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'features'      => [
                    'Access to free quizzes',
                    'Limited practice sets (3/day)',
                    'Basic performance reports',
                    'Community support',
                ],
                'sort_order'    => 1,
                'is_active'     => true,
                'is_featured'   => false,
            ],
            [
                'name'          => 'Monthly Pro',
                'slug'          => 'monthly-pro',
                'description'   => 'Unlock all quizzes and exams with monthly flexibility.',
                'price'         => 499.00,
                'billing_cycle' => 'monthly',
                'duration_days' => 30,
                'features'      => [
                    'Unlimited quizzes & exams',
                    'Unlimited practice sets',
                    'Detailed PDF reports',
                    'Leaderboard access',
                    'Download results',
                    'Email support',
                ],
                'sort_order'    => 2,
                'is_active'     => true,
                'is_featured'   => false,
            ],
            [
                'name'          => 'Yearly Pro',
                'slug'          => 'yearly-pro',
                'description'   => 'Best value — 2 months free compared to monthly plan.',
                'price'         => 4999.00,
                'billing_cycle' => 'yearly',
                'duration_days' => 365,
                'features'      => [
                    'Everything in Monthly Pro',
                    'Priority email support',
                    'Early access to new exams',
                    'Downloadable question banks',
                    'Save 2 months vs monthly',
                ],
                'sort_order'    => 3,
                'is_active'     => true,
                'is_featured'   => true,
            ],
            [
                'name'          => 'Lifetime',
                'slug'          => 'lifetime',
                'description'   => 'One-time payment. Learn at your own pace, forever.',
                'price'         => 14999.00,
                'billing_cycle' => 'lifetime',
                'duration_days' => 36500,
                'features'      => [
                    'Everything in Yearly Pro',
                    'Lifetime access',
                    'All future content included',
                    'Dedicated onboarding',
                    'Premium support',
                ],
                'sort_order'    => 4,
                'is_active'     => true,
                'is_featured'   => false,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
