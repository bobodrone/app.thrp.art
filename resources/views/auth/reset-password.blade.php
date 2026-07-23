<x-guest-layout>
    <x-slot name="title">Set new password — THRP</x-slot>

    <x-auth-card
        flowerPetal="#1565C0"
        flowerCenter="#1A1209"
        flowerDot="#FFD600"
        title="Set new password"
        subtitle="Choose a strong password of at least 8 characters.">

        @if ($errors->any())
            <p class="mb-4 rounded-xl bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">
                {{ $errors->first() }}
            </p>
        @endif

        <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}" />

            <div>
                <label for="email" class="mb-1 block font-body text-sm font-medium text-soil-700">Email</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="username"
                    value="{{ old('email', $request->query('email')) }}"
                    class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
            </div>

            <div>
                <label for="password" class="mb-1 block font-body text-sm font-medium text-soil-700">New password</label>
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                    class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
            </div>

            <div>
                <label for="password_confirmation" class="mb-1 block font-body text-sm font-medium text-soil-700">Confirm password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
            </div>

            <button type="submit"
                class="w-full rounded-xl bg-soil-900 py-3 font-body font-semibold text-sun-500 hover:bg-soil-800 transition-colors">
                Update password
            </button>
        </form>
    </x-auth-card>
</x-guest-layout>