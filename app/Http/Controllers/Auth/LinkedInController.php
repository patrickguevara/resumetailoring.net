<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class LinkedInController extends Controller
{
    /**
     * Redirect to LinkedIn OAuth page
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('linkedin-openid')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    /**
     * Handle LinkedIn OAuth callback
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $linkedInUser = Socialite::driver('linkedin-openid')->user();

            if (! $linkedInUser->getEmail()) {
                return redirect()->route('login')
                    ->with('flash', [
                        'type' => 'error',
                        'message' => 'Email permission is required to log in with LinkedIn.',
                    ]);
            }

            $currentUser = $request->user();

            return DB::transaction(function () use ($linkedInUser, $currentUser) {
                // Check if social account already exists
                $socialAccount = SocialAccount::where('provider', 'linkedin-openid')
                    ->where('provider_id', $linkedInUser->getId())
                    ->first();

                // Check if user is already logged in (manual linking from settings)
                if ($currentUser) {
                    $user = $currentUser;

                    // If this LinkedIn account is already linked to this user, just redirect
                    if ($socialAccount && $socialAccount->user_id === $user->id) {
                        $this->updateSocialAccountData($socialAccount, $linkedInUser);

                        return redirect()->route('linkedin.settings')
                            ->with('flash', [
                                'type' => 'success',
                                'message' => 'Your LinkedIn account is already connected.',
                            ]);
                    }

                    // If this LinkedIn account is linked to a different user, show error
                    if ($socialAccount && $socialAccount->user_id !== $user->id) {
                        return redirect()->route('linkedin.settings')
                            ->with('flash', [
                                'type' => 'error',
                                'message' => 'This LinkedIn account is already linked to another user.',
                            ]);
                    }

                    // Check if this user already has a different LinkedIn account linked
                    if ($user->hasLinkedLinkedIn()) {
                        return redirect()->route('linkedin.settings')
                            ->with('flash', [
                                'type' => 'error',
                                'message' => 'You already have a LinkedIn account linked.',
                            ]);
                    }

                    $this->createSocialAccount($user, $linkedInUser);

                    return redirect()->route('linkedin.settings')
                        ->with('flash', [
                            'type' => 'success',
                            'message' => 'LinkedIn account linked successfully. You can now log in with either method.',
                        ]);
                }

                // User is not logged in - handle login/registration flows

                // If social account exists, log in the associated user
                if ($socialAccount) {
                    Auth::login($socialAccount->user, true);

                    $this->updateSocialAccountData($socialAccount, $linkedInUser);

                    return redirect()->route('dashboard')
                        ->with('flash', [
                            'type' => 'success',
                            'message' => 'Successfully logged in with LinkedIn.',
                        ]);
                }

                // Check if email matches existing user
                $user = User::where('email', $linkedInUser->getEmail())->first();

                if ($user) {
                    // Email matches - auto-link and log in
                    $this->createSocialAccount($user, $linkedInUser);

                    Auth::login($user, true);

                    return redirect()->route('dashboard')
                        ->with('flash', [
                            'type' => 'success',
                            'message' => 'We found your existing account and linked it with LinkedIn. You can now log in with either method.',
                        ]);
                }

                // No existing user - create new account
                $user = User::create([
                    'name' => $linkedInUser->getName(),
                    'email' => $linkedInUser->getEmail(),
                    'password' => null,
                    'email_verified_at' => now(), // Trust LinkedIn email verification
                ]);

                $this->createSocialAccount($user, $linkedInUser);

                Auth::login($user, true);

                return redirect()->route('dashboard')
                    ->with('flash', [
                        'type' => 'success',
                        'message' => 'Welcome! Your account has been created successfully.',
                    ]);
            });
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('flash', [
                    'type' => 'error',
                    'message' => 'Failed to authenticate with LinkedIn. Please try again.',
                ]);
        }
    }

    /**
     * Create a new social account record
     */
    private function createSocialAccount(User $user, SocialiteUser $linkedInUser): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'linkedin-openid',
            'provider_id' => $linkedInUser->getId(),
            'name' => $linkedInUser->getName(),
            'email' => $linkedInUser->getEmail(),
            'avatar' => $linkedInUser->getAvatar(),
            'token' => $linkedInUser->token,
            'refresh_token' => $linkedInUser->refreshToken,
        ]);
    }

    /**
     * Update existing social account data
     */
    private function updateSocialAccountData(SocialAccount $socialAccount, SocialiteUser $linkedInUser): void
    {
        $socialAccount->update([
            'name' => $linkedInUser->getName(),
            'email' => $linkedInUser->getEmail(),
            'avatar' => $linkedInUser->getAvatar(),
            'token' => $linkedInUser->token,
            'refresh_token' => $linkedInUser->refreshToken,
        ]);
    }
}
