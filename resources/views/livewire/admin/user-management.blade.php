@php
    use App\Enums\UserRole;
@endphp

<div class="mx-auto max-w-7xl px-4 py-10">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-soil-900">Users</h1>
        <div class="flex items-center gap-4 font-body text-sm">
            <span class="text-soil-500">
                {{ $users->firstItem() ? ($users->firstItem() . '–' . $users->lastItem()) : '0' }}
                of {{ $users->total() }}
            </span>
            <a href="{{ route('admin.applications') }}" class="font-semibold text-leaf-600 hover:underline">Applications →</a>
            <a href="{{ route('admin.questions') }}" class="font-semibold text-leaf-600 hover:underline">Questions →</a>
        </div>
    </div>

    @if (session('admin-users-ok'))
        <p class="mb-6 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
            {{ session('admin-users-ok') }}
        </p>
    @endif

    {{-- ── Invite ───────────────────────────────────────────────────────────── --}}
    <section class="mb-8">
        <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
            <h2 class="mb-1 font-body text-sm font-semibold uppercase tracking-wide text-soil-600">Invite</h2>
            <p class="mb-4 font-body text-sm text-soil-600">
                They'll receive a link to set their own password. An address that already has an
                account is moved to the chosen role instead of being duplicated.
            </p>

            <form wire:submit="invite" class="flex flex-wrap gap-3">
                <input type="email" required placeholder="Email address"
                    wire:model="inviteEmail"
                    class="min-w-56 flex-1 rounded-xl border-soil-300 font-body text-sm shadow-sm" />
                <input type="text" placeholder="Nickname (optional)"
                    wire:model="inviteName"
                    class="w-44 rounded-xl border-soil-300 font-body text-sm shadow-sm" />
                <select wire:model="inviteRole" class="rounded-xl border-soil-300 font-body text-sm shadow-sm">
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </select>
                <button type="submit"
                    class="rounded-xl bg-leaf-600 px-4 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500">
                    Invite
                </button>
            </form>

            @error('inviteEmail') <p class="mt-2 font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
            @error('inviteName') <p class="mt-2 font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
            @error('inviteRole') <p class="mt-2 font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
        </div>
    </section>

    {{-- ── Filters ──────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search name or email…"
            class="w-64 rounded-xl border-soil-300 font-body text-sm shadow-sm"
        />

        <select wire:model.live="roleFilter" class="rounded-xl border-soil-300 font-body text-sm shadow-sm">
            <option value="">All roles</option>
            @foreach ($roles as $role)
                <option value="{{ $role->value }}">{{ $role->label() }}</option>
            @endforeach
        </select>

        {{-- Blocked accounts are always listed; this narrows the table to them. --}}
        <label class="flex items-center gap-2 font-body text-sm text-soil-600">
            <input type="checkbox" wire:model.live="blockedOnly"
                class="rounded border-soil-300 text-leaf-600 shadow-sm focus:ring-leaf-500" />
            Blocked only
        </label>

        @if ($search || $roleFilter || $blockedOnly)
            <button type="button" wire:click="resetFilters"
                class="font-body text-sm text-soil-500 hover:text-soil-700 hover:underline">
                Reset
            </button>
        @endif
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    @if ($users->isEmpty())
        <div class="rounded-2xl border border-leaf-200 bg-white p-10 text-center font-body text-soil-600">
            No users match your filters.
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-leaf-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-soil-200 font-body text-sm">
                <thead class="bg-soil-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">User</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Role</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Asked</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Joined</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-soil-100">
                    @foreach ($users as $u)
                        @php $isSelf = $u->id === $currentUserId; @endphp
                        <tr @class(['bg-poppy-100/40' => $u->isBlocked()])>
                            <td class="px-4 py-3 align-top">
                                <p class="font-medium text-soil-900">
                                    {{ $u->name }}
                                    @if ($isSelf)
                                        <span class="ml-1 rounded-full bg-leaf-100 px-2 py-0.5 text-xs text-leaf-700">you</span>
                                    @endif
                                    @if ($u->isAnonymised())
                                        <span class="ml-1 rounded-full bg-soil-100 px-2 py-0.5 text-xs text-soil-600">anonymised</span>
                                    @endif
                                </p>
                                <p class="text-soil-500">{{ $u->email }}</p>
                                @php $acceptedAt = $u->termsAcceptedAt(); @endphp
                                @if ($acceptedAt)
                                    <p class="mt-1 text-xs text-leaf-700">✓ Conditions accepted {{ $acceptedAt->format('j M Y') }}</p>
                                @elseif ($u->role === UserRole::Creator)
                                    <p class="mt-1 text-xs text-soil-400">No conditions acceptance on record</p>
                                @endif
                            </td>

                            <td class="px-4 py-3 align-top">
                                @if ($isSelf)
                                    <span class="text-soil-500">{{ $u->role->label() }}</span>
                                @else
                                    <select
                                        class="rounded-lg border-soil-300 py-1 text-xs shadow-sm"
                                        x-on:change="$wire.changeRole({{ $u->id }}, $event.target.value)"
                                    >
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->value }}" @selected($u->role === $role)>{{ $role->label() }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                @error('role_' . $u->id) <p class="mt-1 text-xs text-poppy-600">{{ $message }}</p> @enderror
                            </td>

                            <td class="px-4 py-3 align-top text-soil-600">{{ $u->questions_asked_count }}</td>

                            <td class="px-4 py-3 align-top text-soil-500">{{ $u->created_at->diffForHumans() }}</td>

                            <td class="max-w-xs px-4 py-3 align-top">
                                @if ($u->isBlocked())
                                    <span class="rounded-full bg-poppy-100 px-2 py-0.5 text-xs font-medium text-poppy-600">Blocked</span>
                                    <p class="mt-1 text-xs text-soil-500">
                                        by {{ $u->blockedBy?->name ?? 'an admin' }} · {{ $u->blocked_at->diffForHumans() }}
                                    </p>
                                    @if ($u->blocked_reason)
                                        <p class="mt-1 text-xs italic text-soil-600">“{{ $u->blocked_reason }}”</p>
                                    @endif
                                @else
                                    <span class="text-xs text-soil-400">Active</span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 align-top text-right">
                                <button type="button" wire:click="edit({{ $u->id }})"
                                    class="rounded-lg border border-leaf-200 px-3 py-1.5 text-xs text-soil-600 hover:bg-soil-50">
                                    Edit
                                </button>

                                @unless ($isSelf)
                                    @if ($u->isBlocked())
                                        <button type="button" wire:click="unblock({{ $u->id }})"
                                            class="rounded-lg border border-leaf-200 px-3 py-1.5 text-xs text-leaf-700 hover:bg-leaf-50">
                                            Unblock
                                        </button>
                                    @else
                                        <button type="button" wire:click="confirmBlock({{ $u->id }})"
                                            class="rounded-lg border border-poppy-100 px-3 py-1.5 text-xs text-poppy-600 hover:bg-poppy-100">
                                            Block
                                        </button>
                                    @endif

                                    @unless ($u->isAnonymised())
                                        <button type="button"
                                            wire:click="anonymise({{ $u->id }})"
                                            wire:confirm="Scrub this person's name, email and profile? Their questions and any responses written on them stay up. This cannot be undone."
                                            class="rounded-lg border border-soil-200 px-3 py-1.5 text-xs text-soil-500 hover:bg-soil-50">
                                            Anonymise
                                        </button>
                                    @endunless
                                @endunless

                                @error('block_' . $u->id) <p class="mt-1 text-xs text-poppy-600">{{ $message }}</p> @enderror
                                @error('anonymise_' . $u->id) <p class="mt-1 text-xs text-poppy-600">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $users->links() }}
        </div>
    @endif

    {{-- ── Block modal ──────────────────────────────────────────────────────── --}}
    <div
        x-data="{}"
        x-show="$wire.showBlock"
        x-cloak
        x-on:keydown.escape.window="$wire.showBlock = false"
        x-effect="document.body.classList.toggle('overflow-y-hidden', $wire.showBlock)"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
    >
        <div
            x-show="$wire.showBlock"
            x-transition.opacity
            x-on:click="$wire.showBlock = false"
            class="fixed inset-0 bg-soil-900/50"
        ></div>

        <div
            x-show="$wire.showBlock"
            x-transition
            class="relative z-10 my-auto w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-xl"
        >
            <div class="max-h-[85vh] overflow-y-auto p-6">
                <h2 class="font-display text-lg font-bold text-soil-900">Block account</h2>
                <p class="mt-2 font-body text-sm text-soil-600">
                    They are signed out everywhere immediately and cannot sign in again, or reset
                    their password, until you unblock them. Whatever you write below is what they
                    read when they try. Nothing they have already posted comes down — hide those
                    questions separately if they need to go.
                </p>

                <div class="mt-5">
                    <label for="block-reason" class="mb-1 block font-body text-xs font-medium uppercase tracking-wide text-soil-500">
                        Reason (optional — shown to them at sign-in)
                    </label>
                    <textarea id="block-reason" wire:model="blockReason" rows="4"
                        placeholder="e.g. Repeated advertising posts after a warning."
                        class="w-full rounded-xl border-soil-300 font-body text-sm shadow-sm"></textarea>
                    @error('blockReason') <p class="mt-1 font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" x-on:click="$wire.showBlock = false"
                        class="rounded-xl border border-soil-300 px-4 py-2 font-body text-sm text-soil-600 hover:bg-soil-50">
                        Cancel
                    </button>
                    <button type="button" wire:click="block"
                        class="rounded-xl bg-poppy-600 px-4 py-2 font-body text-sm font-semibold text-white hover:bg-poppy-500">
                        Block account
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Edit nickname modal ──────────────────────────────────────────────── --}}
    <div
        x-data="{}"
        x-show="$wire.showEdit"
        x-cloak
        x-on:keydown.escape.window="$wire.showEdit = false"
        x-effect="document.body.classList.toggle('overflow-y-hidden', $wire.showEdit)"
        class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-6"
        role="dialog"
        aria-modal="true"
    >
        <div
            x-show="$wire.showEdit"
            x-transition.opacity
            x-on:click="$wire.showEdit = false"
            class="fixed inset-0 bg-soil-900/50"
        ></div>

        <div
            x-show="$wire.showEdit"
            x-transition
            class="relative z-10 my-auto w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-xl"
        >
            <div class="max-h-[85vh] overflow-y-auto p-6">
                <h2 class="font-display text-lg font-bold text-soil-900">Edit nickname</h2>
                <p class="mt-2 font-body text-sm text-soil-600">
                    The name shown wherever this person appears on the site. Their email address is
                    theirs to change, from their own settings page.
                </p>

                <div class="mt-5">
                    <input type="text" wire:model="editName"
                        class="w-full rounded-xl border-soil-300 font-body text-sm shadow-sm" />
                    @error('editName') <p class="mt-1 font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" x-on:click="$wire.showEdit = false"
                        class="rounded-xl border border-soil-300 px-4 py-2 font-body text-sm text-soil-600 hover:bg-soil-50">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveEdit"
                        class="rounded-xl bg-leaf-600 px-4 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
