<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Jobs\NotifyAdminsOfNewApplication;
use App\Models\CreatorApplication;
use Illuminate\Http\RedirectResponse;
use Livewire\Component;

class CreatorApplicationForm extends Component
{
    public string $name    = '';
    public string $email   = '';
    public string $message = '';
    public bool   $submitted = false;

    protected function rules(): array
    {
        return [
            'email'   => ['required', 'email'],
            'name'     => ['required', 'string', 'min:2', 'max:40'],
            'message'  => ['required', 'string', 'min:20', 'max:500'],
        ];
    }

    public function mount(): void
    {
        // Creators and admins have no reason to apply
        $user = auth()->user();
        if ($user && in_array($user->role->value, [UserRole::Creator->value, UserRole::Admin->value], true)) {
            $this->redirect(route('home'), navigate: true);
        }
    }

    public function submit(): void
    {
        $this->validate();

        // Prevent duplicates — same email with status 'pending' or 'approved'
        $existing = CreatorApplication::where('email', $this->email)
            ->latest('applied_at')
            ->first();

        if ($existing && $existing->status === ApplicationStatus::Pending) {
            $this->addError('email', 'We already have a pending application from this email address.');
            return;
        }
        if ($existing && $existing->status === ApplicationStatus::Approved) {
            $this->addError('email', 'An account with this email has already been approved as a creator.');
            return;
        }

        CreatorApplication::create([
            'name'    => $this->name,
            'email'   => $this->email,
            'message' => $this->message,
            'status'  => ApplicationStatus::Pending,
        ]);

        NotifyAdminsOfNewApplication::dispatch($this->email, $this->name, $this->message);

        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.apply.form')
            ->layout('layouts.app')
            ->title('Become a Creator — THRP');
    }
}