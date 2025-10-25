<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingPortalController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasStripeId()) {
            return to_route('billing.edit')->with('flash', [
                'type' => 'error',
                'message' => 'We could not find a Stripe customer attached to your account yet.',
            ]);
        }

        return $user->redirectToBillingPortal(route('billing.edit'));
    }
}

