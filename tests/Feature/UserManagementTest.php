<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Jobs\NotifyAdminsOfNewApplication;
use App\Mail\ApplicationRejected;
use App\Mail\UserRoleInvite;
use App\Models\CreatorApplication;
use App\Models\User;
use App\Services\UserInviter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
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

class ApplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_page_renders_publicly(): void
    {
        $this->get('/apply')->assertOk()->assertSee('Become a');
    }

    public function test_creator_is_redirected_home_from_apply(): void
    {
        $creator = User::factory()->creator()->create(['email_verified_at' => now()]);

        $this->actingAs($creator)->get('/apply')->assertRedirect(route('home'));
    }

    public function test_admin_is_redirected_home_from_apply(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/apply')->assertRedirect(route('home'));
    }

    public function test_member_can_submit_application_and_dispatches_job(): void
    {
        Queue::fake();
        $member = User::factory()->create();

        Livewire::actingAs($member)
            ->test(\App\Livewire\CreatorApplicationForm::class)
            ->set('name', 'New Applicant')
            ->set('email', 'new@example.com')
            ->set('message', str_repeat('I would like to be a creator please. ', 5))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('creator_applications', [
            'email' => 'new@example.com',
            'name'  => 'New Applicant',
            'status' => ApplicationStatus::Pending->value,
        ]);

        Queue::assertPushed(NotifyAdminsOfNewApplication::class);
    }

    public function test_guest_can_submit_application_too(): void
    {
        Queue::fake();

        Livewire::test(\App\Livewire\CreatorApplicationForm::class)
            ->set('name', 'Guest Applicant')
            ->set('email', 'guest@example.com')
            ->set('message', str_repeat('Some good reasons to be a creator please. ', 5))
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('creator_applications', [
            'email' => 'guest@example.com',
        ]);
    }

    public function test_validation_rejects_short_fields(): void
    {
        Livewire::test(\App\Livewire\CreatorApplicationForm::class)
            ->set('name', 'a')
            ->set('email', 'not-an-email')
            ->set('message', 'short')
            ->call('submit')
            ->assertHasErrors(['name', 'email', 'message']);
    }

    public function test_duplicate_pending_application_is_rejected(): void
    {
        CreatorApplication::create([
            'email'   => 'dup@example.com',
            'name'    => 'First App',
            'message' => str_repeat('Original attempt. ', 5),
            'status'  => ApplicationStatus::Pending,
            'applied_at' => now(),
        ]);

        Livewire::test(\App\Livewire\CreatorApplicationForm::class)
            ->set('name', 'Second App')
            ->set('email', 'dup@example.com')
            ->set('message', str_repeat('Trying again. ', 5))
            ->call('submit')
            ->assertHasErrors(['email'])
            ->assertSet('submitted', false);

        $this->assertDatabaseCount('creator_applications', 1);
    }

    public function test_approved_application_blocks_reapply(): void
    {
        CreatorApplication::create([
            'email'   => 'approve@example.com',
            'name'    => 'First App',
            'message' => str_repeat('Original attempt. ', 5),
            'status'  => ApplicationStatus::Approved,
            'applied_at' => now(),
        ]);

        Livewire::test(\App\Livewire\CreatorApplicationForm::class)
            ->set('name', 'Second App')
            ->set('email', 'approve@example.com')
            ->set('message', str_repeat('Trying again. ', 5))
            ->call('submit')
            ->assertHasErrors(['email']);
    }

    public function test_rejected_application_can_reapply(): void
    {
        CreatorApplication::create([
            'email'   => 'rejected@example.com',
            'name'    => 'First App',
            'message' => str_repeat('Original attempt. ', 5),
            'status'  => ApplicationStatus::Rejected,
            'applied_at' => now()->subDay(),
            'reviewed_at' => now(),
        ]);

        Livewire::test(\App\Livewire\CreatorApplicationForm::class)
            ->set('name', 'Second App')
            ->set('email', 'rejected@example.com')
            ->set('message', str_repeat('Trying again. ', 5))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertDatabaseCount('creator_applications', 2);
    }
}

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
            ->test(\App\Livewire\AdminUserManagement::class)
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
            ->test(\App\Livewire\AdminUserManagement::class)
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
            ->test(\App\Livewire\AdminUserManagement::class)
            ->call('revoke', $admin->id)
            ->assertHasErrors(['revoke_' . $admin->id]);

        $this->assertSame(UserRole::Admin, $admin->refresh()->role);
    }

    public function test_cannot_revoke_last_admin(): void
    {
        $a1 = User::factory()->create(['role' => UserRole::Admin]);
        $a2 = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($a1)
            ->test(\App\Livewire\AdminUserManagement::class)
            // revoke a2 first → now only a1 remains
            ->call('revoke', $a2->id)
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Member, $a2->refresh()->role);

        // Try to revoke a2 again (it's a member now; nothing should change),
        // then try revoking self (must block because last admin).
        Livewire::actingAs($a1)
            ->test(\App\Livewire\AdminUserManagement::class)
            ->call('revoke', $a1->id)
            ->assertHasErrors(['revoke_' . $a1->id]);

        $this->assertSame(UserRole::Admin, $a1->refresh()->role);
    }

    public function test_revoke_admin_downgrades_to_member(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $other = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminUserManagement::class)
            ->call('revoke', $other->id)
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Member, $other->refresh()->role);
    }
}

class AdminCreatorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->get('/admin/creators')->assertForbidden();
    }

    public function test_admin_sees_pending_apps_creators_direct_invite(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $creator = User::factory()->create(['role' => UserRole::Creator, 'name' => 'Carl Creator']);
        $app = CreatorApplication::create([
            'email'   => 'app@example.com',
            'name'    => 'Applicant Ann',
            'message' => 'I love answering questions and would be happy to help.',
            'status'  => ApplicationStatus::Pending,
            'applied_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/creators')
            ->assertOk()
            ->assertSee('Pending Applications')
            ->assertSee('Applicant Ann')
            ->assertSee('Current Creators')
            ->assertSee('Carl Creator')
            ->assertSee('Direct Invite');
    }

    public function test_approve_calls_userinviter_and_marks_application_approved(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $app = CreatorApplication::create([
            'email'   => 'approvee@example.com',
            'name'    => 'Annie Approvee',
            'message' => str_repeat('I am great. ', 5),
            'status'  => ApplicationStatus::Pending,
            'applied_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminCreatorManagement::class)
            ->call('approve', $app->id)
            ->assertHasNoErrors();

        // Created a new creator account
        $u = User::where('email', 'approvee@example.com')->first();
        $this->assertNotNull($u);
        $this->assertSame(UserRole::Creator, $u->role);
        $this->assertNotNull($u->email_verified_at);

        // Application marked approved with timestamp
        $app->refresh();
        $this->assertSame(ApplicationStatus::Approved, $app->status);
        $this->assertNotNull($app->reviewed_at);

        Mail::assertSent(UserRoleInvite::class, fn ($m) => $m->role === UserRole::Creator);
    }

    public function test_approve_on_already_reviewed_application_fails_silently(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $app = CreatorApplication::create([
            'email'   => 'approved@example.com',
            'name'    => 'Done',
            'message' => str_repeat('Already done. ', 5),
            'status'  => ApplicationStatus::Approved,
            'applied_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminCreatorManagement::class)
            ->call('approve', $app->id)
            ->assertHasErrors(['approve_' . $app->id]);

        // No new user created
        $this->assertNull(User::where('email', 'approved@example.com')->first());
    }

    public function test_reject_updates_status_and_optionally_sends_rejection_email(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $app = CreatorApplication::create([
            'email'   => 'rejectee@example.com',
            'name'    => 'Rejected Reg',
            'message' => str_repeat('Try me. ', 5),
            'status'  => ApplicationStatus::Pending,
            'applied_at' => now(),
        ]);

        // Without notify checkbox
        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminCreatorManagement::class)
            ->set('notifyReject', false)
            ->call('reject', $app->id)
            ->assertHasNoErrors();

        $app->refresh();
        $this->assertSame(ApplicationStatus::Rejected, $app->status);
        $this->assertNotNull($app->reviewed_at);
        Mail::assertNotSent(ApplicationRejected::class);

        // Now approve+reject the SECOND with notify — fake Mail pulls new app via factory again
        $app2 = CreatorApplication::create([
            'email'   => 'rejectee2@example.com',
            'name'    => 'Notify Me',
            'message' => str_repeat('Please review carefully. ', 5),
            'status'  => ApplicationStatus::Pending,
            'applied_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminCreatorManagement::class)
            ->set('notifyReject', true)
            ->call('reject', $app2->id);

        Mail::assertSent(ApplicationRejected::class, fn ($m) => $m->name === 'Notify Me');
    }

    public function test_direct_invite_requires_name_and_email(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminCreatorManagement::class)
            ->set('inviteName', '')
            ->set('inviteEmail', '')
            ->call('invite')
            ->assertHasErrors(['inviteName', 'inviteEmail']);
    }

    public function test_direct_invite_creates_creator_via_userinviter(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminCreatorManagement::class)
            ->set('inviteName', 'Direct Invite')
            ->set('inviteEmail', 'direct@example.com')
            ->call('invite')
            ->assertHasNoErrors();

        $u = User::where('email', 'direct@example.com')->first();
        $this->assertNotNull($u);
        $this->assertSame(UserRole::Creator, $u->role);
        Mail::assertSent(UserRoleInvite::class, fn ($m) => $m->role === UserRole::Creator);
    }

    public function test_revoke_creator_downgrades_to_member(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $creator = User::factory()->create(['role' => UserRole::Creator]);

        Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminCreatorManagement::class)
            ->call('revoke', $creator->id)
            ->assertHasNoErrors();

        $this->assertSame(UserRole::Member, $creator->refresh()->role);
    }

    public function test_answered_counts_rendered_per_creator(): void
    {
        $admin   = User::factory()->create(['role' => UserRole::Admin]);
        $creator = User::factory()->create(['role' => UserRole::Creator, 'name' => 'C Answerer']);
        \App\Models\Question::factory()->answeredBy($creator)->create();

        $this->actingAs($admin)->get('/admin/creators')
            ->assertOk()
            ->assertSee('1 answer');
    }
}

class UserInviterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_new_user_when_email_unknown(): void
    {
        Mail::fake();
        $inviter = app(UserInviter::class);

        $result = $inviter->invite('new@example.com', 'New Person', UserRole::Creator);

        $this->assertSame('created', $result);
        $u = User::where('email', 'new@example.com')->first();
        $this->assertNotNull($u);
        $this->assertSame(UserRole::Creator, $u->role);
        $this->assertNotNull($u->email_verified_at);
        Mail::assertSent(UserRoleInvite::class, fn ($m) => $m->role === UserRole::Creator);
    }

    public function test_upgrades_existing_user_when_email_known(): void
    {
        Mail::fake();
        $member = User::factory()->create(['role' => UserRole::Member, 'email' => 'existing@example.com']);
        $inviter = app(UserInviter::class);

        $result = $inviter->invite('existing@example.com', 'Old Name', UserRole::Admin);

        $this->assertSame('upgraded', $result);
        $this->assertSame(UserRole::Admin, $member->refresh()->role);
        $this->assertDatabaseCount('users', 1);
        Mail::assertSent(UserRoleInvite::class, fn ($m) => $m->role === UserRole::Admin);
    }
}