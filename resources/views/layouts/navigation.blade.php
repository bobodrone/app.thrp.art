@php
    use App\Enums\UserRole;
    use App\Models\ContactMessage;

    $user = auth()->user();

    // Shared link list so the desktop row and mobile panel stay in sync.
    $navLinks = [];
    if ($user) {
        if (in_array($user->role->value, [UserRole::Creator->value, UserRole::Admin->value])) {
            $navLinks[] = ['label' => 'Responder Dashboard', 'url' => route('creator.dashboard')];
        }
        if ($user->role === UserRole::Admin) {
            $navLinks[] = ['label' => 'Questions admin', 'url' => route('admin.questions')];
            $navLinks[] = ['label' => 'Users admin', 'url' => route('admin.users')];
            $navLinks[] = ['label' => 'Applications', 'url' => route('admin.applications')];

            // Only admins ever run this count, and only they see the badge.
            $openMessages = ContactMessage::unhandled()->count();
            $navLinks[] = [
                'label' => 'Messages',
                'url'   => route('admin.messages'),
                'badge' => $openMessages ?: null,
            ];
        }
        $navLinks[] = ['label' => 'My Questions', 'url' => route('my-questions')];
        if ($user->role === UserRole::Member) {
            $navLinks[] = ['label' => 'Become a responder', 'url' => route('apply')];
        }
    }
@endphp

<nav x-data="{ mobileOpen: false }" class="relative z-50 bg-leaf-700 border-b-2 border-leaf-600">
    <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-5">

        <!-- Logo -->
        <a href="{{ route('home') }}" class="flex items-center gap-2 group">
            <x-flower size="30" petalColor="#FFD600" centerColor="#124029" dotColor="#FFD600" />
            <span class="font-display text-xl font-black tracking-tight text-sun-500 group-hover:text-sun-400 transition-colors">
                THRP
            </span>
        </a>

        <!-- Desktop links -->
        <div class="hidden md:flex items-center gap-5 text-sm font-medium" x-data="{ open: false }" @click.away="open = false">
            <a href="https://thrp.art" target="_blank" class="text-sun-400 hover:text-white transition-colors">What’s it all about?</a>
            <a href="{{ route('creators.index') }}" class="text-leaf-100 hover:text-white transition-colors">Responders</a>
            <a href="{{ route('contact') }}" class="text-leaf-100 hover:text-white transition-colors">Contact</a>
            @if ($user)
                @foreach ($navLinks as $link)
                    <a href="{{ $link['url'] }}" class="text-leaf-100 hover:text-white transition-colors">
                        {{ $link['label'] }}
                        @if (($link['badge'] ?? null))
                            <span class="ml-1 rounded-full bg-sun-500 px-1.5 py-0.5 text-xs font-semibold text-soil-900">{{ $link['badge'] }}</span>
                        @endif
                    </a>
                @endforeach

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
                        @if ($user->isCreator())
                            <a href="{{ route('creator.profile') }}" class="block px-4 py-2.5 text-sm text-leaf-100 hover:bg-leaf-700 hover:text-white transition-colors">Responder profile</a>
                        @endif
                        <a href="{{ route('settings') }}" class="block px-4 py-2.5 text-sm text-leaf-100 hover:bg-leaf-700 hover:text-white transition-colors">Settings</a>
                        <form method="post" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2.5 text-left text-sm text-leaf-100 hover:bg-leaf-700 hover:text-white transition-colors">Sign out</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="{{ route('apply') }}" class="text-leaf-100 hover:text-white transition-colors">Become a responder</a>
                <a href="{{ route('register') }}" class="text-leaf-100 hover:text-white transition-colors">Register</a>
                <a href="{{ route('login') }}" class="rounded-lg bg-sun-500 px-4 py-2 text-soil-900 font-semibold hover:bg-sun-400 transition-colors">Sign in</a>
            @endif
        </div>

        <!-- Hamburger (mobile) -->
        <button
            type="button"
            @click="mobileOpen = !mobileOpen"
            :aria-expanded="mobileOpen"
            aria-label="Toggle navigation menu"
            class="md:hidden inline-flex items-center justify-center rounded-lg p-2 text-sun-400 hover:bg-leaf-600 hover:text-white transition-colors"
        >
            <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/>
            </svg>
        </button>
    </div>

    <!-- Mobile panel -->
    <div
        x-show="mobileOpen"
        x-cloak
        x-transition.origin.top
        @click.away="mobileOpen = false"
        class="md:hidden border-t border-leaf-600 bg-leaf-700 px-5 pb-4 pt-2"
    >
        <div class="flex flex-col text-sm font-medium">
            <a href="https://thrp.art" target="_blank" class="py-2.5 text-sun-400 hover:text-white transition-colors">What’s it all about?</a>
            <a href="{{ route('creators.index') }}" class="py-2.5 text-leaf-100 hover:text-white transition-colors">Responders</a>
            <a href="{{ route('contact') }}" class="py-2.5 text-leaf-100 hover:text-white transition-colors">Contact</a>
            @if ($user)
                @foreach ($navLinks as $link)
                    <a href="{{ $link['url'] }}" class="py-2.5 text-leaf-100 hover:text-white transition-colors">
                        {{ $link['label'] }}
                        @if (($link['badge'] ?? null))
                            <span class="ml-1 rounded-full bg-sun-500 px-1.5 py-0.5 text-xs font-semibold text-soil-900">{{ $link['badge'] }}</span>
                        @endif
                    </a>
                @endforeach

                <div class="my-2 border-t border-leaf-600"></div>

                <span class="py-1 text-xs uppercase tracking-wider text-leaf-300">{{ $user->name }}</span>
                @if ($user->isCreator())
                    <a href="{{ route('creator.profile') }}" class="py-2.5 text-leaf-100 hover:text-white transition-colors">Responder profile</a>
                @endif
                <a href="{{ route('settings') }}" class="py-2.5 text-leaf-100 hover:text-white transition-colors">Settings</a>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full py-2.5 text-left text-leaf-100 hover:text-white transition-colors">Sign out</button>
                </form>
            @else
                <a href="{{ route('apply') }}" class="py-2.5 text-leaf-100 hover:text-white transition-colors">Become a responder</a>
                <a href="{{ route('register') }}" class="py-2.5 text-leaf-100 hover:text-white transition-colors">Register</a>
                <a href="{{ route('login') }}" class="mt-2 rounded-lg bg-sun-500 px-4 py-2.5 text-center text-soil-900 font-semibold hover:bg-sun-400 transition-colors">Sign in</a>
            @endif
        </div>
    </div>
</nav>
