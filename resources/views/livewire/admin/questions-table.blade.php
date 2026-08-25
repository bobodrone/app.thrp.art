<div class="mx-auto max-w-7xl px-4 py-10">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-soil-900">All Questions</h1>
        <span class="font-body text-sm text-soil-500">
            {{ $questions->firstItem() ? ($questions->firstItem() . '–' . $questions->lastItem()) : '0' }}
            of {{ $questions->total() }}
            @if ($questions->currentPage() > 1) · page {{ $questions->currentPage() }} @endif
        </span>
    </div>

    @if (session('admin-questions-ok'))
        <p class="mb-6 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
            {{ session('admin-questions-ok') }}
        </p>
    @endif

    {{-- ── Filters ─────────────────────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-wrap items-center gap-3">
        <select
            wire:model.live="statusFilter"
            class="rounded-xl border-soil-300 font-body text-sm shadow-sm"
        >
            <option value="">All statuses</option>
            <option value="asked">Asked</option>
            <option value="claimed">In progress</option>
            <option value="answered">Answered</option>
        </select>

        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search questions…"
            class="w-64 rounded-xl border-soil-300 font-body text-sm shadow-sm"
        />

        <label class="flex items-center gap-2 font-body text-sm text-soil-600">
            <input type="checkbox" wire:model.live="showDeleted"
                class="rounded border-soil-300 text-leaf-600 shadow-sm focus:ring-leaf-500" />
            Show deleted
        </label>

        @if ($statusFilter || $search || $showDeleted)
            <button type="button" wire:click="resetFilters"
                class="font-body text-sm text-soil-500 hover:text-soil-700 hover:underline">
                Reset
            </button>
        @endif
    </div>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    @if ($questions->isEmpty())
        <div class="rounded-2xl border border-leaf-200 bg-white p-10 text-center font-body text-soil-600">
            No questions match your filters.
        </div>
    @else
        <div class="overflow-x-auto rounded-2xl border border-leaf-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-soil-200 font-body text-sm">
                <thead class="bg-soil-50">
                    <tr>
                        <th class="w-10 px-4 py-3 text-left font-medium text-soil-500">#</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Question</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Status</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Asked by</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Claimed by</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Answered by</th>
                        <th class="px-4 py-3 text-left font-medium text-soil-500">Created</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-soil-100">
                    @php
                        $page = $questions->currentPage();
                        $perPage = $questions->perPage();
                        $start = ($page - 1) * $perPage;
                    @endphp
                    @foreach ($questions as $i => $q)
                        @php $isTrashed = $q->trashed(); @endphp
                        <tr wire:key="q-{{ $q->id }}" class="{{ $isTrashed ? 'bg-poppy-100/40' : 'hover:bg-soil-50' }}">
                            <td class="px-4 py-3 tabular-nums text-soil-400">
                                {{ $start + $i + 1 }}
                            </td>
                            <td class="max-w-xs px-4 py-3 text-soil-900">
                                @if ($isTrashed)
                                    <span class="text-soil-500 line-through">{{ \Illuminate\Support\Str::limit($q->content, 80) }}</span>
                                @else
                                    <a href="{{ route('questions.show', $q) }}" class="hover:text-leaf-600 hover:underline">
                                        {{ \Illuminate\Support\Str::limit($q->content, 80) }}
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col items-start gap-1">
                                    <x-status-badge :status="$q->status" />
                                    @if ($isTrashed)
                                        <span class="rounded-full bg-poppy-100 px-2 py-0.5 font-body text-xs font-medium text-poppy-600">Deleted</span>
                                    @elseif ($q->hasHiddenAnswer())
                                        <span class="rounded-full bg-soil-100 px-2 py-0.5 font-body text-xs text-soil-500">Answer removed</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-soil-600">{{ $q->asker?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-soil-600">{{ $q->claimer?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-soil-600">
                                @php $credited = $q->creditedAnswers(); @endphp
                                @if ($credited->isEmpty())
                                    —
                                @else
                                    @foreach ($credited as $answer)
                                        <x-answerer-name :answer="$answer" :viewer="auth()->user()" />{{ $loop->last ? '' : ', ' }}
                                    @endforeach
                                @endif
                            </td>
                            <td class="px-4 py-3 tabular-nums text-soil-400">{{ format_date($q->created_at) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-3">
                                    @if ($isTrashed)
                                        <button type="button" wire:click="restore({{ $q->id }})"
                                            class="font-semibold text-leaf-600 hover:underline">Restore</button>
                                        <button type="button" wire:click="forceDelete({{ $q->id }})"
                                            wire:confirm="Permanently delete this question? This cannot be undone."
                                            class="font-semibold text-poppy-600 hover:underline">Delete permanently</button>
                                    @else
                                        <a href="{{ route('questions.show', $q) }}" class="font-semibold text-leaf-600 hover:underline">View</a>
                                        <button type="button" wire:click="edit({{ $q->id }})"
                                            class="font-semibold text-soil-500 hover:text-soil-700 hover:underline">Edit</button>
                                        @if ($q->answers_count > 1)
                                            {{-- Where every answer is listed, and the main one can be swapped. --}}
                                            <a href="{{ route('creator.questions.show', $q) }}"
                                                class="font-semibold text-soil-500 hover:text-soil-700 hover:underline">Answers ({{ $q->answers_count }})</a>
                                        @endif
                                        @if ($q->hasHiddenAnswer())
                                            <button type="button" wire:click="restoreAnswer({{ $q->id }})"
                                                class="font-semibold text-leaf-600 hover:underline">Restore answer</button>
                                        @endif
                                        <button type="button" wire:click="delete({{ $q->id }})"
                                            wire:confirm="Delete this question and its answer? It can be restored from “Show deleted”."
                                            class="font-semibold text-poppy-600 hover:underline">Delete</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- ── Pagination ───────────────────────────────────────────────────────--}}
        <div class="mt-4">
            {{ $questions->links() }}
        </div>
    @endif

    {{-- ── Edit modal ───────────────────────────────────────────────────────── --}}
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
        {{-- Backdrop (only this closes the modal) --}}
        <div
            x-show="$wire.showEdit"
            x-transition.opacity
            x-on:click="$wire.showEdit = false"
            class="fixed inset-0 bg-soil-900/50"
        ></div>

        {{-- Panel --}}
        <div
            x-show="$wire.showEdit"
            x-transition
            class="relative z-10 my-auto w-full max-w-2xl overflow-hidden rounded-2xl bg-white shadow-xl"
        >
            <div class="max-h-[85vh] overflow-y-auto p-6">
                <h2 class="font-display text-lg font-bold text-soil-900">Edit question</h2>

                <div class="mt-4 space-y-5">
                    <div>
                        <label class="mb-1 block font-body text-xs font-medium uppercase tracking-wide text-soil-500">Question</label>
                        <textarea wire:model="editContent" rows="4"
                            class="w-full rounded-xl border-soil-300 font-body text-sm shadow-sm"></textarea>
                        @error('editContent') <p class="mt-1 font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
                    </div>

                    @if ($editHasAnswer)
                        <div wire:key="edit-answer-{{ $editingId }}">
                            <label class="mb-1 block font-body text-xs font-medium uppercase tracking-wide text-soil-500">Answer</label>
                            <x-markdown-editor wire-model="editAnswer" :initial="$editAnswer" />
                            @error('editAnswer') <p class="mt-1 font-body text-xs text-poppy-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div class="mt-6 flex items-center justify-between">
                    @if ($editHasAnswer)
                        <button type="button"
                            wire:click="deleteAnswer({{ $editingId }})"
                            wire:confirm="Remove this answer and reopen the question?"
                            class="font-body text-sm text-poppy-600 hover:underline">
                            Delete answer
                        </button>
                    @else
                        <span></span>
                    @endif

                    <div class="flex gap-3">
                        <button type="button" x-on:click="$wire.showEdit = false"
                            class="rounded-xl border border-soil-300 px-4 py-2 font-body text-sm text-soil-600 hover:bg-soil-50">
                            Cancel
                        </button>
                        <button type="button" wire:click="saveEdit"
                            class="rounded-xl bg-leaf-600 px-4 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500">
                            Save changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>