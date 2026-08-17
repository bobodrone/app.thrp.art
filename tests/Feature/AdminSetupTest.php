<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_renders_when_no_admin_exists(): void
    {
        config(['app.bootstrap_token' => 'secret-token']);
        User::factory()->create(['role' => UserRole::Member]);

        $this->get('/admin/setup')->assertOk();
    }

    public function test_setup_page_returns_403_once_an_admin_exists(): void
    {
        User::factory()->admin()->create();

        $this->get('/admin/setup')->assertForbidden();
    }

    public function test_admin_user_is_redirected_to_admin_users_from_setup(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/setup')
            ->assertRedirect(route('admin.users'));
    }

    public function test_setup_promotes_existing_user_to_admin_with_correct_token(): void
    {
        config(['app.bootstrap_token' => 'secret-token']);
        $member = User::factory()->create(['role' => UserRole::Member, 'email' => 'bob@example.com']);

        $response = $this->post('/admin/setup', [
            'email' => 'bob@example.com',
            'token' => 'secret-token',
        ]);

        $response->assertRedirect(route('admin.users'));
        $this->assertSame(UserRole::Admin, $member->refresh()->role);
    }

    public function test_setup_rejects_wrong_token(): void
    {
        config(['app.bootstrap_token' => 'secret-token']);
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->from('/admin/setup')->post('/admin/setup', [
            'email' => $member->email,
            'token' => 'wrong-token',
        ])
            ->assertSessionHasErrors(['token']);

        $this->assertSame(UserRole::Member, $member->refresh()->role);
    }

    public function test_setup_rejects_unknown_email(): void
    {
        config(['app.bootstrap_token' => 'secret-token']);

        $this->from('/admin/setup')->post('/admin/setup', [
            'email' => 'no-such-user@example.com',
            'token' => 'secret-token',
        ])
            ->assertSessionHasErrors(['email']);
    }

    public function test_setup_returns_error_when_bootstrap_token_unconfigured(): void
    {
        config(['app.bootstrap_token' => null]);
        User::factory()->create();

        $this->from('/admin/setup')->post('/admin/setup', [
            'email' => 'anyone@example.com',
            'token' => 'anything',
        ])
            ->assertSessionHasErrors(['token']);
    }

    public function test_setup_blocks_submit_once_an_admin_already_exists(): void
    {
        config(['app.bootstrap_token' => 'secret-token']);
        User::factory()->admin()->create();
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->post('/admin/setup', [
            'email' => $member->email,
            'token' => 'secret-token',
        ])->assertForbidden();

        $this->assertSame(UserRole::Member, $member->refresh()->role);
    }
}
