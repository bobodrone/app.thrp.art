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
            <p class="font-body mb-3 text-xs font-semibold uppercase tracking-[0.3em] text-leaf-700">Contact</p>
            <h1 class="font-display text-5xl font-black leading-tight text-soil-900">Get in<br>touch.</h1>
            <p class="font-body mt-4 max-w-md text-soil-700">
                Questions, feedback, something that looks broken, or a note about a response —
                write to us here and a human will read it.
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
                    <h2 class="font-display mb-2 text-2xl font-bold text-soil-900">Message sent</h2>
                    <p class="font-body text-soil-600">
                        Thanks for writing. We read everything that comes in and will reply by email
                        if your message needs a reply.
                    </p>
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
                        {{--
                            Honeypot. Hidden from sight, taken out of the tab order and out of the
                            accessibility tree, and told not to autofill — so no person and no
                            screen reader ever meets it, but a form-stuffing bot fills it in.
                        --}}
                        <div class="absolute left-[-9999px] h-0 w-0 overflow-hidden" aria-hidden="true">
                            <label for="{{ $honeypotField }}">Leave this field empty</label>
                            <input id="{{ $honeypotField }}" name="{{ $honeypotField }}" type="text"
                                wire:model="website" tabindex="-1" autocomplete="off" />
                        </div>

                        <div>
                            <label for="name" class="mb-1 block font-body text-sm font-medium text-soil-700">Your name</label>
                            <input id="name" type="text" required minlength="2" maxlength="60"
                                wire:model="name" autocomplete="name"
                                class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
                        </div>
                        <div>
                            <label for="email" class="mb-1 block font-body text-sm font-medium text-soil-700">
                                Email
                                <span class="ml-1 font-normal text-soil-400">(so we can reply)</span>
                            </label>
                            <input id="email" type="email" required maxlength="255"
                                wire:model="email" autocomplete="email"
                                class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
                        </div>
                        <div>
                            <label for="subject" class="mb-1 block font-body text-sm font-medium text-soil-700">Subject</label>
                            <input id="subject" type="text" required minlength="3" maxlength="120"
                                wire:model="subject"
                                class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
                        </div>
                        <div>
                            <label for="message" class="mb-1 block font-body text-sm font-medium text-soil-700">
                                Message
                                <span class="ml-1 font-normal text-soil-400">(20–2000 characters)</span>
                            </label>
                            <textarea id="message" required minlength="20" maxlength="2000" rows="7"
                                wire:model="message"
                                class="w-full rounded-xl border-soil-300 font-body shadow-sm"></textarea>
                        </div>

                        <button type="submit"
                            class="rounded-xl bg-soil-900 px-8 py-3 font-body font-semibold text-sun-500 hover:bg-soil-800 transition-colors">
                            Send message
                        </button>
                    </form>

                    <p class="mt-6 font-body text-xs text-soil-500">
                        We use your email address to reply to this message and nothing else.
                        See the <a href="{{ route('about') }}" class="underline underline-offset-2 hover:text-soil-700">about page</a>
                        for the privacy notice.
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
