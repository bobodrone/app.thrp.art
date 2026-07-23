<x-guest-layout>
    <x-slot name="title">Forgot password — THRP</x-slot>

    @php $sent = session('status') === 'reset-link-sent'; @endphp

    @if ($sent)
        <x-auth-card flowerPetal="#C1121F" flowerCenter="#1A1209" flowerDot="#FFD600">
            <div class="text-center">
                <h1 class="font-display mb-2 text-2xl font-bold text-soil-900">Check your email</h1>
                <p class="font-body text-soil-600">If that address is registered, you'll receive a reset link shortly.</p>
                <p class="mt-6 font-body text-sm"><a href="{{ route('login') }}" class="font-semibold text-leaf-600 hover:underline">Back to sign in</a></p>
            </div>
        </x-auth-card>
    @else
        <x-auth-card
            flowerPetal="#C1121F"
            flowerCenter="#1A1209"
            flowerDot="#FFD600"
            title="Forgot password?"
            subtitle="Enter your email and we'll send a reset link.">

            @if ($errors->any())
                <p class="mb-4 rounded-xl bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">
                    {{ $errors->first() }}
                </p>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="mb-1 block font-body text-sm font-medium text-soil-700">Email</label>
                    <input id="email" name="email" type="email" required value="{{ old('email') }}"
                        class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
                </div>

                <button type="submit"
                    class="w-full rounded-xl bg-soil-900 py-3 font-body font-semibold text-sun-500 hover:bg-soil-800 transition-colors">
                    Send reset link
                </button>
            </form>

            <x-slot:footer>
                <a href="{{ route('login') }}" class="font-semibold text-soil-900 underline underline-offset-2">Back to sign in</a>
            </x-slot:footer>
        </x-auth-card>
    @endif
</x-guest-layout>