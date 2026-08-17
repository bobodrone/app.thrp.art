<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnswerNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $askerName,
        public string $questionPreview,
        public string $questionUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your question has been answered — THRP');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.answer-notification');
    }
}
