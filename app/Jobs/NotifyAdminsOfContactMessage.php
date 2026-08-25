<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Delivers a contact-form message to whoever is on the receiving end.
 *
 * config('contact.to') wins when it is set — one shared inbox is usually what
 * a project this size wants. With it empty we fall back to every admin
 * account, so an unset env var means "too much mail" rather than "no mail".
 */
class NotifyAdminsOfContactMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ContactMessage $contactMessage) {}

    public function handle(): void
    {
        $recipients = $this->recipients();

        if ($recipients === []) {
            return;
        }

        $mailable = new ContactMessageReceived(
            senderName: $this->contactMessage->name,
            senderEmail: $this->contactMessage->email,
            subjectLine: $this->contactMessage->subject,
            body: $this->contactMessage->message,
            inboxUrl: config('app.url') . route('admin.messages', [], false),
            fromMember: $this->contactMessage->user_id !== null,
        );

        foreach ($recipients as $recipient) {
            Mail::to($recipient)->send(clone $mailable);
        }
    }

    /**
     * @return array<int, string|User>
     */
    protected function recipients(): array
    {
        $configured = config('contact.to', []);

        if ($configured !== []) {
            return $configured;
        }

        // Blocked admins keep their role but should not keep getting the mail.
        return User::where('role', UserRole::Admin)
            ->whereNull('blocked_at')
            ->get()
            ->all();
    }
}
