<x-guest-layout>
    <x-slot name="title">Admin Setup — THRP</x-slot>

    <x-auth-card title="Admin Setup" subtitle="">
        <p class="mb-6 font-body text-sm text-soil-600">
            One-time bootstrap. Register a regular account first, then use your email and the
            <code class="rounded bg-soil-100 px-1 py-0.5 text-xs">BOOTSTRAP_TOKEN</code> from
            <code class="rounded bg-soil-100 px-1 py-0.5 text-xs">.env</code> to claim admin status.
        </p>

        @if ($errors->any())
            <p class="mb-4 rounded-xl bg-poppy-100 px-4 py-3 font-body text-sm text-poppy-600">
                {{ $errors->first() }}
            </p>
        @endif

        <form method="POST" action="{{ route('admin.setup') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block font-body text-sm font-medium text-soil-700">
                    Your registered email
                </label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}"
                    class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
            </div>
            <div>
                <label for="token" class="mb-1 block font-body text-sm font-medium text-soil-700">
                    Bootstrap token
                </label>
                <input id="token" name="token" type="password" required
                    class="w-full rounded-xl border-soil-300 font-body shadow-sm" />
            </div>
            <button type="submit"
                class="w-full rounded-xl bg-soil-900 py-3 font-body font-semibold text-sun-500 hover:bg-soil-800 transition-colors">
                Claim admin access
            </button>
        </form>
    </x-auth-card>
</x-guest-layout>