<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The message text is deliberately NOT called $message: Laravel's mailer
     * injects its own $message (an Illuminate\Mail\Message) into every mail
     * view, which would shadow the property and blow up on render — the bug
     * TASK-4 records against ApplicationReceived.
     */
    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $subjectLine,
        public string $body,
        public string $inboxUrl,
        public bool $fromMember = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Contact form: ' . $this->subjectLine,
            // So an admin can just hit reply and land in the sender's inbox.
            replyTo: [new Address($this->senderEmail, $this->senderName)],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-message');
    }
}
