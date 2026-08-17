<div class="mx-auto max-w-3xl px-4 py-10">
    <div class="mb-8 flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-soil-900">Manage Admins</h1>
        <div class="flex gap-3 font-body text-sm">
            <a href="{{ route('admin.creators') }}" class="font-semibold text-leaf-600 hover:underline">Responders →</a>
            <a href="{{ route('admin.questions') }}" class="font-semibold text-leaf-600 hover:underline">Questions →</a>
        </div>
    </div>

    @if (session('admin-users-ok'))
        <p class="mb-6 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
            {{ session('admin-users-ok') }}
        </p>
    @endif

    {{-- ── Invite admin ─────────────────────────────────────────────────────── --}}
    <section class="mb-10">
        <h2 class="mb-3 font-body text-sm font-semibold uppercase tracking-wide text-soil-600">Invite Admin</h2>
        <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
            <p class="mb-4 font-body text-sm text-soil-600">
                Enter the email address of the person you want to make an admin.
                They'll receive a link to set up their account.
            </p>

            <form wire:submit="invite" class="flex gap-3">
                <input type="email" required placeholder="Email address"
                    wire:model="inviteEmail"
                    class="flex-1 rounded-xl border-soil-300 font-body text-sm shadow-sm" />
                <input type="text" placeholder="Name (optional)"
                    wire:model="inviteName"
                    class="w-40 rounded-xl border-soil-300 font-body text-sm shadow-sm" />
                <button type="submit"
                    class="rounded-xl bg-leaf-600 px-4 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500">
                    Invite
                </button>
            </form>

            @error('inviteEmail') <p class="mt-2 font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
        </div>
    </section>

    {{-- ── Current admins ────────────────────────────────────────────────────── --}}
    <section>
        <h2 class="mb-3 font-body text-sm font-semibold uppercase tracking-wide text-soil-600">
            Current Admins ({{ $admins->count() }})
        </h2>

        <div class="divide-y divide-soil-200 rounded-2xl border border-leaf-200 bg-white shadow-sm">
            @foreach ($admins as $a)
                <div class="flex items-center justify-between gap-4 px-5 py-4">
                    <div class="min-w-0">
                        <p class="font-body font-medium text-soil-900">
                            {{ $a->name }}
                            @if ($a->id === $currentUserId)
                                <span class="ml-2 rounded-full bg-leaf-100 px-2 py-0.5 font-body text-xs text-leaf-700">you</span>
                            @endif
                        </p>
                        <p class="font-body text-sm text-soil-500">{{ $a->email }} · joined {{ $a->created_at->diffForHumans() }}</p>
                    </div>
                    @if ($a->id !== $currentUserId)
                        <form wire:submit="revoke({{ $a->id }})">
                            @csrf
                            <button type="submit"
                                class="rounded-lg border border-leaf-200 px-3 py-1.5 font-body text-xs text-soil-600 hover:bg-soil-50">
                                Revoke
                            </button>
                        </form>
                    @endif
                    @error('revoke_' . $a->id)
                        <p class="ml-2 font-body text-xs text-poppy-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>
    </section>
</div>