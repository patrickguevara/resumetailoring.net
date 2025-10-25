<?php

use App\Enums\UsageFeature;

return [
    'plan' => [
        'name' => 'Resume Tailor Pro',
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
            UsageFeature::ResumeUpload->value => 2,
            UsageFeature::Evaluation->value => 4,
            UsageFeature::Tailoring->value => 2,
            UsageFeature::CompanyResearch->value => 1,
        ],
        'helper' => 'Try the full flow for free, then upgrade when you are ready.',
    ],
];
