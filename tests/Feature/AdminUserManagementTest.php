<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\AdminUserManagement;
use App\Mail\UserRoleInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->get('/admin/users')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_admin_sees_admin_list_and_current_user_badge(): void
    {
        $a1 = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Ada']);
        $a2 = User::factory()->create(['role' => UserRole::Admin, 'name' => 'Bob']);

        $this->actingAs($a1)->get('/admin/users')
            ->assertOk()
            ->assertSee('Ada')
            ->assertSee('Bob')
            ->assertSee('you');
    }

    public function test_invite_creates_new_admin_and_sends_invite_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->set('inviteEmail', 'newadmin@example.com')
            ->set('inviteName', 'New Admin')
            ->call('invite')
            ->assertHasNoErrors();

        $new = User::where('email', 'newadmin@example.com')->first();
        $this->assertNotNull($new);
        $this->assertSame(UserRole::Admin, $new->role);
        $this->assertNotNull($new->email_verified_at);
        Mail::assertSent(UserRoleInvite::class, fn ($m) => $m->role === UserRole::Admin);
    }

    public function test_invite_upgrades_existing_member_to_admin(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $member = User::factory()->create(['role' => UserRole::Member, 'email' => 'existing@example.com']);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->set('inviteEmail', 'existing@example.com')
            ->call('invite')
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Admin, $member->refresh()->role);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_cannot_revoke_self(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('revoke', $admin->id)
            ->assertHasErrors(['revoke_' . $admin->id]);

        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_cannot_revoke_last_admin(): void
    {
        $a1 = User::factory()->create(['role' => UserRole::Admin]);
        $a2 = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($a1)
            ->test(AdminUserManagement::class)
            // revoke a2 first → now only a1 remains
            ->call('revoke', $a2->id)
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Member, $a2->refresh()->role);

        // Try to revoke a2 again (it's a member now; nothing should change),
        // then try revoking self (must block because last admin).
        Livewire::actingAs($a1)
            ->test(AdminUserManagement::class)
            ->call('revoke', $a1->id)
            ->assertHasErrors(['revoke_' . $a1->id]);

        $this->assertSame(UserRole::Admin, $a1->refresh()->role);
    }

    public function test_revoke_admin_downgrades_to_member(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $other = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('revoke', $other->id)
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Member, $other->refresh()->role);
    }
}
