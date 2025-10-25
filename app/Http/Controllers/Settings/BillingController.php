<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Stripe\Subscription as StripeSubscription;
use Throwable;

class BillingController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $checkoutStatus = $request->string('checkout')->toString();

        if ($user && $checkoutStatus === 'success') {
            $flash = $this->handleCheckoutSuccess($request, $user);

            return to_route('billing.edit')->with('flash', $flash);
        }

        return Inertia::render('settings/Billing', [
            'invoices' => $user ? $this->invoicesFor($user) : [],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function invoicesFor(User $user): array
    {
        if (blank(config('cashier.secret'))) {
            return [];
        }

        try {
            $invoices = $user->invoicesIncludingPending();
        } catch (Throwable $exception) {
            return [];
        }

        return collect($invoices)
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'total' => $invoice->total(),
                'status' => $invoice->status ?? null,
                'date' => $invoice->date()?->toIso8601String(),
                'receipt_url' => $invoice->hosted_invoice_url ?? null,
                'invoice_pdf' => $invoice->invoice_pdf ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function handleCheckoutSuccess(Request $request, User $user): array
    {
        $sessionId = $request->string('session_id')->toString();

        $synced = $sessionId !== ''
            ? $this->syncSubscriptionFromCheckoutSession($user, $sessionId)
            : $this->syncLatestStripeSubscription($user);

        if ($synced) {
            return [
                'type' => 'success',
                'message' => 'Subscription activated. Unlimited usage unlocked.',
            ];
        }

        return [
            'type' => 'warning',
            'message' => 'We could not immediately confirm your subscription. Stripe usually finalizes it within a minute.',
        ];
    }

    protected function syncSubscriptionFromCheckoutSession(User $user, string $sessionId): bool
    {
        if (blank($sessionId) || blank($user->stripe_id)) {
            return false;
        }

        try {
            $stripe = $user->stripe();
            $session = $stripe->checkout->sessions->retrieve($sessionId, []);
        } catch (ApiErrorException|Throwable $exception) {
            report($exception);

            return false;
        }

        if (! $session || ! $session->subscription) {
            return false;
        }

        try {
            $stripeSubscription = $stripe->subscriptions->retrieve($session->subscription, []);
        } catch (ApiErrorException|Throwable $exception) {
            report($exception);

            return false;
        }

        $this->storeStripeSubscription($user, $stripeSubscription);

        return true;
    }

    protected function syncLatestStripeSubscription(User $user): bool
    {
        if (blank($user->stripe_id)) {
            return false;
        }

        try {
            $stripe = $user->stripe();
            $subscriptions = $stripe->subscriptions->all([
                'customer' => $user->stripe_id,
                'status' => 'all',
                'limit' => 1,
            ]);
        } catch (ApiErrorException|Throwable $exception) {
            report($exception);

            return false;
        }

        $stripeSubscription = $subscriptions->data[0] ?? null;

        if (! $stripeSubscription) {
            return false;
        }

        $this->storeStripeSubscription($user, $stripeSubscription);

        return true;
    }

    protected function storeStripeSubscription(User $user, StripeSubscription $stripeSubscription): void
    {
        $items = $stripeSubscription->items->data ?? [];
        $firstItem = $items[0] ?? null;
        $isSinglePrice = count($items) === 1 && $firstItem !== null;

        $subscription = $user->subscriptions()->updateOrCreate([
            'stripe_id' => $stripeSubscription->id,
        ], [
            'type' => $stripeSubscription->metadata['type'] ?? $stripeSubscription->metadata['name'] ?? 'default',
            'stripe_status' => $stripeSubscription->status,
            'stripe_price' => $isSinglePrice ? $firstItem->price->id : null,
            'quantity' => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
            'trial_ends_at' => isset($stripeSubscription->trial_end)
                ? Carbon::createFromTimestamp($stripeSubscription->trial_end)
                : null,
            'ends_at' => null,
        ]);

        foreach ($items as $item) {
            $subscription->items()->updateOrCreate([
                'stripe_id' => $item->id,
            ], [
                'stripe_product' => $item->price->product,
                'stripe_price' => $item->price->id,
                'quantity' => $item->quantity ?? null,
            ]);
        }
    }
}
