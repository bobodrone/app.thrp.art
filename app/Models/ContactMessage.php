<?php

namespace App\Models;

use Database\Factories\ContactMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message sent through the public contact form.
 *
 * Rows are kept even though the message is also emailed out: mail can bounce
 * or a Resend outage can swallow it, and an admin inbox that only exists in
 * someone's mailbox is not an inbox anyone else can pick up.
 */
class ContactMessage extends Model
{
    /** @use HasFactory<ContactMessageFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'email', 'subject', 'message', 'ip_hash'];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    /** The account that sent it, when it was not a logged-out visitor. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The admin who dealt with it. */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isHandled(): bool
    {
        return $this->handled_at !== null;
    }

    /** Inbox order: anything still open first, newest first within each group. */
    public function scopeInboxOrder(Builder $q): Builder
    {
        return $q->orderByRaw('handled_at is null desc')->latest('created_at');
    }

    public function scopeUnhandled(Builder $q): Builder
    {
        return $q->whereNull('handled_at');
    }
}
