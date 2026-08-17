<?php

namespace App\Mail;

use App\Enums\UserRole;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserRoleInvite extends Mailable
{
    use Queueable, SerializesModels;

    public string $roleLabel;

    public function __construct(public UserRole $role, public ?string $passwordResetUrl = null)
    {
        $this->roleLabel = $role->label();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You have been added to THRP as a ' . $this->roleLabel);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.invite');
    }
}