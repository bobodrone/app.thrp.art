<?php

namespace App\Livewire;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Mail\ApplicationRejected;
use App\Models\CreatorApplication;
use App\Services\UserInviter;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

/**
 * The responder application inbox.
 *
 * Inviting people and managing the accounts that result both live on the users
 * page now — this queue is applications, which are not user rows and never
 * belonged in a table of them.
 */
class AdminApplications extends Component
{
    public bool $notifyReject = false;

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

        session()->flash('admin-applications-ok', 'Application approved — invite sent.');
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
        session()->flash('admin-applications-ok', 'Application rejected.');
    }

    public function render()
    {
        $pending = CreatorApplication::where('status', ApplicationStatus::Pending)
            ->oldest('applied_at')
            ->get();

        return view('livewire.admin.applications', [
            'pending' => $pending,
        ])
            ->layout('layouts.app')
            ->title('Responder Applications — Admin — THRP');
    }
}
