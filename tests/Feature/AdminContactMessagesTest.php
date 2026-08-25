<?php

namespace Tests\Feature;

use App\Livewire\AdminContactMessages;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminContactMessagesTest extends TestCase
{
    use RefreshDatabase;

    // ── Access ────────────────────────────────────────────────────────────

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/messages')->assertRedirect(route('login'));
    }

    public function test_members_and_responders_are_turned_away(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/messages')->assertForbidden();

        $responder = User::factory()->creator()->create();
        $this->actingAs($responder)->get('/admin/messages')->assertForbidden();
    }

    public function test_admins_can_open_the_inbox(): void
    {
        ContactMessage::factory()->create(['subject' => 'Something is broken']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/messages')
            ->assertOk()
            ->assertSee('Something is broken');
    }

    public function test_admin_navigation_links_to_the_inbox_with_an_open_count(): void
    {
        ContactMessage::factory()->count(2)->create();
        ContactMessage::factory()->handled()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/')
            ->assertOk()
            ->assertSee(route('admin.messages'))
            ->assertSeeInOrder(['Messages', '2']);
    }

    // ── Listing ───────────────────────────────────────────────────────────

    public function test_unhandled_messages_are_listed_before_handled_ones(): void
    {
        ContactMessage::factory()->handled()->create([
            'subject'    => 'Old and dealt with',
            'created_at' => now(),
        ]);
        ContactMessage::factory()->create([
            'subject'    => 'Still waiting',
            'created_at' => now()->subWeek(),
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(AdminContactMessages::class)
            ->assertSeeInOrder(['Still waiting', 'Old and dealt with']);
    }

    public function test_search_narrows_the_list(): void
    {
        ContactMessage::factory()->create(['subject' => 'Broken avatar upload']);
        ContactMessage::factory()->create(['subject' => 'Just saying hello']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(AdminContactMessages::class)
            ->set('search', 'avatar')
            ->assertSee('Broken avatar upload')
            ->assertDontSee('Just saying hello');
    }

    public function test_search_also_matches_the_message_body(): void
    {
        ContactMessage::factory()->create([
            'subject' => 'Hello',
            'message' => 'The avatar upload fails on my phone every time.',
        ]);
        ContactMessage::factory()->create(['subject' => 'Unrelated', 'message' => 'Nothing to see.']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(AdminContactMessages::class)
            ->set('search', 'avatar upload')
            ->assertSee('Hello')
            ->assertDontSee('Unrelated');
    }

    public function test_unhandled_filter_hides_the_handled_ones(): void
    {
        ContactMessage::factory()->create(['subject' => 'Open one']);
        ContactMessage::factory()->handled()->create(['subject' => 'Closed one']);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(AdminContactMessages::class)
            ->set('unhandledOnly', true)
            ->assertSee('Open one')
            ->assertDontSee('Closed one');
    }

    public function test_the_full_body_appears_only_once_a_message_is_opened(): void
    {
        $msg = ContactMessage::factory()->create([
            'message' => str_repeat('A body far longer than the ninety characters the table preview keeps. ', 3)
                . 'Only the expanded row shows the tail end of it.',
        ]);

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(AdminContactMessages::class)
            ->assertDontSee('the tail end of it')
            ->call('toggleOpen', $msg->id)
            ->assertSee('the tail end of it')
            ->call('toggleOpen', $msg->id)
            ->assertDontSee('the tail end of it');
    }

    // ── Actions ───────────────────────────────────────────────────────────

    public function test_admin_can_mark_a_message_handled_and_reopen_it(): void
    {
        $admin = User::factory()->admin()->create();
        $msg   = ContactMessage::factory()->create();

        $component = Livewire::actingAs($admin)
            ->test(AdminContactMessages::class)
            ->call('markHandled', $msg->id);

        $msg->refresh();
        $this->assertNotNull($msg->handled_at);
        $this->assertSame($admin->id, $msg->handled_by);

        $component->call('markUnhandled', $msg->id);

        $msg->refresh();
        $this->assertNull($msg->handled_at);
        $this->assertNull($msg->handled_by);
    }

    public function test_admin_can_delete_a_message(): void
    {
        $msg = ContactMessage::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(AdminContactMessages::class)
            ->call('toggleOpen', $msg->id)
            ->call('delete', $msg->id)
            ->assertSet('openId', null);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_acting_on_a_message_someone_else_deleted_reports_rather_than_crashes(): void
    {
        $msg = ContactMessage::factory()->create();
        $id  = $msg->id;
        $msg->delete();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test(AdminContactMessages::class)
            ->call('markHandled', $id)
            ->assertHasErrors('message_' . $id);
    }

    public function test_a_message_survives_the_account_that_sent_it_being_deleted(): void
    {
        $user = User::factory()->create();
        $msg  = ContactMessage::factory()->create(['user_id' => $user->id]);

        $user->delete();

        $msg->refresh();
        $this->assertNull($msg->user_id);
        $this->assertDatabaseCount('contact_messages', 1);
    }
}
