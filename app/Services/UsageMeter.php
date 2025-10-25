<?php

namespace App\Services;

use App\Enums\UsageFeature;
use App\Exceptions\UsageLimitExceededException;
use App\Models\FeatureUsage;
use App\Models\User;

class UsageMeter
{
    /**
     * @var array<string, int|null>
     */
    protected array $limits;

    public function __construct()
    {
        $this->limits = config('billing.free_tier.limits', []);
    }

    public function assertCanUse(User $user, UsageFeature $feature): void
    {
        if (! $this->canUse($user, $feature)) {
            throw UsageLimitExceededException::forFeature($feature, $this->limitFor($feature));
        }
    }

    public function canUse(User $user, UsageFeature $feature): bool
    {
        if ($this->hasUnlimitedAccess($user)) {
            return true;
        }

        $limit = $this->limitFor($feature);

        if ($limit === null) {
            return true;
        }

        return $this->usageFor($user, $feature) < $limit;
    }

    public function usageFor(User $user, UsageFeature $feature): int
    {
        return (int) FeatureUsage::query()
            ->where('user_id', $user->id)
            ->where('feature', $feature->value)
            ->value('used');
    }

    public function increment(User $user, UsageFeature $feature): void
    {
        if ($this->hasUnlimitedAccess($user)) {
            return;
        }

        $record = FeatureUsage::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'feature' => $feature->value,
            ],
            [
                'used' => 0,
                'period_started_at' => now(),
            ]
        );

        $record->increment('used', 1, [
            'last_used_at' => now(),
            'period_started_at' => $record->period_started_at ?? now(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forInertia(User $user): array
    {
        $usages = FeatureUsage::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('feature');

        $hasSubscription = $this->hasUnlimitedAccess($user);

        return collect(UsageFeature::cases())
            ->map(fn (UsageFeature $feature) => $this->formatUsage(
                $feature,
                (int) ($usages[$feature->value]->used ?? 0),
                $hasSubscription ? null : $this->limitFor($feature)
            ))
            ->values()
            ->all();
    }

    public function summary(User $user): array
    {
        $hasSubscription = $this->hasUnlimitedAccess($user);

        return [
            'has_subscription' => $hasSubscription,
            'features' => $this->forInertia($user),
        ];
    }

    protected function formatUsage(UsageFeature $feature, int $used, ?int $limit): array
    {
        return [
            'key' => $feature->value,
            'label' => $feature->shortLabel(),
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit !== null ? max($limit - $used, 0) : null,
        ];
    }

    public function hasUnlimitedAccess(User $user): bool
    {
        return (bool) $user->subscribed('default');
    }

    public function limitFor(UsageFeature $feature): ?int
    {
        $limit = $this->limits[$feature->value] ?? null;

        return $limit !== null ? (int) $limit : null;
    }
}
