<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewQuestionNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $creatorName,
        public string $questionPreview,
        public string $questionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New question waiting for an answer — THRP');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.new-question');
    }
}
