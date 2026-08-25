<?php

namespace App\Livewire;

use App\Models\ContactMessage;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The contact-form inbox.
 *
 * Messages are also emailed out, but mail bounces and shared mailboxes get
 * archived by whoever read them first. This is the copy the whole admin team
 * can see, with a handled flag so two people do not answer the same message.
 */
class AdminContactMessages extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    /** The inbox lists everything by default; this narrows it to open ones. */
    #[Url(as: 'open')]
    public bool $unhandledOnly = false;

    /** Which message is expanded. Only one body is on screen at a time. */
    #[Locked]
    public ?int $openId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingUnhandledOnly(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'unhandledOnly']);
        $this->resetPage();
    }

    public function toggleOpen(int $messageId): void
    {
        $this->openId = $this->openId === $messageId ? null : $messageId;
    }

    public function markHandled(int $messageId): void
    {
        $contactMessage = $this->target($messageId);

        if (! $contactMessage) {
            return;
        }

        // Assigned rather than mass-updated: handled_at/handled_by are kept
        // out of $fillable so nothing the public form posts can reach them.
        $contactMessage->handled_at = now();
        $contactMessage->handled_by = auth()->id();
        $contactMessage->save();

        session()->flash('admin-messages-ok', 'Message marked handled.');
    }

    public function markUnhandled(int $messageId): void
    {
        $contactMessage = $this->target($messageId);

        if (! $contactMessage) {
            return;
        }

        $contactMessage->handled_at = null;
        $contactMessage->handled_by = null;
        $contactMessage->save();

        session()->flash('admin-messages-ok', 'Message reopened.');
    }

    public function delete(int $messageId): void
    {
        $contactMessage = $this->target($messageId);

        if (! $contactMessage) {
            return;
        }

        if ($this->openId === $messageId) {
            $this->openId = null;
        }

        $contactMessage->delete();

        session()->flash('admin-messages-ok', 'Message deleted.');
    }

    protected function target(int $messageId): ?ContactMessage
    {
        $contactMessage = ContactMessage::find($messageId);

        if (! $contactMessage) {
            $this->addError('message_' . $messageId, 'That message no longer exists.');

            return null;
        }

        return $contactMessage;
    }

    public function render()
    {
        $messages = ContactMessage::query()
            ->with(['user:id,name', 'handler:id,name'])
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhere('message', 'like', $term));
            })
            ->when($this->unhandledOnly, fn ($q) => $q->unhandled())
            ->inboxOrder()
            ->paginate(50);

        return view('livewire.admin.contact-messages', [
            'messages'        => $messages,
            'unhandledCount'  => ContactMessage::unhandled()->count(),
        ])
            ->layout('layouts.app')
            ->title('Messages — Admin — THRP');
    }
}
