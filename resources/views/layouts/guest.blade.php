{{--
    Forward through the application layout so auth pages also get the green
    navigation bar (matches the SvelteKit app, which renders auth routes inside
    the root layout with the nav always visible). The yellow hero + white card
    chrome is provided per-page by <x-auth-card>.
--}}
<x-app-layout>
    {{ $slot }}
</x-app-layout>