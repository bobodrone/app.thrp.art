<x-guest-layout>
    <x-slot name="title">Sign in — THRP</x-slot>

    <x-auth-card title="Welcome back" subtitle="Sign in to your account">

        @if (session('status') === 'password-reset')
            <p class="mb-4 rounded-xl bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
                Password updated — sign in with your new password.
            </p>
        @endif

        @if ($errors->any())
            <p class="mb-4 rounded-xl bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">
                {{ $errors->first() }}
                @if (session('unverified')) — check your inbox for the verification link. @endif
            </p>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="next" value="{{ $next ?? '/' }}" />

            <div>
                <label for="email" class="mb-1 block font-body text-sm font-medium text-soil-700">Email</label>
                <input id="email" name="email" type="email" required autofocus autocomplete="username"
                    value="{{ old('email') }}"
                    class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
            </div>

            <div>
                <label for="password" class="mb-1 block font-body text-sm font-medium text-soil-700">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
            </div>

            <button type="submit"
                class="w-full rounded-xl bg-soil-900 py-3 font-body font-semibold text-sun-500 hover:bg-soil-800 transition-colors">
                Sign in
            </button>
        </form>

        <p class="mt-4 text-center font-body text-sm text-soil-400">
            <a href="{{ route('password.request') }}" class="text-leaf-600 hover:underline">Forgot password?</a>
        </p>

        <x-slot:footer>
            No account? <a href="{{ route('register') }}" class="font-semibold text-soil-900 underline underline-offset-2">Register</a>
        </x-slot:footer>
    </x-auth-card>
</x-guest-layout>