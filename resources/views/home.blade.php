<x-app-layout>
    <x-slot name="title">The Human Response Project</x-slot>

    {{-- ── Hero ─────────────────────────────────────────────────────────────── --}}
    <section class="relative min-h-[62vh] bg-sun-500 overflow-hidden flex items-center">
        <x-botanical-hero />

        <div class="relative z-10 mx-auto w-full max-w-6xl px-5 py-20">
            <p class="mb-5 font-body text-xs font-semibold uppercase tracking-[0.3em] text-leaf-700">
                The Human Response Project
            </p>

            <h1 class="font-display text-5xl font-black leading-[0.92] text-soil-900 md:text-7xl lg:text-8xl">
                Real questions.<br>
                <em class="not-italic text-leaf-700">Real humans.</em><br>
                Real responses.
            </h1>

            <p class="mt-6 max-w-md font-body text-lg text-soil-700">
                Ask anything. Our responders will respond to every query personally.
            </p>

            <div class="mt-10 max-w-xl">
                @if ($errors->any())
                    <p class="mb-3 rounded-lg bg-poppy-100 px-4 py-2.5 text-sm font-medium text-poppy-600">
                        {{ $errors->first() }}
                    </p>
                @endif

                @if (session('status') === 'question-asked')
                    <p class="mb-3 rounded-lg bg-leaf-100 px-4 py-2.5 text-sm font-medium text-leaf-700">
                        Your question has been submitted.
                    </p>
                @endif

                <form method="post" action="{{ route('questions.store') }}" class="flex gap-3">
                    @csrf
                    <input
                        name="content"
                        type="text"
                        value="{{ old('content') }}"
                        placeholder="What’s on your mind…"
                        maxlength="2000"
                        class="min-w-0 flex-1 rounded-xl border-2 border-soil-900/20 bg-white/90 px-5 py-3.5 text-soil-900 shadow-md backdrop-blur-sm placeholder:text-soil-600/60 focus:border-leaf-600 focus:ring-0 focus:bg-white"
                    />
                    <button type="submit"
                        class="rounded-xl bg-soil-900 px-6 py-3.5 font-semibold text-sun-500 shadow-md hover:bg-soil-800 transition-colors whitespace-nowrap">
                        Go →
                    </button>
                </form>

                @if (! auth()->check())
                    <p class="mt-2 font-body text-xs text-soil-700">
                        <a href="{{ route('login') }}" class="underline underline-offset-2 hover:text-soil-900">Sign in</a> or
                        <a href="{{ route('register') }}" class="underline underline-offset-2 hover:text-soil-900">register</a> to ask a question.
                    </p>
                @endif
            </div>
        </div>
    </section>

    {{-- ── Question feed ────────────────────────────────────────────────────── --}}
    <section class="bg-soil-100 px-5 py-14">
        <div class="mx-auto max-w-6xl">
            @php $count = $questions->count(); @endphp
            @if ($count === 0)
                <div class="rounded-2xl border-2 border-dashed border-soil-300 p-16 text-center">
                    <p class="font-display text-2xl italic text-soil-600">No questions yet.</p>
                    <p class="mt-2 font-body text-sm text-soil-500">Be the first to ask one above.</p>
                </div>
            @else
                <div class="mb-8 flex items-center justify-between">
                    <h2 class="font-display text-2xl font-bold text-soil-800">Questions</h2>
                    <span class="font-body text-sm text-soil-500">{{ $count }} question{{ $count === 1 ? '' : 's' }}</span>
                </div>
                <div class="grid gap-5 items-start sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($questions as $row)
                        <x-question-card :question="$row['question']" :renderedAnswer="$row['renderedAnswer']" />
                    @endforeach
                </div>
            @endif
        </div>
    </section>
    <section class="bg-gray-800 px-5 py-4">
        <div class="mx-auto max-w-6xl">
          <p>
            <a
              href="/about"
              class="text-white underline-offset-4 underline" target="_blank"
            >Credits and legal information</a>
          </p>
        </div>
    </section>
</x-app-layout>
