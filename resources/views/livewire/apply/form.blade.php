<div>
    {{-- Yellow hero header --}}
    <div class="relative overflow-hidden bg-sun-500 py-16 px-4">
        <div class="pointer-events-none absolute right-8 top-4 opacity-60" aria-hidden="true">
            <x-flower size="90" petalColor="#1A5C38" centerColor="#1A1209" dotColor="#FFD600" />
        </div>
        <div class="pointer-events-none absolute left-8 bottom-2 opacity-50" aria-hidden="true">
            <x-leaf size="70" color="#1A5C38" :rotate="-20" />
        </div>
        <div class="relative z-10 mx-auto max-w-2xl">
            <p class="font-body mb-3 text-xs font-semibold uppercase tracking-[0.3em] text-leaf-700">Responder Application</p>
            <h1 class="font-display text-5xl font-black leading-tight text-soil-900">Become a<br>Responder.</h1>
            <p class="font-body mt-4 max-w-md text-soil-700">
                Responders respond to questions submitted by the community. Tell us about yourself.
            </p>
        </div>
    </div>

    {{-- Form / Success state --}}
    <div class="bg-soil-100 px-4 py-12">
        <div class="mx-auto max-w-2xl">
            @if ($submitted)
                <div class="rounded-2xl bg-white p-10 text-center shadow-lg">
                    <div class="mb-4 flex justify-center">
                        <x-flower size="56" petalColor="#FFD600" centerColor="#1A5C38" dotColor="#FFD600" />
                    </div>
                    <h2 class="font-display mb-2 text-2xl font-bold text-soil-900">Application received</h2>
                    <p class="font-body text-soil-600">Thanks — we'll review your application and get back to you by email.</p>
                    <a href="{{ route('home') }}" class="mt-6 inline-block font-body text-sm font-semibold text-leaf-600 hover:underline">← Back to questions</a>
                </div>
            @else
                <div class="rounded-2xl bg-white p-8 shadow-lg">
                    @if ($errors->any())
                        <p class="mb-5 rounded-xl bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">
                            {{ $errors->first() }}
                        </p>
                    @endif

                    <form wire:submit="submit" class="space-y-5">
                        <div>
                            <label for="name" class="mb-1 block font-body text-sm font-medium text-soil-700">Name / nickname</label>
                            <input id="name" type="text" required minlength="2" maxlength="40"
                                wire:model="name"
                                class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
                        </div>
                        <div>
                            <label for="email" class="mb-1 block font-body text-sm font-medium text-soil-700">Email</label>
                            <input id="email" type="email" required
                                wire:model="email"
                                class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
                        </div>
                        <div>
                            <label for="message" class="mb-1 block font-body text-sm font-medium text-soil-700">
                                Why do you want to be a responder?
                                <span class="ml-1 font-normal text-soil-400">(20–500 characters)</span>
                            </label>
                            <textarea id="message" required minlength="20" maxlength="500" rows="5"
                                wire:model="message"
                                class="w-full rounded-xl border-soil-300 font-body shadow-sm"></textarea>
                        </div>

                        <div class="space-y-3">
                            <x-responder-terms />

                            <label for="acceptedTerms" class="flex cursor-pointer items-start gap-3">
                                <input id="acceptedTerms" type="checkbox" required
                                    wire:model="acceptedTerms"
                                    class="mt-0.5 h-5 w-5 shrink-0 rounded border-soil-300 text-leaf-600 shadow-sm focus:ring-leaf-500" />
                                <span class="font-body text-sm text-soil-700">
                                    I hereby accept &amp; confirm the conditions
                                </span>
                            </label>

                            @error('acceptedTerms')
                                <p class="font-body text-sm text-poppy-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                            class="rounded-xl bg-soil-900 px-8 py-3 font-body font-semibold text-sun-500 hover:bg-soil-800 transition-colors">
                            Submit application
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>