<div class="mx-auto max-w-7xl px-4 py-10">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-soil-900">
            Messages
            @if ($unhandledCount > 0)
                <span class="ml-2 align-middle rounded-full bg-poppy-100 px-2.5 py-0.5 font-body text-sm font-medium text-poppy-600">
                    {{ $unhandledCount }} open
                </span>
            @endif
        </h1>
        <div class="flex items-center gap-4 font-body text-sm">
            <span class="text-soil-500">
                {{ $messages->firstItem() ? ($messages->firstItem() . '–' . $messages->lastItem()) : '0' }}
                of {{ $messages->total() }}
            </span>
            <a href="{{ route('admin.users') }}" class="font-semibold text-leaf-600 hover:underline">Users →</a>
            <a href="{{ route('admin.questions') }}" class="font-semibold text-leaf-600 hover:underline">Questions →</a>
        </div>
    </div>

    @if (session('admin-messages-ok'))
        <p class="mb-6 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
            {{ session('admin-messages-ok') }}
        </p>
    @endif

    {{-- ── Filters ──────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search sender, subject or text…"
            class="w-72 rounded-xl border-soil-300 font-body text-sm shadow-sm"
        />

        <label class="flex items-center gap-2 font-body text-sm text-soil-600">
            <input type="checkbox" wire:model.live="unhandledOnly"
                class="rounded border-soil-300 text-leaf-600 shadow-sm focus:ring-leaf-500" />
            Unhandled only
        </label>

        @if ($search || $unhandledOnly)
            <button type="button" wire:click="resetFilters"
                class="font-body text-sm text-soil-500 hover:text-soil-700 hover:underline">
                Reset
            </button>
        @endif
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    @if ($messages->isEmpty())
        <div class="rounded-2xl border border-leaf-200 bg-white p-10 text-center font-body text-soil-600">
            @if ($search || $unhandledOnly)
                No messages match your filters.
            @else
                Nothing has come in through the contact form yet.
            @endif
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-leaf-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-soil-200 font-body text-sm">
                <thead class="bg-soil-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">From</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Subject</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Received</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-soil-100">
                    @foreach ($messages as $msg)
                        <tr @class(['bg-sun-500/10' => ! $msg->isHandled()])>
                            <td class="px-4 py-3 align-top">
                                <p class="font-medium text-soil-900">{{ $msg->name }}</p>
                                <a href="mailto:{{ $msg->email }}" class="text-soil-500 hover:underline">{{ $msg->email }}</a>
                                @if ($msg->user)
                                    <p class="mt-1 text-xs text-soil-500">
                                        signed in as {{ $msg->user->name }}
                                    </p>
                                @endif
                            </td>

                            <td class="max-w-md px-4 py-3 align-top">
                                <button type="button" wire:click="toggleOpen({{ $msg->id }})"
                                    class="text-left font-medium text-leaf-700 hover:underline">
                                    {{ $msg->subject }}
                                </button>
                                @if ($openId !== $msg->id)
                                    <p class="mt-1 text-xs text-soil-500">{{ Str::limit($msg->message, 90) }}</p>
                                @endif
                                @error('message_' . $msg->id) <p class="mt-1 text-xs text-poppy-600">{{ $message }}</p> @enderror
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 align-top text-soil-500">
                                {{ $msg->created_at->diffForHumans() }}
                            </td>

                            <td class="px-4 py-3 align-top">
                                @if ($msg->isHandled())
                                    <span class="rounded-full bg-leaf-100 px-2 py-0.5 text-xs font-medium text-leaf-700">Handled</span>
                                    <p class="mt-1 text-xs text-soil-500">
                                        by {{ $msg->handler?->name ?? 'an admin' }} · {{ $msg->handled_at->diffForHumans() }}
                                    </p>
                                @else
                                    <span class="rounded-full bg-poppy-100 px-2 py-0.5 text-xs font-medium text-poppy-600">Open</span>
                                @endif
                            </td>

                            <td class="whitespace-nowrap px-4 py-3 align-top text-right">
                                <a href="mailto:{{ $msg->email }}?subject={{ rawurlencode('Re: ' . $msg->subject) }}"
                                    class="rounded-lg border border-leaf-200 px-3 py-1.5 text-xs text-soil-600 hover:bg-soil-50">
                                    Reply
                                </a>

                                @if ($msg->isHandled())
                                    <button type="button" wire:click="markUnhandled({{ $msg->id }})"
                                        class="rounded-lg border border-leaf-200 px-3 py-1.5 text-xs text-soil-600 hover:bg-soil-50">
                                        Reopen
                                    </button>
                                @else
                                    <button type="button" wire:click="markHandled({{ $msg->id }})"
                                        class="rounded-lg border border-leaf-200 px-3 py-1.5 text-xs text-leaf-700 hover:bg-leaf-50">
                                        Mark handled
                                    </button>
                                @endif

                                <button type="button"
                                    wire:click="delete({{ $msg->id }})"
                                    wire:confirm="Delete this message for good? The emailed copy stays in your mailbox."
                                    class="rounded-lg border border-poppy-100 px-3 py-1.5 text-xs text-poppy-600 hover:bg-poppy-100">
                                    Delete
                                </button>
                            </td>
                        </tr>

                        @if ($openId === $msg->id)
                            <tr class="bg-soil-50">
                                <td colspan="5" class="px-4 py-4">
                                    <p class="whitespace-pre-wrap font-body text-sm leading-relaxed text-soil-700">{{ $msg->message }}</p>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            {{ $messages->links() }}
        </div>
    @endif
</div>
