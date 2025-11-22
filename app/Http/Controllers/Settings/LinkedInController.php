<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LinkedInController extends Controller
{
    /**
     * Show LinkedIn settings page
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        $linkedInAccount = $user->linkedInAccount();

        return Inertia::render('settings/LinkedIn', [
            'linkedInAccount' => $linkedInAccount ? [
                'name' => $linkedInAccount->name,
                'email' => $linkedInAccount->email,
                'avatar' => $linkedInAccount->avatar,
                'connected_at' => $linkedInAccount->created_at->format('F j, Y'),
            ] : null,
            'hasPassword' => ! is_null($user->password),
        ]);
    }

    /**
     * Disconnect LinkedIn account
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Check if user has a password set
        if (is_null($user->password)) {
            return redirect()->route('linkedin.settings')
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'You must set a password before disconnecting LinkedIn. This ensures you can still access your account.',
                ]);
        }

        $linkedInAccount = $user->linkedInAccount();

        if (! $linkedInAccount) {
            return redirect()->route('linkedin.settings')
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'No LinkedIn account is connected.',
                ]);
        }

        $linkedInAccount->delete();

        return redirect()->route('linkedin.settings')
            ->with('flash', [
                'type' => 'success',
                'message' => 'LinkedIn account disconnected successfully.',
            ]);
    }
}
