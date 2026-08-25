<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApplicationReceived extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The applicant's text is deliberately NOT called $message: Laravel's
     * mailer injects its own $message (an Illuminate\Mail\Message) into every
     * mail view, and that injection wins over a mailable's public properties.
     * Naming it $message here meant the blade echoed the Message object and
     * threw at render time, so no admin was ever told an application had
     * arrived — TASK-4. Do not rename it back.
     */
    public function __construct(
        public string $applicantName,
        public string $applicantEmail,
        public string $applicantMessage,
        public string $reviewUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New responder application from ' . $this->applicantName . ' — THRP');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.application-received');
    }
}
