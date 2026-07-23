@php
    use App\Enums\UserRole;
    $user = auth()->user();
@endphp

<nav class="relative z-50 bg-leaf-700 border-b-2 border-leaf-600">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5">

        <!-- Logo -->
        <a href="{{ route('home') }}" class="flex items-center gap-2 group">
            <x-flower size="30" petalColor="#FFD600" centerColor="#124029" dotColor="#FFD600" />
            <span class="font-display text-xl font-black tracking-tight text-sun-500 group-hover:text-sun-400 transition-colors">
                THRP
            </span>
        </a>

        <!-- Links -->
        <div class="flex items-center gap-5 text-sm font-medium" x-data="{ open: false }" @click.away="open = false">

            @if ($user)
                @if (in_array($user->role->value, [UserRole::Creator->value, UserRole::Admin->value]))
                    <a href="{{ route('creator.dashboard') }}" class="text-leaf-100 hover:text-white transition-colors">Creator</a>
                @endif

                @if ($user->role === UserRole::Admin)
                    <a href="{{ route('admin.questions') }}" class="text-leaf-100 hover:text-white transition-colors">Questions</a>
                    <a href="{{ route('admin.creators') }}" class="text-leaf-100 hover:text-white transition-colors">Creators</a>
                    <a href="{{ route('admin.users') }}" class="text-leaf-100 hover:text-white transition-colors">Admins</a>
                @endif

                <a href="{{ route('my-questions') }}" class="text-leaf-100 hover:text-white transition-colors">My Questions</a>

                @if ($user->role === UserRole::Member)
                    <a href="{{ route('apply') }}" class="text-leaf-100 hover:text-white transition-colors">Become a creator</a>
                @endif

                <!-- User dropdown -->
                <div class="relative">
                    <button
                        type="button"
                        @click="open = !open"
                        class="flex items-center gap-1.5 rounded-full bg-leaf-600 px-3 py-1.5 text-sun-400 hover:bg-leaf-500 hover:text-sun-300 transition-colors"
                    >
                        {{ $user->name }}
                        <svg class="h-3 w-3 opacity-60" viewBox="0 0 12 12" fill="currentColor"><path d="M6 8L1 3h10L6 8z"/></svg>
                    </button>

                    <div
                        x-show="open"
                        x-cloak
                        x-transition
                        class="absolute right-0 top-full z-10 mt-1.5 w-44 rounded-xl border border-leaf-600 bg-leaf-800 py-1 shadow-xl"
                    >
                        <a href="{{ route('settings') }}" class="block px-4 py-2.5 text-sm text-leaf-100 hover:bg-leaf-700 hover:text-white transition-colors">Settings</a>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2.5 text-left text-sm text-leaf-100 hover:bg-leaf-700 hover:text-white transition-colors">Sign out</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('apply') }}" class="text-leaf-100 hover:text-white transition-colors">Become a creator</a>
                <a href="{{ route('register') }}" class="text-leaf-100 hover:text-white transition-colors">Register</a>
                <a href="{{ route('login') }}" class="rounded-lg bg-sun-500 px-4 py-2 text-soil-900 font-semibold hover:bg-sun-400 transition-colors">Sign in</a>
            @endif
        </div>
    </div>
</nav>