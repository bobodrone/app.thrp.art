<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\NotifyAdminsOfNewApplication;
use App\Mail\ApplicationReceived;
use App\Mail\ApplicationRejected;
use App\Mail\ContactMessageReceived;
use App\Mail\UserRoleInvite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionProperty;
use Tests\TestCase;

/**
 * Guards against a whole class of bug rather than one instance of it.
 *
 * Laravel's mailer injects its own $message — an Illuminate\Mail\Message — into
 * every mail view, and that injection beats a mailable's public properties. A
 * mailable that names a property $message therefore renders the object instead
 * of the text and throws, but only at render time. Mail::fake() records a
 * mailable without ever rendering it, so the whole existing suite was blind to
 * it and ApplicationReceived shipped broken (TASK-4): every responder
 * application arrived with no admin told about it.
 *
 * These tests render through a real transport, which is the only way to see it.
 */
class MailablesRenderTest extends TestCase
{
    use RefreshDatabase;

    /** Sends through the array transport and hands back the rendered HTML. */
    private function renderThroughTransport(Mailable $mailable): string
    {
        Mail::to('admin@thrp.invalid')->send($mailable);

        $sent = Mail::mailer()->getSymfonyTransport()->messages();

        $this->assertCount(1, $sent, class_basename($mailable) . ' was not sent');

        return (string) $sent->last()->getOriginalMessage()->getHtmlBody();
    }

    /**
     * The original TASK-4 reproduction: this threw a ViewException on
     * htmlspecialchars() before the property was renamed.
     */
    public function test_application_received_renders_and_carries_the_applicants_message(): void
    {
        $body = $this->renderThroughTransport(new ApplicationReceived(
            applicantName: 'Ada Lovelace',
            applicantEmail: 'ada@example.com',
            applicantMessage: 'I would like to help out with illustrations.',
            reviewUrl: 'http://thrp.invalid/admin/applications',
        ));

        $this->assertStringContainsString('Ada Lovelace', $body);
        $this->assertStringContainsString('ada@example.com', $body);
        $this->assertStringContainsString('I would like to help out with illustrations.', $body);
        $this->assertStringContainsString('http://thrp.invalid/admin/applications', $body);

        // The symptom, spelled out: the injected Message object stringifies to
        // a class name, so its absence is what proves the shadowing is gone.
        $this->assertStringNotContainsString('Illuminate\Mail\Message', $body);
    }

    /**
     * The production path, end to end and unfaked: the bug's real symptom was
     * the queued job throwing, so an admin was never told an application had
     * arrived. Asserting on the mailable alone would not have caught a caller
     * still passing the old parameter name.
     */
    public function test_the_new_application_job_delivers_a_renderable_email_to_admins(): void
    {
        $admin = User::factory()->admin()->create();

        (new NotifyAdminsOfNewApplication(
            email: 'ada@example.com',
            name: 'Ada Lovelace',
            message: 'I would like to help out with illustrations.',
        ))->handle();

        $sent = Mail::mailer()->getSymfonyTransport()->messages();

        $this->assertCount(1, $sent);

        $email = $sent->first()->getOriginalMessage();

        $this->assertSame($admin->email, $email->getTo()[0]->getAddress());
        $this->assertStringContainsString(
            'I would like to help out with illustrations.',
            (string) $email->getHtmlBody(),
        );
    }

    /**
     * Every other mailable whose constructor can be satisfied here, rendered
     * for real — the rest of AC #4. A mailable with no arguments to invent is
     * still worth rendering: shadowing is not the only way a mail view throws.
     */
    public function test_the_other_mailables_render_through_a_real_transport(): void
    {
        $mailables = [
            new ApplicationRejected('Ada Lovelace'),
            new UserRoleInvite(UserRole::Creator, 'http://thrp.invalid/reset'),
            new ContactMessageReceived(
                senderName: 'Grace Hopper',
                senderEmail: 'grace@example.com',
                subjectLine: 'A question',
                body: 'How long do responses usually take?',
                inboxUrl: 'http://thrp.invalid/admin/messages',
            ),
        ];

        foreach ($mailables as $mailable) {
            $rendered = $this->renderThroughTransport($mailable);

            $this->assertNotSame('', trim($rendered), class_basename($mailable) . ' rendered empty');
            $this->assertStringNotContainsString('Illuminate\Mail\Message', $rendered);

            Mail::mailer()->getSymfonyTransport()->flush();
        }
    }

    /**
     * The durable half of AC #4. Renaming one property fixes today's bug; this
     * fails the build the next time anyone reintroduces the reserved name,
     * including on a mailable nobody thought to render above.
     */
    public function test_no_mailable_declares_a_property_named_message(): void
    {
        $offenders = [];

        foreach ($this->mailableClasses() as $class) {
            foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->getDeclaringClass()->getName() !== $class) {
                    continue; // Inherited from Mailable itself, not ours to police.
                }

                if ($property->getName() === 'message') {
                    $offenders[] = $class;
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_map(
            fn (string $class) => $class . ' declares a public $message, which Laravel\'s mailer '
                . 'shadows with its own Illuminate\Mail\Message. Rename it.',
            $offenders,
        )));
    }

    /** Every Mailable under app/Mail. */
    private function mailableClasses(): array
    {
        $classes = [];

        foreach (glob(app_path('Mail/*.php')) as $file) {
            $class = 'App\\Mail\\' . Str::before(basename($file), '.php');

            if (is_subclass_of($class, Mailable::class)) {
                $classes[] = $class;
            }
        }

        $this->assertNotEmpty($classes, 'No mailables found — has app/Mail moved?');

        return $classes;
    }
}
