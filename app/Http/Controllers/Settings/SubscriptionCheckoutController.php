<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionCheckoutController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user->subscribed('default')) {
            return to_route('billing.edit')->with('flash', [
                'type' => 'info',
                'message' => 'You already have an active subscription.',
            ]);
        }

        $priceId = (string) config('billing.plan.price_id');

        if (blank($priceId)) {
            return to_route('billing.edit')->with('flash', [
                'type' => 'error',
                'message' => 'Plan price is not configured yet. Please reach out to support.',
            ]);
        }

        $successUrl = route('billing.edit', ['checkout' => 'success']);
        $separator = str_contains($successUrl, '?') ? '&' : '?';
        $successUrl .= $separator.'session_id={CHECKOUT_SESSION_ID}';

        $checkout = $user->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => $successUrl,
                'cancel_url' => route('billing.edit', ['checkout' => 'cancelled']),
            ]);

        return Inertia::location($checkout->url);
    }
}
