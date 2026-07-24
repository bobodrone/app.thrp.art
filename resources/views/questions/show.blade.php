<x-app-layout>
    <x-slot name="title">Question — THRP</x-slot>

    <div class="px-4 py-12">
        <div class="mx-auto max-w-2xl">

            <a href="{{ route('home') }}" class="mb-6 inline-flex items-center gap-1.5 font-body text-sm font-medium text-leaf-600 hover:text-leaf-700">
                ← Back to questions
            </a>

            <article class="rounded-2xl bg-white p-8 shadow-lg">
                <div class="mb-6 flex items-center justify-between">
                    <x-status-badge :status="$question->status" />
                    <span class="font-body text-xs text-soil-400">{{ $question->created_at->diffForHumans() }}</span>
                </div>

                <h1 class="font-display mb-3 text-2xl font-bold leading-snug text-soil-900">
                    {{ $question->content }}
                </h1>

                @if ($question->asker)
                    <p class="font-body text-xs text-soil-400">
                        Asked by <span class="font-medium text-soil-600">{{ $question->asker->name }}</span>
                    </p>
                @endif

                @if ($question->isClaimableBy(auth()->user()))
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3">
                        <p class="font-body text-sm text-leaf-700">No one has answered this yet.</p>
                        <x-question-action :question="$question" size="md" />
                    </div>
                @elseif ($question->status === \App\Enums\QuestionStatus::Claimed && $question->claimer)
                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-sky-100 px-4 py-3">
                        <p class="font-body text-sm text-sky-600">
                            @if ($question->isAwaitingAnswerFrom(auth()->user()))
                                You claimed this question — pick up where you left off.
                            @else
                                Being answered by <strong>{{ $question->claimer->name }}</strong>…
                            @endif
                        </p>
                        {{-- Only the claimer gets a link back; for everyone else this renders nothing. --}}
                        <x-question-action :question="$question" size="md" />
                    </div>
                @endif

                @if ($question->status === \App\Enums\QuestionStatus::Answered && $renderedAnswer)
                    <div class="mt-8 border-t-2 border-sun-300 pt-8">
                        <div class="mb-4 flex items-center gap-2">
                            <x-flower size="20" petalColor="#FFD600" centerColor="#1A5C38" dotColor="#FFD600" />
                            <h2 class="font-body text-xs font-semibold uppercase tracking-wider text-leaf-600">Answer</h2>
                        </div>
                        @if ($imageUrl = $question->answerImageUrl())
                            <x-answer-image :url="$imageUrl" />
                        @endif
                        <div class="prose prose-sm max-w-none font-body prose-headings:font-display prose-a:text-leaf-600">
                            {!! $renderedAnswer !!}
                        </div>
                        @if ($question->answerer)
                            <p class="mt-6 font-body text-xs text-soil-400">
                                Answered by <span class="font-medium text-soil-600">{{ $question->answerer->name }}</span>
                                @if ($question->answered_at)&nbsp;· {{ $question->answered_at->diffForHumans() }}@endif
                            </p>
                        @endif
                    </div>
                @endif
            </article>
        </div>
    </div>
</x-app-layout>