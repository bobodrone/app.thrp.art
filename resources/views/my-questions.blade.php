<x-app-layout>
    <x-slot name="title">My Questions — THRP</x-slot>

    <div class="mx-auto max-w-3xl px-4 py-10">
        <h1 class="mb-6 font-display text-2xl font-bold text-soil-900">My Questions</h1>

        @php $count = $questions->count(); @endphp
        @if ($count === 0)
            <div class="rounded-2xl border border-leaf-200 bg-white p-10 text-center font-body text-soil-600">
                <p>You haven't asked any questions yet.</p>
                <a href="{{ route('home') }}" class="mt-3 inline-block font-body text-sm font-semibold text-leaf-600 hover:underline">Ask your first question</a>
            </div>
        @else
            <div class="divide-y divide-soil-200 rounded-2xl border border-leaf-200 bg-white shadow-sm">
                @foreach ($questions as $q)
                    <a href="{{ route('questions.show', $q) }}"
                        class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-soil-50">
                        <div class="min-w-0">
                            <p class="truncate font-body text-sm text-soil-900">{{ \Illuminate\Support\Str::limit($q->content, 120) }}</p>
                            <p class="mt-0.5 font-body text-xs text-soil-400">{{ $q->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="shrink-0">
                            <x-status-badge :status="$q->status" />
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>