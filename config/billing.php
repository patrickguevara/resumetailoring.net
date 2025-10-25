<?php

use App\Enums\UsageFeature;

return [
    'plan' => [
        'name' => 'Tailor Pro',
        'amount' => 1000,
        'currency' => 'usd',
        'interval' => 'month',
        'price_id' => env('STRIPE_PRO_MONTHLY_PRICE_ID'),
        'description' => 'Unlimited resume uploads, evaluations, tailoring, and company research.',
        'features' => [
            'Unlimited resume uploads',
            'Unlimited evaluations',
            'Unlimited tailored resumes',
            'Unlimited company research',
        ],
    ],
    'free_tier' => [
        'label' => 'Free preview',
        'limits' => [
            UsageFeature::ResumeUpload->value => 1,
            UsageFeature::Evaluation->value => 2,
            UsageFeature::Tailoring->value => 2,
            UsageFeature::CompanyResearch->value => 0,
        ],
        'helper' => 'Try the full flow once for free, then upgrade when you are ready.',
    ],
];
