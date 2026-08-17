<x-app-layout>
    <x-slot name="title">Settings — THRP</x-slot>

    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="mb-8 font-display text-3xl font-black text-soil-900">Account Settings</h1>

        <div class="space-y-6">

            {{-- ── Responder profile (responders & admins only) ─────────── --}}
            @if ($user->isCreator())
                <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
                    <h2 class="mb-1 font-display text-lg font-bold text-soil-900">Responder Profile</h2>
                    <p class="mb-4 font-body text-sm text-soil-600">
                        Your picture, bio and links — plus whether your answers are posted anonymously.
                    </p>
                    <a href="{{ route('creator.profile') }}"
                        class="inline-block rounded-xl bg-leaf-600 px-4 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500 transition-colors">
                        Edit responder profile
                    </a>
                </div>
            @endif

            {{-- ── Change Nickname ─────────────────────────────────────── --}}
            <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 font-display text-lg font-bold text-soil-900">Nickname</h2>
                <p class="mb-4 font-body text-sm text-soil-600">This is how your name appears on questions and answers.</p>

                @if (session('status') === 'name-updated')
                    <p class="mb-4 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
                        Nickname updated.
                    </p>
                @endif
                @if ($errors->has('name'))
                    <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">
                        {{ $errors->first('name') }}
                    </p>
                @endif

                <form method="post" action="{{ route('settings.name') }}" class="flex gap-3">
                    @csrf
                    <input
                        name="name" type="text" required minlength="2" maxlength="40"
                        value="{{ old('name', $user->name) }}"
                        class="flex-1 rounded-xl border-soil-300 font-body shadow-sm"
                    />
                    <button type="submit"
                        class="rounded-xl bg-leaf-600 px-4 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500 transition-colors">
                        Save
                    </button>
                </form>
            </div>

            {{-- ── Change Email ─────────────────────────────────────────── --}}
            <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 font-display text-lg font-bold text-soil-900">Email Address</h2>
                <p class="mb-1 font-body text-sm text-soil-600">
                    Current: <span class="font-medium text-soil-800">{{ $user->email }}</span>
                </p>
                <p class="mb-4 font-body text-sm text-soil-600">A confirmation link will be sent to the new address before it becomes active.</p>

                @if (session('status') === 'email-pending')
                    <p class="mb-4 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
                        Verification email sent to <strong>{{ session('newEmail') }}</strong>. Click the link to confirm the change.
                    </p>
                @endif
                @if (session('status') === 'email-confirmed')
                    <p class="mb-4 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
                        Your email address has been updated.
                    </p>
                @endif
                @if ($errors->has('newEmail') || $errors->has('email'))
                    <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">
                        {{ $errors->first('newEmail') ?: $errors->first('email') }}
                    </p>
                @endif

                <form method="post" action="{{ route('settings.email') }}" class="flex gap-3">
                    @csrf
                    <input
                        name="newEmail" type="email" required
                        placeholder="New email address"
                        class="flex-1 rounded-xl border-soil-300 font-body shadow-sm"
                    />
                    <button type="submit"
                        class="rounded-xl bg-leaf-600 px-4 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500 transition-colors">
                        Update
                    </button>
                </form>
            </div>

            {{-- ── Change Password ────────────────────────────────────── --}}
            <div class="rounded-2xl border border-leaf-200 bg-white p-6 shadow-sm">
                <h2 class="mb-1 font-display text-lg font-bold text-soil-900">Password</h2>
                <p class="mb-4 font-body text-sm text-soil-600">Choose a strong password of at least 8 characters.</p>

                @if (session('status') === 'password-updated')
                    <p class="mb-4 rounded-xl border border-leaf-200 bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
                        Password updated successfully.
                    </p>
                @endif
                @if ($errors->has('currentPassword') || $errors->has('newPassword') || $errors->has('confirmPassword'))
                    <p class="mb-4 rounded-xl border border-poppy-100 bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">
                        {{ $errors->first('currentPassword') ?: $errors->first('newPassword') ?: $errors->first('confirmPassword') }}
                    </p>
                @endif

                <form method="post" action="{{ route('settings.password') }}" class="space-y-3">
                    @csrf
                    <input name="currentPassword" type="password" required placeholder="Current password"
                        class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
                    <input name="newPassword" type="password" required minlength="8" placeholder="New password"
                        class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
                    <input name="confirmPassword" type="password" required placeholder="Confirm new password"
                        class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
                    <div class="pt-1">
                        <button type="submit"
                            class="rounded-xl bg-leaf-600 px-5 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500 transition-colors">
                            Update password
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>