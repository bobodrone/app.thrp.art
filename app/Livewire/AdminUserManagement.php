<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserInviter;
use Livewire\Component;

class AdminUserManagement extends Component
{
    public string $inviteEmail = '';
    public string $inviteName = '';

    public function invite(UserInviter $inviter): void
    {
        $this->validate([
            'inviteEmail' => ['required', 'email'],
            'inviteName'  => ['nullable', 'string', 'max:40'],
        ]);

        $name = trim($this->inviteName) ?: explode('@', $this->inviteEmail)[0];

        $inviter->invite($this->inviteEmail, $name, UserRole::Admin);

        $this->reset(['inviteEmail', 'inviteName']);
        session()->flash('admin-users-ok', 'Admin invited.');
    }

    public function revoke(int $userId): void
    {
        if ($userId === auth()->id()) {
            $this->addError('revoke_' . $userId, 'You cannot revoke your own admin status.');
            return;
        }

        if (User::where('role', UserRole::Admin)->count() <= 1) {
            $this->addError('revoke_' . $userId, 'Cannot remove the last remaining admin.');
            return;
        }

        User::whereKey($userId)->update(['role' => UserRole::Member]);
        session()->flash('admin-users-ok', 'Admin access revoked.');
    }

    public function render()
    {
        $admins = User::where('role', UserRole::Admin)
            ->oldest('created_at')
            ->get();

        return view('livewire.admin.user-management', [
            'admins'       => $admins,
            'currentUserId' => auth()->id(),
        ])
        ->layout('layouts.app')
        ->title('Manage Admins — THRP');
    }
}