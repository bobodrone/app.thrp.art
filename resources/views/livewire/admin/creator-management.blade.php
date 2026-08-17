<div class="mx-auto max-w-4xl px-4 py-10">
    <div class="mb-8 flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-soil-900">Manage Responders</h1>
        <div class="flex gap-3 font-body text-sm">
            <a href="{{ route('admin.users') }}" class="font-semibold text-leaf-600 hover:underline">Admins →</a>
            <a href="{{ route('admin.questions') }}" class="font-semibold text-leaf-600 hover:underline">Questions →</a>
        </div>
    </div>

    @if (session('admin-creators-ok'))
        <p class="mb-6 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
            {{ session('admin-creators-ok') }}
        </p>
    @endif

    {{-- ── Pending applications ─────────────────────────────────────────────── --}}
    <section class="mb-10">
        <h2 class="mb-3 font-body text-sm font-semibold uppercase tracking-wide text-soil-600">
            Pending Applications ({{ $pending->count() }})
        </h2>

        @if ($pending->isEmpty())
            <p class="font-body text-sm text-soil-500">No pending applications.</p>
        @else
            <div class="divide-y divide-soil-200 rounded-2xl border border-leaf-200 bg-white shadow-sm">
                @foreach ($pending as $app)
                    <div class="px-5 py-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="font-body font-medium text-soil-900">{{ $app->name }}</p>
                                <p class="font-body text-sm text-soil-500">{{ $app->email }} · {{ $app->applied_at->diffForHumans() }}</p>
                                <p class="mt-2 font-body text-sm text-soil-700">{{ \Illuminate\Support\Str::limit($app->message, 200) }}</p>
                            </div>
                            <div class="flex shrink-0 flex-col gap-2">
                                <form wire:submit="approve({{ $app->id }})">
                                    @csrf
                                    <button type="submit"
                                        class="w-full rounded-lg bg-leaf-600 px-3 py-1.5 font-body text-xs font-semibold text-white hover:bg-leaf-500">
                                        Approve
                                    </button>
                                </form>
                                <form wire:submit="reject({{ $app->id }})">
                                    @csrf
                                    <label class="flex items-center gap-1.5 font-body text-xs text-soil-500">
                                        <input type="checkbox" wire:model="notifyReject"
                                            class="rounded border-soil-300" />
                                        Notify by email
                                    </label>
                                    <button type="submit"
                                        class="mt-1 w-full rounded-lg border border-leaf-200 px-3 py-1.5 font-body text-xs text-soil-600 hover:bg-soil-50">
                                        Reject
                                    </button>
                                </form>
                                @error('reject_' . $app->id) <p class="font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
                                @error('approve_' . $app->id) <p class="font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ── Direct invite ─────────────────────────────────────────────────────── --}}
    <section class="mb-10">
        <h2 class="mb-3 font-body text-sm font-semibold uppercase tracking-wide text-soil-600">Direct Invite</h2>
        <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
            <p class="mb-4 font-body text-sm text-soil-600">
                Bypass the application queue and invite someone directly. They'll receive a link to set their password.
            </p>

            <form wire:submit="invite" class="flex gap-3">
                <input type="text" required placeholder="Nickname"
                    wire:model="inviteName"
                    class="w-40 rounded-xl border-soil-300 font-body text-sm shadow-sm" />
                <input type="email" required placeholder="Email address"
                    wire:model="inviteEmail"
                    class="flex-1 rounded-xl border-soil-300 font-body text-sm shadow-sm" />
                <button type="submit"
                    class="rounded-xl bg-leaf-600 px-4 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500">
                    Invite
                </button>
            </form>

            @error('inviteName') <p class="mt-2 font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
            @error('inviteEmail') <p class="mt-2 font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
        </div>
    </section>

    {{-- ── Current responders ────────────────────────────────────────────────────--}}
    <section>
        <h2 class="mb-3 font-body text-sm font-semibold uppercase tracking-wide text-soil-600">
            Current Responders ({{ $creators->count() }})
        </h2>

        @if ($creators->isEmpty())
            <p class="font-body text-sm text-soil-500">No responders yet.</p>
        @else
            <div class="divide-y divide-soil-200 rounded-2xl border border-leaf-200 bg-white shadow-sm">
                @foreach ($creators as $c)
                    @php $count = $answeredCounts->get($c->id, 0); @endphp
                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <p class="font-body font-medium text-soil-900">{{ $c->name }}</p>
                            <p class="font-body text-sm text-soil-500">
                                {{ $c->email }} · joined {{ $c->created_at->diffForHumans() }} · {{ $count }} answer{{ $count === 1 ? '' : 's' }}
                            </p>
                        </div>
                        <form wire:submit="revoke({{ $c->id }})">
                            @csrf
                            <button type="submit"
                                class="rounded-lg border border-leaf-200 px-3 py-1.5 font-body text-xs text-soil-600 hover:bg-soil-50">
                                Revoke
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>