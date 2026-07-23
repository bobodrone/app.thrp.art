<div class="mx-auto max-w-3xl px-4 py-10">
    <div class="mb-8 flex items-center justify-between">
        <h1 class="font-display text-2xl font-bold text-soil-900">Creator Dashboard</h1>
        <a href="{{ route('creator.answered') }}" class="font-body text-sm font-semibold text-leaf-600 hover:underline">My Answered Questions →</a>
    </div>

    {{-- ── My In-Progress ────────────────────────────────────────────────── --}}
    <section class="mb-10">
        <h2 class="mb-3 font-body text-sm font-semibold uppercase tracking-wide text-soil-600">
            My In-Progress ({{ $myClaimed->count() }})
        </h2>

        @if ($myClaimed->isEmpty())
            <p class="font-body text-sm text-soil-500">No questions claimed yet.</p>
        @else
            <div class="divide-y divide-soil-200 rounded-2xl border border-leaf-200 bg-white shadow-sm">
                @foreach ($myClaimed as $q)
                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <p class="truncate font-body text-sm text-soil-900">{{ \Illuminate\Support\Str::limit($q->content, 100) }}</p>
                            @if ($q->claimed_at)
                                <p class="mt-0.5 font-body text-xs text-soil-400">Claimed {{ $q->claimed_at->diffForHumans() }}</p>
                            @endif
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <form wire:submit="unclaim({{ $q->id }})">
                                @csrf
                                <button type="submit"
                                    class="rounded-lg border border-leaf-200 px-3 py-1.5 font-body text-xs text-soil-600 hover:bg-soil-50">
                                    Unclaim
                                </button>
                            </form>
                            <a href="{{ route('creator.questions.show', $q) }}"
                                class="rounded-lg bg-leaf-600 px-3 py-1.5 font-body text-xs font-semibold text-white hover:bg-leaf-500">
                                Continue →
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ── Open Questions ────────────────────────────────────────────────── --}}
    <section>
        <h2 class="mb-3 font-body text-sm font-semibold uppercase tracking-wide text-soil-600">
            Open Questions ({{ $openQuestions->count() }})
        </h2>

        @if ($openQuestions->isEmpty())
            <p class="font-body text-sm text-soil-500">No open questions right now.</p>
        @else
            <div class="divide-y divide-soil-200 rounded-2xl border border-leaf-200 bg-white shadow-sm">
                @foreach ($openQuestions as $q)
                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                        <div class="min-w-0">
                            <p class="truncate font-body text-sm text-soil-900">{{ \Illuminate\Support\Str::limit($q->content, 100) }}</p>
                            <p class="mt-0.5 font-body text-xs text-soil-400">
                                @if ($q->asker) {{ $q->asker->name }} · @endif{{ $q->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <form wire:submit="claim({{ $q->id }})">
                            @csrf
                            <button type="submit"
                                class="shrink-0 rounded-lg bg-leaf-600 px-3 py-1.5 font-body text-xs font-semibold text-white hover:bg-leaf-500">
                                Claim
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>