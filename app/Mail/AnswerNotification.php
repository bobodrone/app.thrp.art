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
        /**
         * Set when an answer already sent to this asker has been rewritten and
         * whoever edited it chose to say so. Deliberately says nothing about
         * who made the change, so the same mail reads correctly whether it was
         * the responder or an admin.
         */
        public bool $edited = false,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->edited
            ? 'A response to your question has been updated — THRP'
            : 'Your question has a response — THRP');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.answer-notification');
    }
}
