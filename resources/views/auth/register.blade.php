<x-guest-layout>
    <x-slot name="title">Register — THRP</x-slot>

    @php
        $registered = session('registered', false);
        $registeredEmail = session('email', old('email'));
    @endphp

    @if ($registered)
        <x-auth-card flowerPetal="#1A5C38" flowerCenter="#1A1209" flowerDot="#FFD600">
            <div class="text-center">
                <h1 class="font-display mb-2 text-2xl font-bold text-soil-900">Check your email</h1>
                <p class="font-body text-soil-600">
                    We sent a verification link to <strong class="text-soil-900">{{ $registeredEmail }}</strong>. Click it to activate your account.
                </p>
                <p class="mt-4 font-body text-sm text-soil-400">
                    Already verified? <a href="{{ route('login') }}" class="font-semibold text-leaf-600 hover:underline">Sign in</a>
                </p>
            </div>
        </x-auth-card>
    @else
        <x-auth-card
            flowerPetal="#1A5C38"
            flowerCenter="#1A1209"
            flowerDot="#FFD600"
            title="Create an account"
            subtitle="Join The Human Response Project">

            @if ($errors->any())
                <p class="mb-4 rounded-xl bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">
                    {{ $errors->first() }}
                </p>
            @endif

            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block font-body text-sm font-medium text-soil-700">Email</label>
                    <input id="email" name="email" type="email" required value="{{ old('email') }}"
                        class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
                </div>

                <div>
                    <label for="name" class="mb-1 block font-body text-sm font-medium text-soil-700">Nickname</label>
                    <input id="name" name="name" type="text" required minlength="2" maxlength="40" value="{{ old('name') }}"
                        class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
                </div>

                <div>
                    <label for="password" class="mb-1 block font-body text-sm font-medium text-soil-700">Password</label>
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
                    Create account
                </button>
            </form>

            <x-slot:footer>
                Already have an account? <a href="{{ route('login') }}" class="font-semibold text-soil-900 underline underline-offset-2">Sign in</a>
            </x-slot:footer>
        </x-auth-card>
    @endif
</x-guest-layout>