<?php

namespace App\Livewire;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\UserInviter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every account in one table, whatever its role.
 *
 * This page used to manage admins only, with responders managed on their own
 * page and members visible nowhere at all — so the person who needed blocking
 * was the one person an admin could not find. Role is now a column and a
 * filter rather than a reason to have three lists.
 */
class AdminUserManagement extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'role')]
    public string $roleFilter = '';

    /** Blocked accounts are listed by default; this narrows the table to them. */
    #[Url(as: 'blocked')]
    public bool $blockedOnly = false;

    public string $inviteEmail = '';

    public string $inviteName = '';

    public string $inviteRole = UserRole::Creator->value;

    #[Locked]
    public ?int $blockingId = null;

    public string $blockReason = '';

    public bool $showBlock = false;

    #[Locked]
    public ?int $editingId = null;

    public string $editName = '';

    public bool $showEdit = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatingBlockedOnly(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'roleFilter', 'blockedOnly']);
        $this->resetPage();
    }

    // ── Invites ───────────────────────────────────────────────────────────

    public function invite(UserInviter $inviter): void
    {
        $this->validate([
            'inviteEmail' => ['required', 'email'],
            'inviteName'  => ['nullable', 'string', 'max:40'],
            'inviteRole'  => ['required', 'in:' . implode(',', $this->roleValues())],
        ]);

        $role = UserRole::from($this->inviteRole);
        $name = trim($this->inviteName) ?: explode('@', $this->inviteEmail)[0];

        $inviter->invite($this->inviteEmail, $name, $role);

        $this->reset(['inviteEmail', 'inviteName']);
        session()->flash('admin-users-ok', $role->label() . ' invited.');
    }

    // ── Role ──────────────────────────────────────────────────────────────

    public function changeRole(int $userId, string $role): void
    {
        $key = 'role_' . $userId;

        if (! in_array($role, $this->roleValues(), true)) {
            $this->addError($key, 'Unknown role.');

            return;
        }

        $user = $this->target($userId, $key);

        if (! $user) {
            return;
        }

        $new = UserRole::from($role);

        if ($new !== UserRole::Admin && ! $this->canLoseAdmin($user, $key)) {
            return;
        }

        $user->update(['role' => $new]);
        session()->flash('admin-users-ok', $user->name . ' is now a ' . strtolower($new->label()) . '.');
    }

    // ── Blocking ──────────────────────────────────────────────────────────

    /**
     * Open the block dialog. The reason is asked for here rather than assumed,
     * but stays optional — not every block needs a word.
     */
    public function confirmBlock(int $userId): void
    {
        $user = $this->target($userId, 'block_' . $userId);

        if (! $user || ! $this->canLoseAdmin($user, 'block_' . $userId)) {
            return;
        }

        $this->blockingId  = $user->id;
        $this->blockReason = $user->blocked_reason ?? '';

        $this->resetErrorBag();
        $this->showBlock = true;
    }

    public function block(): void
    {
        $this->validate(
            ['blockReason' => ['nullable', 'string', 'max:1000']],
            ['blockReason.max' => 'Reason must be 1000 characters or fewer.'],
        );

        $user = $this->target($this->blockingId, 'block_' . $this->blockingId);

        if (! $user || ! $this->canLoseAdmin($user, 'block_' . $this->blockingId)) {
            return;
        }

        // Written for the blocked person: this is the text they read on the
        // sign-in screen, not an internal note.
        $user->block(auth()->user(), $this->blockReason);

        $this->reset(['blockingId', 'blockReason', 'showBlock']);
        session()->flash('admin-users-ok', $user->name . ' is blocked and signed out of every device.');
    }

    public function unblock(int $userId): void
    {
        $user = User::findOrFail($userId);

        $user->unblock();

        session()->flash('admin-users-ok', $user->name . ' can sign in again.');
    }

    // ── Name ──────────────────────────────────────────────────────────────

    public function edit(int $userId): void
    {
        $user = User::findOrFail($userId);

        $this->editingId = $user->id;
        $this->editName  = $user->name;

        $this->resetErrorBag();
        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        $this->validate(
            ['editName' => ['required', 'string', 'min:2', 'max:40']],
            [
                'editName.required' => 'A nickname is required.',
                'editName.min'      => 'Nickname must be 2–40 characters.',
                'editName.max'      => 'Nickname must be 2–40 characters.',
            ],
        );

        User::findOrFail($this->editingId)->update(['name' => trim($this->editName)]);

        $this->reset(['editingId', 'editName', 'showEdit']);
        session()->flash('admin-users-ok', 'Nickname updated.');
    }

    // ── Anonymising ───────────────────────────────────────────────────────

    /**
     * The delete button, in the only form that does not take other people's
     * work with it — see User::anonymise().
     */
    public function anonymise(int $userId): void
    {
        $key = 'anonymise_' . $userId;

        $user = $this->target($userId, $key);

        if (! $user || ! $this->canLoseAdmin($user, $key)) {
            return;
        }

        $user->anonymise();

        session()->flash('admin-users-ok', 'Account anonymised — their questions and responses are still there.');
    }

    // ── Guards ────────────────────────────────────────────────────────────

    /**
     * The user this action is aimed at, or null with an error already set.
     * Nothing an admin does here may be aimed at themselves: the buttons are
     * hidden on their own row, and this is what holds if one is called anyway.
     */
    private function target(?int $userId, string $errorKey): ?User
    {
        if ($userId === auth()->id()) {
            $this->addError($errorKey, 'You cannot do that to your own account.');

            return null;
        }

        return User::findOrFail($userId);
    }

    /** The site must keep at least one admin who can actually sign in. */
    private function canLoseAdmin(User $user, string $errorKey): bool
    {
        if ($user->role !== UserRole::Admin) {
            return true;
        }

        if (User::where('role', UserRole::Admin)->notBlocked()->count() > 1) {
            return true;
        }

        $this->addError($errorKey, 'Cannot remove the last remaining admin.');

        return false;
    }

    /** @return array<int, string> */
    private function roleValues(): array
    {
        return array_map(fn (UserRole $r) => $r->value, UserRole::cases());
    }

    public function render()
    {
        $users = User::query()
            ->with(['blockedBy:id,name', 'applications:email,terms_accepted_at'])
            ->withCount('questionsAsked')
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->when(in_array($this->roleFilter, $this->roleValues(), true), function ($q) {
                $q->where('role', $this->roleFilter);
            })
            ->when($this->blockedOnly, fn ($q) => $q->blocked())
            ->orderByDesc('created_at')
            ->paginate(100);

        return view('livewire.admin.user-management', [
            'users'         => $users,
            'currentUserId' => auth()->id(),
            'roles'         => UserRole::cases(),
        ])
            ->layout('layouts.app')
            ->title('Users — Admin — THRP');
    }
}
