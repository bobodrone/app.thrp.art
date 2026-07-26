<div class="mx-auto max-w-2xl px-4 py-10">
    <h1 class="mb-1 font-display text-3xl font-black text-soil-900">Creator Profile</h1>
    <p class="mb-8 font-body text-sm text-soil-600">
        This is what visitors see on your
        <a href="{{ route('creators.show', $user) }}" class="font-medium text-leaf-600 hover:underline">public profile</a>.
        Your nickname, email and password live in
        <a href="{{ route('settings') }}" class="font-medium text-leaf-600 hover:underline">Account Settings</a>.
    </p>

    @if (session('profile-saved'))
        <p class="mb-6 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
            {{ session('profile-saved') }}
        </p>
    @endif

    <form wire:submit="save" class="space-y-6">

        {{-- ── Profile picture ──────────────────────────────────────── --}}
        <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
            <h2 class="mb-1 font-display text-lg font-bold text-soil-900">Profile picture</h2>
            <p class="mb-4 font-body text-sm text-soil-600">Shown next to your name on your public profile.</p>

            <x-image-upload
                wire-model="avatar"
                clear-action="clearAvatar"
                config="avatar"
                label=""
                remove-label="Remove picture"
                :current-url="$avatarPreview"
            />
        </div>

        {{-- ── Bio ──────────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
            <h2 class="mb-1 font-display text-lg font-bold text-soil-900">Bio</h2>
            <p class="mb-1 font-body text-sm text-soil-600">A short introduction — what you grow, make or know about.</p>
            <p class="mb-4 font-body text-xs text-soil-400">
                Markdown works here: <code>**bold**</code>, <code>*italic*</code>,
                <code>[link](https://…)</code>, and <code>*</code> bullet lists. Headings are shown as plain text.
            </p>

            {{-- The binding is deferred (saved on submit), so the counter is kept
                 in Alpine rather than costing a round-trip per keystroke. --}}
            <div x-data="{ count: @js(mb_strlen($bio)) }">
                <textarea
                    wire:model="bio"
                    x-on:input="count = $event.target.value.length"
                    rows="5"
                    maxlength="1000"
                    placeholder="Tell people a little about yourself…"
                    class="w-full rounded-xl border-soil-300 font-body text-sm shadow-sm"
                ></textarea>
                <div class="mt-1 flex items-center justify-between gap-3">
                    @error('bio')
                        <p class="font-body text-xs text-poppy-600">{{ $message }}</p>
                    @enderror
                    <p class="ml-auto font-body text-xs text-soil-400"><span x-text="count"></span> / 1000</p>
                </div>
            </div>
        </div>

        {{-- ── Social links ─────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
            <h2 class="mb-1 font-display text-lg font-bold text-soil-900">Links</h2>
            <p class="mb-4 font-body text-sm text-soil-600">
                Where else people can find you — social profiles, a shop, your own site.
                Up to {{ \App\Livewire\CreatorProfile::MAX_LINKS }}.
            </p>

            @if ($socialLinks)
                <div class="mb-4 space-y-3">
                    @foreach ($socialLinks as $index => $link)
                        <div wire:key="social-link-{{ $index }}" class="flex flex-col gap-2 sm:flex-row sm:items-start">
                            <div class="sm:w-40">
                                <input
                                    type="text"
                                    wire:model="socialLinks.{{ $index }}.label"
                                    maxlength="40"
                                    placeholder="Instagram"
                                    class="w-full rounded-xl border-soil-300 font-body text-sm shadow-sm"
                                />
                                @error("socialLinks.{$index}.label")
                                    <p class="mt-1 font-body text-xs text-poppy-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex-1">
                                <input
                                    type="url"
                                    wire:model="socialLinks.{{ $index }}.url"
                                    maxlength="255"
                                    placeholder="https://instagram.com/yourname"
                                    class="w-full rounded-xl border-soil-300 font-body text-sm shadow-sm"
                                />
                                @error("socialLinks.{$index}.url")
                                    <p class="mt-1 font-body text-xs text-poppy-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <button
                                type="button"
                                wire:click="removeLink({{ $index }})"
                                aria-label="Remove link"
                                class="self-start rounded-xl border border-soil-300 px-3 py-2 font-body text-sm text-soil-400 hover:border-poppy-100 hover:text-poppy-600 transition-colors"
                            >
                                Remove
                            </button>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($canAddLink)
                <button
                    type="button"
                    wire:click="addLink"
                    class="rounded-xl border border-leaf-200 px-4 py-2 font-body text-sm font-semibold text-leaf-600 hover:bg-leaf-50 transition-colors"
                >
                    + Add a link
                </button>
            @else
                <p class="font-body text-xs text-soil-400">
                    That's the maximum of {{ \App\Livewire\CreatorProfile::MAX_LINKS }} links.
                </p>
            @endif
        </div>

        {{-- ── Anonymity ────────────────────────────────────────────── --}}
        <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
            <h2 class="mb-1 font-display text-lg font-bold text-soil-900">Answering anonymously</h2>

            <label class="mt-3 flex items-start gap-3">
                <input
                    type="checkbox"
                    wire:model="postsAnonymously"
                    class="mt-0.5 rounded border-soil-300 text-leaf-600 shadow-sm focus:ring-leaf-500"
                />
                <span class="font-body text-sm text-soil-700">
                    Post my answers anonymously
                    <span class="mt-1 block text-xs text-soil-500">
                        New answers are credited to “{{ \App\Models\Answer::ANONYMOUS_AUTHOR }}” instead of your
                        nickname. Answers you have already posted keep the credit they were published with, and
                        admins can always see who answered what.
                    </span>
                </span>
            </label>
        </div>

        <div class="flex justify-end">
            <button
                type="submit"
                class="rounded-xl bg-leaf-600 px-6 py-2.5 font-body text-sm font-semibold text-white hover:bg-leaf-500 transition-colors"
            >
                Save profile
            </button>
        </div>
    </form>
</div>
