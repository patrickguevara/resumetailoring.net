<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_factory_creates_user_with_password_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertNotNull($user->password);
    }

    public function test_user_factory_can_create_user_without_password(): void
    {
        $user = User::factory()->withoutPassword()->create();

        $this->assertNull($user->password);
    }

    public function test_user_factory_can_create_user_with_linkedin(): void
    {
        $user = User::factory()->withLinkedIn()->create();

        $this->assertTrue($user->hasLinkedLinkedIn());
        $this->assertNotNull($user->linkedInAccount());
        $this->assertEquals('linkedin-openid', $user->linkedInAccount()->provider);
        $this->assertEquals('linkedin-test-123', $user->linkedInAccount()->provider_id);
    }

    public function test_user_factory_can_create_user_with_custom_linkedin_id(): void
    {
        $customId = 'linkedin-custom-456';
        $user = User::factory()->withLinkedIn($customId)->create();

        $this->assertTrue($user->hasLinkedLinkedIn());
        $this->assertEquals($customId, $user->linkedInAccount()->provider_id);
    }

    public function test_user_factory_can_combine_without_password_and_linkedin(): void
    {
        $user = User::factory()
            ->withoutPassword()
            ->withLinkedIn()
            ->create();

        $this->assertNull($user->password);
        $this->assertTrue($user->hasLinkedLinkedIn());
    }

    public function test_linkedin_social_account_has_correct_data(): void
    {
        $user = User::factory()->withLinkedIn('test-id-789')->create();
        $socialAccount = $user->linkedInAccount();

        $this->assertEquals($user->id, $socialAccount->user_id);
        $this->assertEquals('linkedin-openid', $socialAccount->provider);
        $this->assertEquals('test-id-789', $socialAccount->provider_id);
        $this->assertEquals($user->name, $socialAccount->name);
        $this->assertEquals($user->email, $socialAccount->email);
    }
}
