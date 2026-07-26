@inject('markdown', 'App\Services\MarkdownRenderer')

@php
    // One helper for both sortable headers: current column gets an arrow.
    $arrow = fn (string $column): string => $sort !== $column ? '' : ($direction === 'asc' ? ' ↑' : ' ↓');
@endphp

<div class="mx-auto max-w-3xl px-4 py-10">
    <h1 class="mb-1 font-display text-3xl font-black text-soil-900">Creators</h1>
    <p class="mb-6 font-body text-sm text-soil-600">The people who answer your questions.</p>

    {{-- ── Type-ahead search ────────────────────────────────────────── --}}
    <div class="relative mb-6">
        <input
            type="search"
            wire:model.live.debounce.300ms="search"
            placeholder="Search by name…"
            aria-label="Search creators by name"
            class="w-full rounded-xl border-soil-300 pl-10 font-body text-sm shadow-sm"
        />
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-soil-400"
            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>
        </svg>
        <div wire:loading.delay wire:target="search"
            class="absolute right-3 top-1/2 -translate-y-1/2 font-body text-xs text-soil-400">
            Searching…
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-leaf-200 bg-white shadow-sm">
        <table class="w-full text-left">
            <caption class="sr-only">Creators, with the number of answers each has published</caption>
            <thead class="border-b border-leaf-200 bg-soil-50">
                <tr class="font-body text-xs font-semibold uppercase tracking-wide text-soil-600">
                    <th scope="col" class="px-5 py-3" aria-sort="{{ $sort === 'name' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                        <button type="button" wire:click="sortBy('name')" class="hover:text-leaf-600">
                            Creator{{ $arrow('name') }}
                        </button>
                    </th>
                    <th scope="col" class="px-5 py-3 text-right" aria-sort="{{ $sort === 'answers' ? ($direction === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                        <button type="button" wire:click="sortBy('answers')" class="hover:text-leaf-600">
                            Answers{{ $arrow('answers') }}
                        </button>
                    </th>
                    <th scope="col" class="px-5 py-3"><span class="sr-only">Profile</span></th>
                </tr>
            </thead>

            <tbody class="divide-y divide-soil-200">
                @forelse ($creators as $creator)
                    <tr wire:key="creator-{{ $creator->id }}" class="hover:bg-soil-50">
                        <td class="px-5 py-4">
                            <a href="{{ route('creators.show', $creator) }}" class="flex items-center gap-3 group">
                                <x-creator-avatar :creator="$creator" />
                                <span class="min-w-0">
                                    <span class="block truncate font-body font-medium text-soil-900 group-hover:text-leaf-600">
                                        {{ $creator->name }}
                                    </span>
                                    @if ($creator->bio)
                                        {{-- Plain text: rendered HTML cannot be truncated safely. --}}
                                        <span class="block truncate font-body text-sm text-soil-500">
                                            {{ $markdown->excerpt($creator->bio) }}
                                        </span>
                                    @endif
                                </span>
                            </a>
                        </td>
                        <td class="px-5 py-4 text-right font-body text-sm tabular-nums text-soil-600">
                            {{ $creator->answers_count }}
                        </td>
                        <td class="px-5 py-4 text-right">
                            <a href="{{ route('creators.show', $creator) }}"
                                class="whitespace-nowrap font-body text-sm font-semibold text-leaf-600 hover:underline">
                                View profile →
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-5 py-10 text-center font-body text-sm text-soil-500">
                            @if (trim($search) !== '')
                                No creator matches “{{ $search }}”.
                            @else
                                No creators yet.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($creators->hasPages())
        <div class="mt-4 flex items-center justify-between font-body text-sm">
            <button type="button" wire:click="previousPage" @disabled($creators->onFirstPage())
                class="rounded-xl border border-soil-300 px-4 py-2 text-soil-600 hover:bg-soil-50 disabled:opacity-40 disabled:hover:bg-transparent">
                ← Previous
            </button>
            <span class="text-soil-500">Page {{ $creators->currentPage() }} of {{ $creators->lastPage() }}</span>
            <button type="button" wire:click="nextPage" @disabled(! $creators->hasMorePages())
                class="rounded-xl border border-soil-300 px-4 py-2 text-soil-600 hover:bg-soil-50 disabled:opacity-40 disabled:hover:bg-transparent">
                Next →
            </button>
        </div>
    @endif
</div>
