<?php

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
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

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($linkedInUser);

        Socialite::shouldReceive('driver')
            ->with('linkedin-openid')
            ->andReturn($provider);
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
