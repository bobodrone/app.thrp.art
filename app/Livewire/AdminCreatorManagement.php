<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Mail\ApplicationRejected;
use App\Models\Answer;
use App\Models\CreatorApplication;
use App\Models\User;
use App\Services\UserInviter;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class AdminCreatorManagement extends Component
{
    public string $inviteName  = '';
    public string $inviteEmail = '';
    public bool   $notifyReject = false;

    public function approve(int $applicationId, UserInviter $inviter): void
    {
        $app = CreatorApplication::where('id', $applicationId)
            ->where('status', ApplicationStatus::Pending)
            ->first();

        if (! $app) {
            $this->addError('approve_' . $applicationId, 'Application not found or already reviewed.');
            return;
        }

        $inviter->invite($app->email, $app->name, UserRole::Creator);

        $app->update([
            'status'      => ApplicationStatus::Approved,
            'reviewed_at' => now(),
        ]);

        session()->flash('admin-creators-ok', 'Application approved — invite sent.');
    }

    public function reject(int $applicationId): void
    {
        $app = CreatorApplication::where('id', $applicationId)
            ->where('status', ApplicationStatus::Pending)
            ->first();

        if (! $app) {
            $this->addError('reject_' . $applicationId, 'Application not found or already reviewed.');
            return;
        }

        $app->update([
            'status'      => ApplicationStatus::Rejected,
            'reviewed_at' => now(),
        ]);

        if ($this->notifyReject) {
            Mail::to($app->email)->send(new ApplicationRejected($app->name));
        }

        $this->notifyReject = false;
        session()->flash('admin-creators-ok', 'Application rejected.');
    }

    public function invite(UserInviter $inviter): void
    {
        $this->validate([
            'inviteName'  => ['required', 'string', 'min:2', 'max:40'],
            'inviteEmail' => ['required', 'email'],
        ]);

        $inviter->invite($this->inviteEmail, $this->inviteName, UserRole::Creator);

        $this->reset(['inviteName', 'inviteEmail']);
        session()->flash('admin-creators-ok', 'Creator invited.');
    }

    public function revoke(int $userId): void
    {
        User::whereKey($userId)
            ->where('role', UserRole::Creator)
            ->update(['role' => UserRole::Member]);

        session()->flash('admin-creators-ok', 'Creator access revoked.');
    }

    public function render()
    {
        $pending = CreatorApplication::where('status', ApplicationStatus::Pending)
            ->oldest('applied_at')
            ->get();

        $creators = User::where('role', UserRole::Creator)
            ->oldest('created_at')
            ->get();

        // Answered count per creator — alternatives count too, since writing one
        // is the same work as writing the main answer.
        $answeredCounts = Answer::published()
            ->whereNotNull('created_by')
            ->selectRaw('created_by, count(*) as cnt')
            ->groupBy('created_by')
            ->pluck('cnt', 'created_by');

        return view('livewire.admin.creator-management', [
            'pending'         => $pending,
            'creators'         => $creators,
            'answeredCounts'  => $answeredCounts,
        ])
        ->layout('layouts.app')
        ->title('Manage Creators — Admin — THRP');
    }
}