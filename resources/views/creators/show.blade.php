<x-app-layout>
    <x-slot name="title">{{ $creator->name }} — Creator — THRP</x-slot>

    <div class="mx-auto max-w-2xl px-4 py-10">
        <a href="{{ route('creators.index') }}" class="mb-6 inline-block font-body text-sm font-semibold text-leaf-600 hover:underline">
            ← All creators
        </a>

        <div class="rounded-2xl border border-leaf-200 bg-white p-8 shadow-sm">
            <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-start sm:gap-6 sm:text-left">
                <x-creator-avatar :creator="$creator" size="lg" />

                <div class="min-w-0">
                    <h1 class="font-display text-3xl font-black text-soil-900">{{ $creator->name }}</h1>
                    <p class="mt-1 font-body text-sm text-soil-500">
                        {{ $creator->answers_count }} {{ \Illuminate\Support\Str::plural('answer', $creator->answers_count) }} published
                    </p>
                </div>
            </div>

            @if ($renderedBio)
                <div class="mt-8 border-t border-leaf-200 pt-6">
                    <div class="prose prose-sm max-w-none font-body text-soil-800 prose-a:text-leaf-600">
                        {!! $renderedBio !!}
                    </div>
                </div>
            @endif

            @if ($links = $creator->publicSocialLinks())
                <div class="mt-8 border-t border-leaf-200 pt-6">
                    <h2 class="mb-3 font-body text-xs font-semibold uppercase tracking-wide text-soil-400">Elsewhere</h2>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($links as $link)
                            <li>
                                <a
                                    href="{{ $link['url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer nofollow"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-leaf-200 px-3 py-1.5 font-body text-sm font-medium text-leaf-700 hover:bg-leaf-50 transition-colors"
                                >
                                    {{ $link['label'] }}
                                    <svg class="h-3 w-3 opacity-60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5h5v5m0-5L10 14M9 5H6a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1v-3"/>
                                    </svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $creator->bio && ! $creator->publicSocialLinks())
                <p class="mt-8 border-t border-leaf-200 pt-6 font-body text-sm text-soil-400">
                    This creator hasn't filled in their profile yet.
                </p>
            @endif
        </div>

        @if (auth()->id() === $creator->id)
            <p class="mt-4 text-center font-body text-sm text-soil-500">
                This is your public profile.
                <a href="{{ route('creator.profile') }}" class="font-semibold text-leaf-600 hover:underline">Edit it →</a>
            </p>
        @endif
    </div>
</x-app-layout>
