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

class AdminCreatorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->get('/admin/responders')->assertForbidden();
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

        $this->actingAs($admin)->get('/admin/responders')
            ->assertOk()
            ->assertSee('Pending Applications')
            ->assertSee('Applicant Ann')
            ->assertSee('Current Responders')
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

        $this->actingAs($admin)->get('/admin/responders')
            ->assertOk()
            ->assertSee('1 answer');
    }
}
