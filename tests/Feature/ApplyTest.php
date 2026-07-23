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
