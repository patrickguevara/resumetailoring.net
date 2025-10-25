<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\UsageMeter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();
        $usageMeter = app(UsageMeter::class);

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'billing' => [
                'plan' => $this->planPayload(),
                'free_tier' => $this->freeTierPayload(),
                'subscription' => $this->subscriptionPayload($user),
                'usage' => $user ? $usageMeter->summary($user) : null,
            ],
            'flash' => fn () => $request->session()->get('flash'),
            'usageLimit' => fn () => $request->session()->get('usageLimit'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function planPayload(): array
    {
        $plan = config('billing.plan', []);

        return [
            'name' => data_get($plan, 'name'),
            'amount' => data_get($plan, 'amount'),
            'currency' => data_get($plan, 'currency', 'usd'),
            'interval' => data_get($plan, 'interval', 'month'),
            'description' => data_get($plan, 'description'),
            'features' => data_get($plan, 'features', []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function freeTierPayload(): array
    {
        $free = config('billing.free_tier', []);

        return [
            'label' => data_get($free, 'label'),
            'helper' => data_get($free, 'helper'),
            'limits' => data_get($free, 'limits', []),
        ];
    }

    protected function subscriptionPayload(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        $subscription = $user->subscription('default');

        if ($subscription === null) {
            return null;
        }

        $stripe = $subscription->asStripeSubscription();

        $renewsAt = null;

        if ($stripe && isset($stripe->current_period_end)) {
            $renewsAt = Carbon::createFromTimestamp($stripe->current_period_end)->toIso8601String();
        }

        return [
            'status' => $subscription->stripe_status,
            'active' => $subscription->active(),
            'on_grace_period' => $subscription->onGracePeriod(),
            'renews_at' => $renewsAt,
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
        ];
    }
}
