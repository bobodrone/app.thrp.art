<?php

namespace Tests\Feature;

use App\Jobs\NotifyAdminsOfContactMessage;
use App\Livewire\ContactForm;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The array cache store is shared across tests in a run, and every
        // test submits from the same fake IP — so the limiter has to start
        // each test empty or the later ones inherit earlier attempts.
        RateLimiter::clear('contact:hour:' . hash('sha256', '127.0.0.1'));
        RateLimiter::clear('contact:day:' . hash('sha256', '127.0.0.1'));

        // The time trap is exercised in its own test; elsewhere it would just
        // mean sleeping for three seconds in every single case.
        config(['contact.spam.min_seconds' => 0]);
    }

    private function fill(Testable $component): Testable
    {
        return $component
            ->set('name', 'Ada Lovelace')
            ->set('email', 'ada@example.com')
            ->set('subject', 'A question about responses')
            ->set('message', 'I sent a question last week and I wondered how long responses usually take to arrive.');
    }

    // ── The page ──────────────────────────────────────────────────────────

    public function test_contact_page_renders_for_guests(): void
    {
        $this->get('/contact')->assertOk()->assertSee('Get in');
    }

    public function test_contact_page_renders_for_members(): void
    {
        $this->actingAs(User::factory()->create())->get('/contact')->assertOk();
    }

    public function test_contact_link_is_in_the_navigation(): void
    {
        $this->get('/')->assertOk()->assertSee(route('contact'));
    }

    public function test_signed_in_users_get_their_details_prefilled(): void
    {
        $user = User::factory()->create(['name' => 'Grace', 'email' => 'grace@example.com']);

        Livewire::actingAs($user)
            ->test(ContactForm::class)
            ->assertSet('name', 'Grace')
            ->assertSet('email', 'grace@example.com');
    }

    // ── Submitting ────────────────────────────────────────────────────────

    public function test_guest_can_send_a_message(): void
    {
        Queue::fake();

        $this->fill(Livewire::test(ContactForm::class))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('contact_messages', [
            'name'    => 'Ada Lovelace',
            'email'   => 'ada@example.com',
            'subject' => 'A question about responses',
            'user_id' => null,
        ]);

        Queue::assertPushed(NotifyAdminsOfContactMessage::class);
    }

    public function test_message_from_a_member_is_linked_to_their_account(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->fill(Livewire::actingAs($user)->test(ContactForm::class))
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseHas('contact_messages', ['user_id' => $user->id]);
    }

    public function test_the_senders_ip_is_stored_hashed_not_raw(): void
    {
        Queue::fake();

        $this->fill(Livewire::test(ContactForm::class))->call('submit');

        $stored = ContactMessage::sole();

        $this->assertSame(hash('sha256', '127.0.0.1'), $stored->ip_hash);
        $this->assertStringNotContainsString('127.0.0.1', (string) $stored->ip_hash);
    }

    public function test_validation_rejects_empty_and_short_fields(): void
    {
        Livewire::test(ContactForm::class)
            ->set('name', 'a')
            ->set('email', 'not-an-email')
            ->set('subject', '')
            ->set('message', 'too short')
            ->call('submit')
            ->assertHasErrors(['name', 'email', 'subject', 'message'])
            ->assertSet('submitted', false);

        $this->assertDatabaseCount('contact_messages', 0);
    }

    // ── Spam guards ───────────────────────────────────────────────────────

    public function test_honeypot_submission_is_silently_discarded(): void
    {
        Queue::fake();

        $this->fill(Livewire::test(ContactForm::class))
            ->set('website', 'http://spam.example')
            ->call('submit')
            // The bot is shown the success screen so it learns nothing.
            ->assertSet('submitted', true)
            ->assertHasNoErrors();

        $this->assertDatabaseCount('contact_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_submission_faster_than_a_human_is_rejected(): void
    {
        Queue::fake();
        config(['contact.spam.min_seconds' => 30]);

        $this->fill(Livewire::test(ContactForm::class))
            ->call('submit')
            ->assertHasErrors('message')
            ->assertSet('submitted', false);

        $this->assertDatabaseCount('contact_messages', 0);
        Queue::assertNothingPushed();
    }

    public function test_the_timer_cannot_be_wound_back_from_the_browser(): void
    {
        config(['contact.spam.min_seconds' => 30]);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(ContactForm::class)->set('startedAt', time() - 3600);
    }

    public function test_rate_limit_stops_the_fourth_message_from_one_address(): void
    {
        Queue::fake();
        config(['contact.spam.max_per_hour' => 3]);

        foreach (range(1, 3) as $i) {
            $this->fill(Livewire::test(ContactForm::class))
                ->set('subject', 'Message number ' . $i)
                ->call('submit')
                ->assertHasNoErrors()
                ->assertSet('submitted', true);
        }

        $this->fill(Livewire::test(ContactForm::class))
            ->set('subject', 'Message number 4')
            ->call('submit')
            ->assertHasErrors('message')
            ->assertSet('submitted', false);

        $this->assertDatabaseCount('contact_messages', 3);
    }

    public function test_failed_validation_does_not_burn_the_rate_limit(): void
    {
        Queue::fake();
        config(['contact.spam.max_per_hour' => 1]);

        // Five rejected attempts...
        foreach (range(1, 5) as $ignored) {
            Livewire::test(ContactForm::class)->set('message', 'nope')->call('submit')->assertHasErrors();
        }

        // ...still leave the one real message they were allowed.
        $this->fill(Livewire::test(ContactForm::class))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertDatabaseCount('contact_messages', 1);
    }

    // ── Delivery ──────────────────────────────────────────────────────────

    public function test_message_is_mailed_to_the_configured_address(): void
    {
        Mail::fake();
        config(['contact.to' => ['hello@thrp.example']]);
        User::factory()->admin()->create(['email' => 'admin@thrp.example']);

        $this->fill(Livewire::test(ContactForm::class))->call('submit');

        Mail::assertSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo('hello@thrp.example'));
        Mail::assertNotSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo('admin@thrp.example'));
    }

    public function test_message_falls_back_to_every_admin_when_no_address_is_configured(): void
    {
        Mail::fake();
        config(['contact.to' => []]);

        $adminOne = User::factory()->admin()->create();
        $adminTwo = User::factory()->admin()->create();
        $member   = User::factory()->create();

        $this->fill(Livewire::test(ContactForm::class))->call('submit');

        Mail::assertSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo($adminOne->email));
        Mail::assertSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo($adminTwo->email));
        Mail::assertNotSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo($member->email));
    }

    public function test_blocked_admins_stop_receiving_contact_mail(): void
    {
        Mail::fake();
        config(['contact.to' => []]);

        $active  = User::factory()->admin()->create();
        $blocked = User::factory()->admin()->blocked()->create();

        $this->fill(Livewire::test(ContactForm::class))->call('submit');

        Mail::assertSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo($active->email));
        Mail::assertNotSent(ContactMessageReceived::class, fn ($mail) => $mail->hasTo($blocked->email));
    }

    public function test_the_message_is_still_stored_when_there_is_nobody_to_mail(): void
    {
        Mail::fake();
        config(['contact.to' => []]);

        $this->fill(Livewire::test(ContactForm::class))
            ->call('submit')
            ->assertSet('submitted', true);

        $this->assertDatabaseCount('contact_messages', 1);
        Mail::assertNothingSent();
    }

    /**
     * Renders through a real transport rather than Mail::fake(), which records
     * the mailable without ever rendering it — the blind spot TASK-4 documents
     * for ApplicationReceived's $message property.
     */
    public function test_the_notification_email_renders_and_carries_the_message(): void
    {
        config(['contact.to' => ['hello@thrp.example']]);

        $this->fill(Livewire::test(ContactForm::class))->call('submit');

        $sent = Mail::mailer()->getSymfonyTransport()->messages();

        $this->assertCount(1, $sent);

        $email = $sent->first()->getOriginalMessage();
        $body  = $email->getHtmlBody();

        $this->assertStringContainsString('A question about responses', $email->getSubject());
        $this->assertStringContainsString('how long responses usually take', $body);
        $this->assertStringContainsString('Ada Lovelace', $body);
        $this->assertSame('ada@example.com', $email->getReplyTo()[0]->getAddress());
    }
}
