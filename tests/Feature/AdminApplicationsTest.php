<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Livewire\AdminApplications;
use App\Mail\ApplicationRejected;
use App\Mail\UserRoleInvite;
use App\Models\CreatorApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The responder application inbox. Inviting people and managing the accounts
 * that result moved to the users page; what is left here is approve/reject.
 */
class AdminApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private function application(array $attributes = []): CreatorApplication
    {
        return CreatorApplication::create($attributes + [
            'email'      => 'app@example.com',
            'name'       => 'Applicant Ann',
            'message'    => 'I love answering questions and would be happy to help.',
            'status'     => ApplicationStatus::Pending,
            'applied_at' => now(),
        ]);
    }

    public function test_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Member]))
            ->get('/admin/applications')->assertForbidden();
    }

    public function test_the_old_responders_url_still_lands_here(): void
    {
        $this->get('/admin/responders')->assertRedirect('/admin/applications');
    }

    public function test_admin_sees_pending_applications(): void
    {
        $admin = User::factory()->admin()->create();
        $this->application();

        $this->actingAs($admin)->get('/admin/applications')
            ->assertOk()
            ->assertSee('Pending Applications')
            ->assertSee('Applicant Ann');
    }

    public function test_approve_invites_the_applicant_and_marks_the_application_approved(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $app   = $this->application(['email' => 'approvee@example.com', 'name' => 'Annie Approvee']);

        Livewire::actingAs($admin)
            ->test(AdminApplications::class)
            ->call('approve', $app->id)
            ->assertHasNoErrors();

        $u = User::where('email', 'approvee@example.com')->first();
        $this->assertNotNull($u);
        $this->assertSame(UserRole::Creator, $u->role);
        $this->assertNotNull($u->email_verified_at);

        $app->refresh();
        $this->assertSame(ApplicationStatus::Approved, $app->status);
        $this->assertNotNull($app->reviewed_at);

        Mail::assertSent(UserRoleInvite::class, fn ($m) => $m->role === UserRole::Creator);
    }

    public function test_approve_on_an_already_reviewed_application_creates_nothing(): void
    {
        $admin = User::factory()->admin()->create();
        $app   = $this->application([
            'email'  => 'approved@example.com',
            'status' => ApplicationStatus::Approved,
        ]);

        Livewire::actingAs($admin)
            ->test(AdminApplications::class)
            ->call('approve', $app->id)
            ->assertHasErrors(['approve_' . $app->id]);

        $this->assertNull(User::where('email', 'approved@example.com')->first());
    }

    public function test_reject_is_silent_unless_the_notify_box_is_ticked(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $app   = $this->application(['email' => 'rejectee@example.com', 'name' => 'Rejected Reg']);

        Livewire::actingAs($admin)
            ->test(AdminApplications::class)
            ->set('notifyReject', false)
            ->call('reject', $app->id)
            ->assertHasNoErrors();

        $app->refresh();
        $this->assertSame(ApplicationStatus::Rejected, $app->status);
        $this->assertNotNull($app->reviewed_at);
        Mail::assertNotSent(ApplicationRejected::class);

        $app2 = $this->application(['email' => 'rejectee2@example.com', 'name' => 'Notify Me']);

        Livewire::actingAs($admin)
            ->test(AdminApplications::class)
            ->set('notifyReject', true)
            ->call('reject', $app2->id);

        Mail::assertSent(ApplicationRejected::class, fn ($m) => $m->name === 'Notify Me');
    }

    public function test_inbox_shows_when_the_applicant_accepted_the_conditions(): void
    {
        $this->application(['terms_accepted_at' => now()->setDate(2026, 8, 27)->setTime(9, 15)]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(AdminApplications::class)
            ->assertSee('Accepted the conditions on 27 Aug 2026, 09:15');
    }

    public function test_inbox_flags_an_application_with_no_acceptance_on_record(): void
    {
        $this->application(['terms_accepted_at' => null]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(AdminApplications::class)
            ->assertSee('no acceptance on record');
    }
}
