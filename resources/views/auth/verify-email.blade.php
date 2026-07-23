<x-guest-layout>
    <x-slot name="title">Verify your email — THRP</x-slot>

    <x-auth-card
        title="Verify your email"
        subtitle="Thanks for signing up! Click the verification link in the email we just sent you to activate your account.">

        @if (session('status') === 'verification-link-sent')
            <p class="mb-4 rounded-xl bg-leaf-100 px-4 py-3 font-body text-sm text-leaf-700">
                A new verification link has been sent to your email address.
            </p>
        @endif

        <div class="mt-2 space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit"
                    class="w-full rounded-xl bg-soil-900 py-3 font-body font-semibold text-sun-500 hover:bg-soil-800 transition-colors">
                    Resend verification email
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                    class="w-full text-center font-body text-sm text-leaf-600 hover:underline">
                    Sign out
                </button>
            </form>
        </div>
    </x-auth-card>
</x-guest-layout>