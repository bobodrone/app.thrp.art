<div class="mx-auto max-w-7xl px-4 py-10">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-soil-900">All Questions</h1>
        <span class="font-body text-sm text-soil-500">
            {{ $questions->firstItem() ? ($questions->firstItem() . '–' . $questions->lastItem()) : '0' }}
            of {{ $questions->total() }}
            @if ($questions->currentPage() > 1) · page {{ $questions->currentPage() }} @endif
        </span>
    </div>

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

        @if ($statusFilter || $search)
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
                        <tr class="hover:bg-soil-50">
                            <td class="px-4 py-3 tabular-nums text-soil-400">
                                {{ $start + $i + 1 }}
                            </td>
                            <td class="max-w-xs px-4 py-3 text-soil-900">
                                <a href="{{ route('questions.show', $q) }}" class="hover:text-leaf-600 hover:underline">
                                    {{ \Illuminate\Support\Str::limit($q->content, 80) }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <x-status-badge :status="$q->status" />
                            </td>
                            <td class="px-4 py-3 text-soil-600">{{ $q->asker?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-soil-600">{{ $q->claimer?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-soil-600">{{ $q->answerer?->name ?? '—' }}</td>
                            <td class="px-4 py-3 tabular-nums text-soil-400">{{ format_date($q->created_at) }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('questions.show', $q) }}" class="font-semibold text-leaf-600 hover:underline">View</a>
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
</div>