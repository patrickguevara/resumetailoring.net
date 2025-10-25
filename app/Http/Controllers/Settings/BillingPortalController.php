<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class BillingPortalController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if (! $user->hasStripeId()) {
            return to_route('billing.edit')->with('flash', [
                'type' => 'error',
                'message' => 'We could not find a Stripe customer attached to your account yet.',
            ]);
        }

        $portalUrl = $user->billingPortalUrl(route('billing.edit'));

        return Inertia::location($portalUrl);
    }
}
