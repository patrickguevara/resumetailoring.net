<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionCheckoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
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

        return $user->newSubscription('default', $priceId)->checkout([
            'success_url' => route('billing.edit', ['checkout' => 'success']),
            'cancel_url' => route('billing.edit', ['checkout' => 'cancelled']),
        ]);
    }
}

