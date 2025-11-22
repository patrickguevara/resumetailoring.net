# LinkedIn OAuth Integration Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enable users to authenticate using LinkedIn OAuth via Laravel Socialite, with account linking support for existing users.

**Architecture:** Install Laravel Socialite with LinkedIn provider, create social_accounts table for OAuth data storage, implement OAuth controllers for login/callback flows, add LinkedIn settings page for account management, and integrate LinkedIn button into existing auth UI.

**Tech Stack:** Laravel 11, Laravel Socialite, Inertia.js (Vue 3), Tailwind CSS, TypeScript

---

## Task 1: Install and Configure Laravel Socialite

**Files:**
- Modify: `composer.json`
- Create: `config/services.php` (may already exist)
- Create: `.env.example`
- Modify: `.env`

**Step 1: Install Laravel Socialite**

Run:
```bash
composer require laravel/socialite
```

Expected: Package installed successfully

**Step 2: Add LinkedIn credentials to services config**

If `config/services.php` exists, add LinkedIn configuration. Otherwise, publish it first:

```bash
php artisan config:publish services
```

Add to `config/services.php` after existing services:

```php
'linkedin-openid' => [
    'client_id' => env('LINKEDIN_CLIENT_ID'),
    'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
    'redirect' => env('LINKEDIN_REDIRECT_URI', env('APP_URL').'/auth/linkedin/callback'),
],
```

**Step 3: Add environment variables to .env.example**

Add to `.env.example`:

```
LINKEDIN_CLIENT_ID=
LINKEDIN_CLIENT_SECRET=
LINKEDIN_REDIRECT_URI="${APP_URL}/auth/linkedin/callback"
```

**Step 4: Add environment variables to .env**

Add your actual LinkedIn app credentials to `.env`:

```
LINKEDIN_CLIENT_ID=your_client_id_here
LINKEDIN_CLIENT_SECRET=your_client_secret_here
LINKEDIN_REDIRECT_URI="${APP_URL}/auth/linkedin/callback"
```

**Step 5: Clear config cache**

Run:
```bash
php artisan config:clear
```

Expected: Configuration cache cleared

**Step 6: Commit**

```bash
git add composer.json composer.lock config/services.php .env.example
git commit -m "feat: install and configure Laravel Socialite with LinkedIn provider"
```

---

## Task 2: Create Social Accounts Migration and Model

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_create_social_accounts_table.php`
- Create: `app/Models/SocialAccount.php`
- Modify: `app/Models/User.php`

**Step 1: Create migration**

Run:
```bash
php artisan make:migration create_social_accounts_table
```

Expected: Migration file created

**Step 2: Write migration content**

Edit the new migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // 'linkedin-openid'
            $table->string('provider_id')->unique(); // LinkedIn user ID
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('avatar')->nullable();
            $table->text('token')->nullable(); // OAuth access token
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
```

**Step 3: Run migration**

Run:
```bash
php artisan migrate
```

Expected: Migration successful, social_accounts table created

**Step 4: Create SocialAccount model**

Run:
```bash
php artisan make:model SocialAccount
```

Edit `app/Models/SocialAccount.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'name',
        'email',
        'avatar',
        'token',
        'refresh_token',
        'token_expires_at',
    ];

    protected $hidden = [
        'token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Step 5: Add relationship to User model**

Add to `app/Models/User.php` after existing relationships:

```php
public function socialAccounts(): HasMany
{
    return $this->hasMany(SocialAccount::class);
}

public function hasLinkedLinkedIn(): bool
{
    return $this->socialAccounts()
        ->where('provider', 'linkedin-openid')
        ->exists();
}

public function linkedInAccount(): ?SocialAccount
{
    return $this->socialAccounts()
        ->where('provider', 'linkedin-openid')
        ->first();
}
```

Don't forget to add the import at the top:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;
```

**Step 6: Make password nullable in users table**

Create migration:

```bash
php artisan make:migration make_password_nullable_in_users_table
```

Edit the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
```

Run migration:

```bash
php artisan migrate
```

**Step 7: Commit**

```bash
git add database/migrations app/Models
git commit -m "feat: add social accounts table and relationships"
```

---

## Task 3: Create LinkedIn OAuth Controllers

**Files:**
- Create: `app/Http/Controllers/Auth/LinkedInController.php`
- Modify: `routes/auth.php`

**Step 1: Create LinkedIn controller**

Run:
```bash
php artisan make:controller Auth/LinkedInController
```

Edit `app/Http/Controllers/Auth/LinkedInController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
    public function callback(): RedirectResponse
    {
        try {
            $linkedInUser = Socialite::driver('linkedin-openid')->user();

            return DB::transaction(function () use ($linkedInUser) {
                // Check if social account already exists
                $socialAccount = SocialAccount::where('provider', 'linkedin-openid')
                    ->where('provider_id', $linkedInUser->getId())
                    ->first();

                if ($socialAccount) {
                    // Social account exists - log in the associated user
                    Auth::login($socialAccount->user, true);

                    $this->updateSocialAccountData($socialAccount, $linkedInUser);

                    return redirect()->route('dashboard')
                        ->with('flash', [
                            'type' => 'success',
                            'message' => 'Successfully logged in with LinkedIn.',
                        ]);
                }

                // Check if user is already logged in (manual linking from settings)
                if (Auth::check()) {
                    $user = Auth::user();

                    // Check if this user already has a LinkedIn account linked
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

                // Not logged in - check if email matches existing user
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
    private function createSocialAccount(User $user, $linkedInUser): SocialAccount
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
    private function updateSocialAccountData(SocialAccount $socialAccount, $linkedInUser): void
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
```

**Step 2: Add routes**

Add to `routes/auth.php` in the guest middleware group:

```php
Route::get('auth/linkedin', [App\Http\Controllers\Auth\LinkedInController::class, 'redirect'])
    ->name('auth.linkedin');

Route::get('auth/linkedin/callback', [App\Http\Controllers\Auth\LinkedInController::class, 'callback'])
    ->name('auth.linkedin.callback');
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Auth/LinkedInController.php routes/auth.php
git commit -m "feat: add LinkedIn OAuth login and callback handlers"
```

---

## Task 4: Add LinkedIn Button to Login Page

**Files:**
- Modify: `resources/js/Pages/auth/Login.vue`

**Step 1: Add LinkedIn login button to Login.vue**

Update the template section in `resources/js/Pages/auth/Login.vue`. After the opening `<Form>` tag and before the `<div class="grid gap-6">`, add:

```vue
<!-- LinkedIn Login Button -->
<a
    :href="route('auth.linkedin')"
    class="flex w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50"
>
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
    </svg>
    Log in with LinkedIn
</a>

<!-- Divider -->
<div class="relative">
    <div class="absolute inset-0 flex items-center">
        <span class="w-full border-t"></span>
    </div>
    <div class="relative flex justify-center text-xs uppercase">
        <span class="bg-background px-2 text-muted-foreground">
            Or log in with email
        </span>
    </div>
</div>
```

**Step 2: Test the login page renders**

Run:
```bash
npm run build
```

Visit `/login` in browser to verify the LinkedIn button appears

**Step 3: Commit**

```bash
git add resources/js/Pages/auth/Login.vue
git commit -m "feat: add LinkedIn login button to login page"
```

---

## Task 5: Add LinkedIn Button to Register Page

**Files:**
- Modify: `resources/js/Pages/auth/Register.vue`

**Step 1: Read current Register.vue structure**

```bash
cat resources/js/Pages/auth/Register.vue
```

**Step 2: Add LinkedIn signup button**

Similar to Login.vue, add the LinkedIn button at the top of the form, before the name/email/password fields:

```vue
<!-- LinkedIn Signup Button -->
<a
    :href="route('auth.linkedin')"
    class="flex w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50"
>
    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
    </svg>
    Sign up with LinkedIn
</a>

<!-- Divider -->
<div class="relative">
    <div class="absolute inset-0 flex items-center">
        <span class="w-full border-t"></span>
    </div>
    <div class="relative flex justify-center text-xs uppercase">
        <span class="bg-background px-2 text-muted-foreground">
            Or sign up with email
        </span>
    </div>
</div>
```

**Step 3: Commit**

```bash
git add resources/js/Pages/auth/Register.vue
git commit -m "feat: add LinkedIn signup button to register page"
```

---

## Task 6: Create LinkedIn Settings Page and Controller

**Files:**
- Create: `app/Http/Controllers/Settings/LinkedInController.php`
- Create: `resources/js/Pages/settings/LinkedIn.vue`
- Modify: `routes/settings.php`

**Step 1: Create LinkedIn settings controller**

Run:
```bash
php artisan make:controller Settings/LinkedInController
```

Edit `app/Http/Controllers/Settings/LinkedInController.php`:

```php
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
            'hasPassword' => !is_null($user->password),
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

        if (!$linkedInAccount) {
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
```

**Step 2: Create LinkedIn.vue settings page**

Create `resources/js/Pages/settings/LinkedIn.vue`:

```vue
<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { linkedin } from '@/routes/settings';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Linkedin } from 'lucide-vue-next';
import { ref } from 'vue';

interface LinkedInAccountData {
    name: string;
    email: string;
    avatar: string | null;
    connected_at: string;
}

interface Props {
    linkedInAccount: LinkedInAccountData | null;
    hasPassword: boolean;
}

const props = defineProps<Props>();

const showDisconnectDialog = ref(false);
const isDisconnecting = ref(false);

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'LinkedIn settings',
        href: linkedin.show().url,
    },
];

function connectLinkedIn() {
    window.location.href = route('auth.linkedin');
}

function confirmDisconnect() {
    showDisconnectDialog.value = true;
}

function disconnect() {
    isDisconnecting.value = true;
    router.delete(route('linkedin.destroy'), {
        preserveScroll: true,
        onFinish: () => {
            isDisconnecting.value = false;
            showDisconnectDialog.value = false;
        },
    });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="LinkedIn settings" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall
                    title="LinkedIn account"
                    description="Connect your LinkedIn account to enable social login"
                />

                <!-- Not Connected State -->
                <div v-if="!linkedInAccount" class="space-y-4">
                    <div
                        class="rounded-lg border border-border bg-card p-6 text-center"
                    >
                        <div
                            class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10"
                        >
                            <Linkedin class="h-6 w-6 text-primary" />
                        </div>
                        <h3 class="mb-2 text-lg font-semibold">
                            Connect LinkedIn
                        </h3>
                        <p class="mb-4 text-sm text-muted-foreground">
                            Link your LinkedIn account to enable quick login
                            with LinkedIn in addition to your email and
                            password.
                        </p>
                        <Button @click="connectLinkedIn">
                            <Linkedin class="mr-2 h-4 w-4" />
                            Connect LinkedIn account
                        </Button>
                    </div>
                </div>

                <!-- Connected State -->
                <div v-else class="space-y-4">
                    <div
                        class="flex items-start justify-between rounded-lg border border-border bg-card p-6"
                    >
                        <div class="flex items-start gap-4">
                            <div
                                v-if="linkedInAccount.avatar"
                                class="h-12 w-12 flex-shrink-0 overflow-hidden rounded-full"
                            >
                                <img
                                    :src="linkedInAccount.avatar"
                                    :alt="linkedInAccount.name"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                            <div
                                v-else
                                class="flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-primary/10"
                            >
                                <Linkedin class="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">
                                    {{ linkedInAccount.name }}
                                </h3>
                                <p class="text-sm text-muted-foreground">
                                    {{ linkedInAccount.email }}
                                </p>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Connected on
                                    {{ linkedInAccount.connected_at }}
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="confirmDisconnect"
                        >
                            Disconnect
                        </Button>
                    </div>

                    <div
                        class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950"
                    >
                        <p class="text-sm text-green-800 dark:text-green-200">
                            Your LinkedIn account is connected. You can log in
                            using either your email and password or LinkedIn.
                        </p>
                    </div>
                </div>
            </div>
        </SettingsLayout>

        <!-- Disconnect Confirmation Dialog -->
        <Dialog v-model:open="showDisconnectDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Disconnect LinkedIn account?</DialogTitle>
                    <DialogDescription>
                        <span v-if="!hasPassword" class="text-destructive">
                            You must set a password before disconnecting
                            LinkedIn. Please visit the Password settings page
                            first.
                        </span>
                        <span v-else>
                            Your LinkedIn account will be disconnected. You'll
                            still be able to log in with your email and
                            password. You can reconnect LinkedIn anytime.
                        </span>
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button
                        variant="outline"
                        @click="showDisconnectDialog = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        v-if="hasPassword"
                        variant="destructive"
                        :disabled="isDisconnecting"
                        @click="disconnect"
                    >
                        Disconnect
                    </Button>
                    <Button
                        v-else
                        as-child
                        @click="showDisconnectDialog = false"
                    >
                        <a :href="route('password.edit')">
                            Go to Password Settings
                        </a>
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
```

**Step 3: Add routes**

Add to `routes/settings.php` in the auth middleware group:

```php
Route::get('settings/linkedin', [App\Http\Controllers\Settings\LinkedInController::class, 'show'])
    ->name('linkedin.settings');

Route::delete('settings/linkedin', [App\Http\Controllers\Settings\LinkedInController::class, 'destroy'])
    ->name('linkedin.destroy');
```

**Step 4: Generate TypeScript route helpers**

Run:
```bash
php artisan ziggy:generate
```

Expected: Route definitions generated

**Step 5: Commit**

```bash
git add app/Http/Controllers/Settings/LinkedInController.php resources/js/Pages/settings/LinkedIn.vue routes/settings.php
git commit -m "feat: add LinkedIn settings page with connect/disconnect functionality"
```

---

## Task 7: Add LinkedIn Link to Sidebar Navigation

**Files:**
- Modify: `resources/js/Components/AppSidebar.vue`

**Step 1: Read AppSidebar.vue to understand structure**

```bash
cat resources/js/Components/AppSidebar.vue
```

**Step 2: Add LinkedIn to settings navigation**

In the settings section of the sidebar navigation, add a new navigation item for LinkedIn. Look for where settings items like "Profile", "Billing", "Password" etc. are defined and add:

```vue
{
    title: 'LinkedIn',
    href: route('linkedin.settings'),
    icon: Linkedin,
}
```

Make sure to import the Linkedin icon at the top of the script section:

```typescript
import { Linkedin } from 'lucide-vue-next';
```

**Step 3: Add conditional rendering to hide when linked**

If the sidebar shows settings items conditionally, add logic to only show the "Connect LinkedIn" prompt when not connected. However, always keep the settings page accessible from the navigation.

**Step 4: Test sidebar renders**

Run:
```bash
npm run build
```

Visit `/settings/linkedin` to verify navigation works

**Step 5: Commit**

```bash
git add resources/js/Components/AppSidebar.vue
git commit -m "feat: add LinkedIn to settings navigation"
```

---

## Task 8: Update Password Controller to Require Password for LinkedIn Users

**Files:**
- Modify: `app/Http/Controllers/Settings/PasswordController.php`

**Step 1: Read current PasswordController**

```bash
cat app/Http/Controllers/Settings/PasswordController.php
```

**Step 2: Update edit method to pass hasLinkedIn flag**

Modify the `edit` method to include information about LinkedIn connection:

```php
public function edit(Request $request): Response
{
    $user = $request->user();

    return Inertia::render('settings/Password', [
        'hasPassword' => !is_null($user->password),
        'hasLinkedIn' => $user->hasLinkedLinkedIn(),
    ]);
}
```

**Step 3: Update Password.vue to show LinkedIn warning**

Edit `resources/js/Pages/settings/Password.vue` to accept the new prop and show a warning if user has LinkedIn but no password:

Add to props:

```typescript
interface Props {
    hasPassword: boolean;
    hasLinkedIn: boolean;
}
```

Add warning message in the template before the password form:

```vue
<div
    v-if="hasLinkedIn && !hasPassword"
    class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950"
>
    <p class="text-sm text-amber-800 dark:text-amber-200">
        You're currently using LinkedIn to log in. Setting a password will give
        you an alternative way to access your account.
    </p>
</div>
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Settings/PasswordController.php resources/js/Pages/settings/Password.vue
git commit -m "feat: add LinkedIn awareness to password settings"
```

---

## Task 9: Add TypeScript Types

**Files:**
- Modify: `resources/js/types/index.ts` (or equivalent types file)

**Step 1: Find types file**

```bash
find resources/js -name "*.ts" -path "*types*" | head -5
```

**Step 2: Add social account types**

Add to the types file:

```typescript
export interface SocialAccount {
    provider: string;
    provider_id: string;
    name: string | null;
    email: string | null;
    avatar: string | null;
    connected_at: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    has_linked_linkedin?: boolean;
    // ... existing user properties
}
```

**Step 3: Commit**

```bash
git add resources/js/types
git commit -m "feat: add TypeScript types for social authentication"
```

---

## Task 10: Write Tests for LinkedIn OAuth Flow

**Files:**
- Create: `tests/Feature/Auth/LinkedInAuthTest.php`

**Step 1: Create test file**

Run:
```bash
php artisan make:test Auth/LinkedInAuthTest
```

**Step 2: Write tests**

Edit `tests/Feature/Auth/LinkedInAuthTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class LinkedInAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function mockLinkedInUser(string $id, string $email, string $name): void
    {
        $linkedInUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $linkedInUser->shouldReceive('getId')->andReturn($id);
        $linkedInUser->shouldReceive('getEmail')->andReturn($email);
        $linkedInUser->shouldReceive('getName')->andReturn($name);
        $linkedInUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
        $linkedInUser->token = 'mock-token';
        $linkedInUser->refreshToken = 'mock-refresh-token';

        Socialite::shouldReceive('driver->user')
            ->andReturn($linkedInUser);
    }

    public function test_redirect_to_linkedin(): void
    {
        $response = $this->get(route('auth.linkedin'));

        $response->assertRedirect();
    }

    public function test_new_user_registration_via_linkedin(): void
    {
        $this->mockLinkedInUser('linkedin-123', 'newuser@example.com', 'New User');

        $response = $this->get(route('auth.linkedin.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::where('email', 'newuser@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('New User', $user->name);
        $this->assertNull($user->password);
        $this->assertNotNull($user->email_verified_at);

        $socialAccount = SocialAccount::where('user_id', $user->id)->first();
        $this->assertNotNull($socialAccount);
        $this->assertEquals('linkedin-openid', $socialAccount->provider);
        $this->assertEquals('linkedin-123', $socialAccount->provider_id);
    }

    public function test_existing_user_login_via_linkedin(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'linkedin-openid',
            'provider_id' => 'linkedin-456',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $this->mockLinkedInUser('linkedin-456', 'existing@example.com', $user->name);

        $response = $this->get(route('auth.linkedin.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_auto_link_existing_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        $this->mockLinkedInUser('linkedin-789', 'existing@example.com', 'Existing User');

        $response = $this->get(route('auth.linkedin.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $socialAccount = SocialAccount::where('user_id', $user->id)->first();
        $this->assertNotNull($socialAccount);
        $this->assertEquals('linkedin-789', $socialAccount->provider_id);
    }

    public function test_logged_in_user_can_link_linkedin(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->mockLinkedInUser('linkedin-999', 'different@example.com', 'Different User');

        $response = $this->get(route('auth.linkedin.callback'));

        $response->assertRedirect(route('linkedin.settings'));

        $socialAccount = SocialAccount::where('user_id', $user->id)->first();
        $this->assertNotNull($socialAccount);
        $this->assertEquals('linkedin-999', $socialAccount->provider_id);
    }

    public function test_user_cannot_link_multiple_linkedin_accounts(): void
    {
        $user = User::factory()->create();
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'linkedin-openid',
            'provider_id' => 'linkedin-first',
            'email' => $user->email,
        ]);

        $this->actingAs($user);

        $this->mockLinkedInUser('linkedin-second', 'another@example.com', 'Another User');

        $response = $this->get(route('auth.linkedin.callback'));

        $response->assertRedirect(route('linkedin.settings'));
        $response->assertSessionHas('flash.type', 'error');

        $this->assertEquals(1, SocialAccount::where('user_id', $user->id)->count());
    }

    public function test_user_can_disconnect_linkedin_if_has_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $socialAccount = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'linkedin-openid',
            'provider_id' => 'linkedin-123',
            'email' => $user->email,
        ]);

        $this->actingAs($user);

        $response = $this->delete(route('linkedin.destroy'));

        $response->assertRedirect(route('linkedin.settings'));
        $response->assertSessionHas('flash.type', 'success');

        $this->assertDatabaseMissing('social_accounts', ['id' => $socialAccount->id]);
    }

    public function test_user_cannot_disconnect_linkedin_without_password(): void
    {
        $user = User::factory()->create(['password' => null]);
        $socialAccount = SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'linkedin-openid',
            'provider_id' => 'linkedin-123',
            'email' => $user->email,
        ]);

        $this->actingAs($user);

        $response = $this->delete(route('linkedin.destroy'));

        $response->assertRedirect(route('linkedin.settings'));
        $response->assertSessionHas('flash.type', 'error');

        $this->assertDatabaseHas('social_accounts', ['id' => $socialAccount->id]);
    }
}
```

**Step 3: Run tests**

Run:
```bash
php artisan test --filter=LinkedInAuthTest
```

Expected: All tests pass

**Step 4: Commit**

```bash
git add tests/Feature/Auth/LinkedInAuthTest.php
git commit -m "test: add comprehensive LinkedIn OAuth flow tests"
```

---

## Task 11: Update User Factory for Testing

**Files:**
- Modify: `database/factories/UserFactory.php`

**Step 1: Add state for LinkedIn users**

Add to `database/factories/UserFactory.php`:

```php
/**
 * Indicate that the user has no password (LinkedIn only)
 */
public function withoutPassword(): static
{
    return $this->state(fn (array $attributes) => [
        'password' => null,
    ]);
}

/**
 * Indicate that the user has a linked LinkedIn account
 */
public function withLinkedIn(string $linkedInId = 'linkedin-test-123'): static
{
    return $this->afterCreating(function (User $user) use ($linkedInId) {
        \App\Models\SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'linkedin-openid',
            'provider_id' => $linkedInId,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    });
}
```

**Step 2: Commit**

```bash
git add database/factories/UserFactory.php
git commit -m "feat: add user factory states for LinkedIn testing"
```

---

## Task 12: Manual Testing and Documentation

**Files:**
- Create: `docs/LINKEDIN_OAUTH_SETUP.md`

**Step 1: Create setup documentation**

Create `docs/LINKEDIN_OAUTH_SETUP.md`:

```markdown
# LinkedIn OAuth Setup Guide

This guide explains how to set up LinkedIn OAuth authentication for local development and production.

## Prerequisites

- LinkedIn Developer Account
- LinkedIn App created in LinkedIn Developer Portal

## LinkedIn App Configuration

1. Go to [LinkedIn Developers](https://www.linkedin.com/developers/)
2. Create a new app or select existing app
3. In "Auth" tab:
   - Add redirect URL: `http://localhost/auth/linkedin/callback` (local)
   - Add redirect URL: `https://yourdomain.com/auth/linkedin/callback` (production)
4. In "Products" tab:
   - Request access to "Sign In with LinkedIn using OpenID Connect"
5. Copy your Client ID and Client Secret

## Environment Configuration

Add to `.env`:

```env
LINKEDIN_CLIENT_ID=your_client_id_here
LINKEDIN_CLIENT_SECRET=your_client_secret_here
LINKEDIN_REDIRECT_URI="${APP_URL}/auth/linkedin/callback"
```

## Testing OAuth Flow

### Test New User Registration

1. Visit `/login`
2. Click "Log in with LinkedIn"
3. Authorize the app on LinkedIn
4. Verify you're logged in and redirected to dashboard
5. Check database: user created with no password, social_account created

### Test Existing User Auto-Link

1. Create user with email `test@example.com`
2. Log out
3. Click "Log in with LinkedIn"
4. Use LinkedIn account with same email `test@example.com`
5. Verify auto-linked and logged in

### Test Manual Linking

1. Log in with email/password
2. Go to Settings > LinkedIn
3. Click "Connect LinkedIn account"
4. Authorize on LinkedIn
5. Verify account linked in settings

### Test Disconnect

1. Ensure user has password set
2. Go to Settings > LinkedIn
3. Click "Disconnect"
4. Confirm in dialog
5. Verify LinkedIn removed but can still log in with password

### Test Disconnect Without Password

1. Create user via LinkedIn only (no password)
2. Go to Settings > LinkedIn
3. Try to disconnect
4. Verify error message requiring password
5. Set password in Settings > Password
6. Retry disconnect - should work

## Troubleshooting

### "Invalid redirect URI" error

- Ensure redirect URI in LinkedIn app matches exactly (including http/https)
- Check `.env` has correct `APP_URL`

### "Email not provided" error

- Ensure app has access to "Sign In with LinkedIn using OpenID Connect"
- Check scopes include 'email'

### User created without email

- LinkedIn user may not have verified email
- Check LinkedIn profile has public email

## Security Notes

- Never commit `.env` with real credentials
- Use different LinkedIn apps for dev/staging/production
- Rotate client secret if compromised
- Access tokens are stored encrypted in database
```

**Step 2: Manual testing checklist**

Test each scenario from documentation:

- [ ] New user registration via LinkedIn
- [ ] Existing user login via LinkedIn
- [ ] Auto-link by email match
- [ ] Manual link from settings when logged in
- [ ] Disconnect with password
- [ ] Prevent disconnect without password
- [ ] Cannot link multiple LinkedIn accounts
- [ ] Login button appears on /login
- [ ] Signup button appears on /register
- [ ] Settings page accessible from sidebar
- [ ] Flash messages appear correctly
- [ ] Proper redirects after OAuth

**Step 3: Commit documentation**

```bash
git add docs/LINKEDIN_OAUTH_SETUP.md
git commit -m "docs: add LinkedIn OAuth setup and testing guide"
```

---

## Final Verification

**Step 1: Run all tests**

```bash
php artisan test
```

Expected: All tests pass

**Step 2: Check code style**

```bash
./vendor/bin/pint
```

Expected: No style issues

**Step 3: Build frontend assets**

```bash
npm run build
```

Expected: Build successful, no errors

**Step 4: Verify routes**

```bash
php artisan route:list | grep linkedin
```

Expected: Shows all LinkedIn-related routes

**Step 5: Final commit**

```bash
git add .
git commit -m "feat: complete LinkedIn OAuth integration with account linking"
```

---

## Summary

This implementation adds complete LinkedIn OAuth authentication with:

- ✅ New user registration via LinkedIn
- ✅ Existing user login via LinkedIn
- ✅ Auto-linking by email match
- ✅ Manual account linking from settings
- ✅ Secure disconnect with password requirement
- ✅ UI integration in login/register/settings pages
- ✅ Comprehensive test coverage
- ✅ Type-safe TypeScript integration
- ✅ Flash message notifications
- ✅ One-to-one LinkedIn account constraint

The implementation follows Laravel best practices, maintains backward compatibility with existing email/password authentication, and provides a smooth user experience across all OAuth flows.
