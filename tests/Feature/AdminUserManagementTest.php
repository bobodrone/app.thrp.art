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

/**
 * The users table: one list of every account, whatever its role.
 * Blocking has its own file — see BlockUserTest.
 */
class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->get('/admin/users')->assertForbidden();
    }

    public function test_responder_is_forbidden(): void
    {
        $this->actingAs(User::factory()->creator()->create())
            ->get('/admin/users')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/users')->assertRedirect('/login');
    }

    public function test_every_role_is_listed_not_just_admins(): void
    {
        $admin  = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $member = User::factory()->create(['role' => UserRole::Member, 'name' => 'Mo Member']);
        $creator = User::factory()->creator()->create(['name' => 'Rae Responder']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('Ada Admin')
            ->assertSee('Mo Member')
            ->assertSee('Rae Responder')
            ->assertSee('you');
    }

    public function test_search_matches_name_or_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Findable Fay', 'email' => 'fay@example.com']);
        User::factory()->create(['name' => 'Hidden Hal', 'email' => 'hal@example.com']);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->set('search', 'Findable')
            ->assertSee('Findable Fay')
            ->assertDontSee('Hidden Hal')
            ->set('search', 'hal@example')
            ->assertSee('Hidden Hal')
            ->assertDontSee('Findable Fay');
    }

    public function test_role_filter_narrows_the_table(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['role' => UserRole::Member, 'name' => 'Mo Member']);
        User::factory()->creator()->create(['name' => 'Rae Responder']);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->set('roleFilter', UserRole::Creator->value)
            ->assertSee('Rae Responder')
            ->assertDontSee('Mo Member');
    }

    // ── Invites ───────────────────────────────────────────────────────────

    public function test_invite_creates_an_account_in_the_chosen_role(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->set('inviteEmail', 'newadmin@example.com')
            ->set('inviteName', 'New Admin')
            ->set('inviteRole', UserRole::Admin->value)
            ->call('invite')
            ->assertHasNoErrors();

        $new = User::where('email', 'newadmin@example.com')->first();
        $this->assertNotNull($new);
        $this->assertSame(UserRole::Admin, $new->role);
        $this->assertNotNull($new->email_verified_at);
        Mail::assertSent(UserRoleInvite::class, fn ($m) => $m->role === UserRole::Admin);
    }

    public function test_invite_can_also_create_a_responder(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->set('inviteEmail', 'responder@example.com')
            ->set('inviteRole', UserRole::Creator->value)
            ->call('invite')
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Creator, User::where('email', 'responder@example.com')->first()->role);
        Mail::assertSent(UserRoleInvite::class, fn ($m) => $m->role === UserRole::Creator);
    }

    public function test_invite_upgrades_an_existing_account_rather_than_duplicating_it(): void
    {
        Mail::fake();
        $admin  = User::factory()->admin()->create();
        $member = User::factory()->create(['role' => UserRole::Member, 'email' => 'existing@example.com']);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->set('inviteEmail', 'existing@example.com')
            ->set('inviteRole', UserRole::Admin->value)
            ->call('invite')
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Admin, $member->refresh()->role);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_invite_requires_a_valid_email(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->set('inviteEmail', 'not-an-email')
            ->call('invite')
            ->assertHasErrors(['inviteEmail']);
    }

    // ── Role changes ──────────────────────────────────────────────────────

    public function test_role_can_be_changed_in_either_direction(): void
    {
        $admin  = User::factory()->admin()->create();
        $member = User::factory()->create(['role' => UserRole::Member]);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('changeRole', $member->id, UserRole::Creator->value)
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Creator, $member->refresh()->role);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('changeRole', $member->id, UserRole::Member->value)
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Member, $member->refresh()->role);
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $admin  = User::factory()->admin()->create();
        $member = User::factory()->create(['role' => UserRole::Member]);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('changeRole', $member->id, 'superuser')
            ->assertHasErrors(['role_' . $member->id]);

        $this->assertSame(UserRole::Member, $member->refresh()->role);
    }

    public function test_cannot_change_own_role(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('changeRole', $admin->id, UserRole::Member->value)
            ->assertHasErrors(['role_' . $admin->id]);

        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_cannot_demote_the_last_admin(): void
    {
        $a1 = User::factory()->admin()->create();
        $a2 = User::factory()->admin()->create();

        // Demoting the second admin is fine — one is left.
        Livewire::actingAs($a1)
            ->test(AdminUserManagement::class)
            ->call('changeRole', $a2->id, UserRole::Member->value)
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Member, $a2->refresh()->role);

        // Demoting the one that remains is not.
        Livewire::actingAs($a1)
            ->test(AdminUserManagement::class)
            ->call('changeRole', $a1->id, UserRole::Member->value)
            ->assertHasErrors(['role_' . $a1->id]);

        $this->assertSame(UserRole::Admin, $a1->refresh()->role);
    }

    // ── Nickname ──────────────────────────────────────────────────────────

    public function test_an_admin_can_rename_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create(['name' => 'Rude Name']);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('edit', $user->id)
            ->assertSet('showEdit', true)
            ->assertSet('editName', 'Rude Name')
            ->set('editName', '  Polite Name  ')
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertSet('showEdit', false);

        $this->assertSame('Polite Name', $user->refresh()->name);
    }

    public function test_a_rename_is_length_checked(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create(['name' => 'Keep Me']);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('edit', $user->id)
            ->set('editName', 'x')
            ->call('saveEdit')
            ->assertHasErrors(['editName']);

        $this->assertSame('Keep Me', $user->refresh()->name);
    }
}
