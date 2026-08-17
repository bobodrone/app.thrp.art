<?php

namespace Tests\Feature;

use App\Mail\ConfirmNewEmail;
use App\Models\PendingEmailChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings');

        $response->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/settings');

        $response->assertRedirect('/login');
    }

    public function test_nickname_can_be_updated(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this
            ->actingAs($user)
            ->from('/settings')
            ->post('/settings/name', ['name' => 'New Name']);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings')
            ->assertSessionHas('status', 'name-updated');

        $this->assertSame('New Name', $user->refresh()->name);
    }

    public function test_nickname_must_be_2_to_40_chars(): void
    {
        $user = User::factory()->create();

        $tooShort = $this->actingAs($user)->post('/settings/name', ['name' => 'a']);
        $tooShort->assertSessionHasErrors(['name']);

        $tooLong = $this->actingAs($user)->post('/settings/name', ['name' => str_repeat('a', 41)]);
        $tooLong->assertSessionHasErrors(['name']);
    }

    public function test_change_email_creates_pending_row_and_sends_confirmation_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'old@example.com']);

        $response = $this
            ->actingAs($user)
            ->from('/settings')
            ->post('/settings/email', ['newEmail' => 'new@example.com']);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings')
            ->assertSessionHas('status', 'email-pending');

        $this->assertDatabaseHas('pending_email_changes', [
            'user_id'   => $user->id,
            'new_email' => 'new@example.com',
        ]);

        Mail::assertSent(ConfirmNewEmail::class, function ($mail) {
            return $mail->envelope()->subject === 'Confirm your new email address'
                && $mail->url !== '';
        });

        // User's current email must be unchanged until the link is clicked
        $this->assertSame('old@example.com', $user->refresh()->email);
    }

    public function test_change_email_rejects_duplicate_or_same_address(): void
    {
        $other = User::factory()->create(['email' => 'taken@example.com']);
        $user  = User::factory()->create(['email' => 'me@example.com']);

        $dup = $this->actingAs($user)->post('/settings/email', ['newEmail' => 'taken@example.com']);
        $dup->assertSessionHasErrors(['newEmail']);

        $same = $this->actingAs($user)->post('/settings/email', ['newEmail' => 'me@example.com']);
        $same->assertSessionHasErrors(['newEmail']);
    }

    public function test_confirm_new_email_swaps_user_email_and_deletes_pending_row(): void
    {
        $user = User::factory()->create(['email' => 'old@example.com']);

        $pending = PendingEmailChange::create([
            'user_id'    => $user->id,
            'new_email'  => 'new@example.com',
            'token'      => 'test-token-123',
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->get('/email/change/test-token-123');

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings')
            ->assertSessionHas('status', 'email-confirmed');

        $this->assertSame('new@example.com', $user->refresh()->email);
        $this->assertNotNull($user->refresh()->email_verified_at);
        $this->assertDatabaseMissing('pending_email_changes', ['id' => $pending->id]);
    }

    public function test_expired_confirm_link_is_rejected(): void
    {
        $user = User::factory()->create();

        PendingEmailChange::create([
            'user_id'    => $user->id,
            'new_email'  => 'new@example.com',
            'token'      => 'expired-token',
            'expires_at' => now()->subHours(1),
        ]);

        $response = $this->get('/email/change/expired-token');

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseMissing('pending_email_changes', ['token' => 'expired-token']);
    }

    public function test_unknown_confirm_link_is_rejected(): void
    {
        $response = $this->get('/email/change/no-such-token');

        $response->assertSessionHasErrors(['email']);
    }
}
