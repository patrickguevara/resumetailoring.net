<?php

namespace App\Exceptions;

use App\Enums\UsageFeature;
use Exception;

class UsageLimitExceededException extends Exception
{
    public function __construct(
        public readonly UsageFeature $feature,
        public readonly ?int $limit = null
    ) {
        parent::__construct("Free limit reached for {$feature->label()}");
    }

    public static function forFeature(UsageFeature $feature, ?int $limit = null): self
    {
        return new self($feature, $limit);
    }

    public function userMessage(): string
    {
        $plan = config('billing.plan.name', 'Tailor Pro');

        $prefix = $this->limit !== null && $this->limit > 0
            ? "You've used your {$this->limit} free {$this->feature->label()}."
            : 'This action is reserved for paid members.';

        return trim(sprintf(
            '%s Upgrade to %s for unlimited access.',
            $prefix,
            $plan
        ));
    }

    /**
     * @return array{feature: string, limit: int|null}
     */
    public function context(): array
    {
        return [
            'feature' => $this->feature->value,
            'limit' => $this->limit,
        ];
    }
}
