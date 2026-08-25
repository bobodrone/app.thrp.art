<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\AdminUserManagement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Blocking an account.
 *
 * A block is not a role change and not a deletion: it shuts the door and says
 * why, leaves everything the person wrote standing, and can be undone without
 * having to remember what they used to be.
 */
class BlockUserTest extends TestCase
{
    use RefreshDatabase;

    // ── The admin action ──────────────────────────────────────────────────

    public function test_an_admin_can_block_a_user_with_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->creator()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('confirmBlock', $user->id)
            ->assertSet('showBlock', true)
            ->set('blockReason', 'Repeated advertising posts after a warning.')
            ->call('block')
            ->assertHasNoErrors()
            ->assertSet('showBlock', false);

        $fresh = $user->fresh();
        $this->assertTrue($fresh->isBlocked());
        $this->assertSame($admin->id, $fresh->blocked_by);
        $this->assertSame('Repeated advertising posts after a warning.', $fresh->blocked_reason);
        // Blocking is orthogonal to the role — unblocking has to put them back.
        $this->assertSame(UserRole::Creator, $fresh->role);
    }

    public function test_the_reason_is_optional(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('confirmBlock', $user->id)
            ->call('block')
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->isBlocked());
        $this->assertNull($user->fresh()->blocked_reason);
    }

    public function test_a_whitespace_only_reason_is_stored_as_none(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('confirmBlock', $user->id)
            ->set('blockReason', "   \n  ")
            ->call('block')
            ->assertHasNoErrors();

        $this->assertNull($user->fresh()->blocked_reason);
    }

    public function test_an_over_long_reason_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('confirmBlock', $user->id)
            ->set('blockReason', str_repeat('a', 1001))
            ->call('block')
            ->assertHasErrors('blockReason');

        $this->assertFalse($user->fresh()->isBlocked());
    }

    public function test_unblocking_clears_every_trace_of_it(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->blocked($admin, 'Spam.')->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('unblock', $user->id)
            ->assertHasNoErrors();

        $fresh = $user->fresh();
        $this->assertFalse($fresh->isBlocked());
        $this->assertNull($fresh->blocked_by);
        $this->assertNull($fresh->blocked_reason);
    }

    public function test_blocking_sends_no_mail(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('confirmBlock', $user->id)
            ->call('block');

        Mail::assertNothingSent();
    }

    public function test_blocked_only_narrows_the_table(): void
    {
        $admin   = User::factory()->admin()->create();
        $blocked = User::factory()->blocked($admin)->create(['name' => 'Bad Barry']);
        User::factory()->create(['name' => 'Good Grace']);

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->assertSee('Bad Barry')
            ->assertSee('Good Grace')
            ->set('blockedOnly', true)
            ->assertSee('Bad Barry')
            ->assertDontSee('Good Grace');
    }

    public function test_the_table_shows_who_blocked_them_and_why(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $user  = User::factory()->blocked($admin, 'Repeated advertising posts.')
            ->create(['name' => 'Bad Barry']);

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('Bad Barry')
            ->assertSee('Blocked')
            ->assertSee('by Ada Admin')
            ->assertSee('Repeated advertising posts.');
    }

    // ── Guards ────────────────────────────────────────────────────────────

    public function test_an_admin_cannot_block_themselves(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(AdminUserManagement::class)
            ->call('confirmBlock', $admin->id)
            ->assertHasErrors(['block_' . $admin->id])
            ->assertSet('showBlock', false);

        $this->assertFalse($admin->fresh()->isBlocked());
    }

    public function test_the_last_remaining_admin_cannot_be_blocked(): void
    {
        $a1 = User::factory()->admin()->create();
        $a2 = User::factory()->admin()->create();

        // With two admins, blocking one is allowed.
        Livewire::actingAs($a1)
            ->test(AdminUserManagement::class)
            ->call('confirmBlock', $a2->id)
            ->call('block')
            ->assertHasNoErrors();

        $this->assertTrue($a2->fresh()->isBlocked());

        // The one left over cannot go the same way.
        Livewire::actingAs($a1)
            ->test(AdminUserManagement::class)
            ->call('confirmBlock', $a1->id)
            ->assertHasErrors(['block_' . $a1->id]);

        $this->assertFalse($a1->fresh()->isBlocked());
    }

    // ── What a block actually does ────────────────────────────────────────

    public function test_a_blocked_user_cannot_sign_in_and_is_told_why(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->blocked($admin, 'Repeated spam.')->create([
            'email' => 'blocked@example.com',
        ]);

        $this->post('/login', ['email' => 'blocked@example.com', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertStringContainsString(
            'Repeated spam.',
            session('errors')->first('email'),
        );
    }

    public function test_a_wrong_password_never_reveals_that_an_account_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->blocked($admin, 'Repeated spam.')->create(['email' => 'blocked@example.com']);

        $this->post('/login', ['email' => 'blocked@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertStringNotContainsString('Repeated spam.', session('errors')->first('email'));
        $this->assertStringNotContainsString('blocked', session('errors')->first('email'));
    }

    public function test_an_unblocked_user_can_sign_in_again(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->blocked($admin, 'Spam.')->create(['email' => 'back@example.com']);

        $user->unblock();

        $this->post('/login', ['email' => 'back@example.com', 'password' => 'password']);

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_an_open_session_dies_on_the_next_request(): void
    {
        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create(['email' => 'live@example.com']);

        // A real sign-in, not actingAs(): the point of this test is that the
        // session is re-read from the database on the next request.
        $this->post('/login', ['email' => 'live@example.com', 'password' => 'password']);
        $this->assertAuthenticated();
        $this->get('/my-questions')->assertOk();

        $user->block($admin, 'Off you go.');

        // What the next request does — resolve the signed-in user afresh.
        Auth::guard('web')->forgetUser();

        $this->get('/my-questions')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_blocking_deletes_the_persons_stored_sessions(): void
    {
        // Sessions are an array in the test environment; this is the production
        // driver, which is what block() reaches for.
        config(['session.driver' => 'database']);

        $admin = User::factory()->admin()->create();
        $user  = User::factory()->create();

        foreach (['sess-one', 'sess-two'] as $id) {
            DB::table('sessions')->insert([
                'id'            => $id,
                'user_id'       => $user->id,
                'payload'       => '',
                'last_activity' => now()->timestamp,
            ]);
        }

        DB::table('sessions')->insert([
            'id'            => 'someone-else',
            'user_id'       => $admin->id,
            'payload'       => '',
            'last_activity' => now()->timestamp,
        ]);

        $user->block($admin);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $admin->id)->count());
    }

    public function test_a_blocked_user_cannot_ask_for_a_password_reset(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->blocked($admin, 'Spam.')->create(['email' => 'blocked@example.com']);

        $this->post('/forgot-password', ['email' => 'blocked@example.com'])
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString('Spam.', session('errors')->first('email'));
    }

    public function test_a_blocked_responder_leaves_the_public_directory(): void
    {
        $admin   = User::factory()->admin()->create();
        $blocked = User::factory()->creator()->blocked($admin)->create(['name' => 'Gone Gary']);
        $active  = User::factory()->creator()->create(['name' => 'Here Hana']);

        $this->get('/responders')
            ->assertOk()
            ->assertSee('Here Hana')
            ->assertDontSee('Gone Gary');

        $this->get("/responders/{$blocked->id}")->assertNotFound();
        $this->get("/responders/{$active->id}")->assertOk();
    }
}
