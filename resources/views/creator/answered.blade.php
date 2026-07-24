<x-app-layout>
    <x-slot name="title">{{ $isAdmin ? 'Answered Questions' : 'My Answers' }} — THRP</x-slot>

    <div class="mx-auto max-w-3xl px-4 py-10">
        <div class="mb-6 flex items-center justify-between">
            <h1 class="font-display text-2xl font-bold text-soil-900">
                {{ $isAdmin ? 'Answered Questions' : "Questions I've Answered" }}
            </h1>
            <a href="{{ route('creator.dashboard') }}" class="font-body text-sm font-semibold text-leaf-600 hover:underline">← Dashboard</a>
        </div>

        @if ($answered->isEmpty())
            <div class="rounded-2xl border border-leaf-200 bg-white p-10 text-center font-body text-soil-600">
                <p>{{ $isAdmin ? 'No questions have been answered yet.' : "You haven't answered any questions yet." }}</p>
                <a href="{{ route('creator.dashboard') }}" class="mt-3 inline-block font-body text-sm font-semibold text-leaf-600 hover:underline">Go to dashboard</a>
            </div>
        @else
            <div class="divide-y divide-soil-200 rounded-2xl border border-leaf-200 bg-white shadow-sm">
                @foreach ($answered as $q)
                    @php
                        // Answerers and admins get the creator view, which carries the edit form.
                        $canEdit = $q->isAnswerEditableBy(auth()->user());
                    @endphp
                    <a href="{{ $canEdit ? route('creator.questions.show', $q) : route('questions.show', $q) }}"
                        class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-soil-50">
                        <div class="min-w-0">
                            <p class="truncate font-body text-sm text-soil-900">{{ \Illuminate\Support\Str::limit($q->content, 120) }}</p>
                            <p class="mt-0.5 font-body text-xs text-soil-400">
                                @if ($q->asker) Asked by {{ $q->asker->name }} · @endif
                                @if ($isAdmin && $q->answerer) Answered by {{ $q->answerer->name }} · @endif
                                @if ($q->answered_at) Answered {{ $q->answered_at->diffForHumans() }}@endif
                            </p>
                        </div>
                        <span class="shrink-0 font-body text-xs font-semibold text-leaf-600">
                            {{ $canEdit ? 'Edit answer →' : 'View →' }}
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
