@props([
    'answer',           // the answer to credit, or null when there is none
    'viewer' => null,   // who is looking — admins see through anonymity
])

@php
    $name    = $answer?->authorNameFor($viewer);
    $creator = $answer?->author;

    // An anonymous answer never links, not even for admins who can see the real
    // name: the href would carry the creator's id straight into the markup, and
    // that markup is one copy-paste away from a public view.
    //
    // The role check needs `role` on the eager load (`author:id,name,role`) —
    // a partially loaded User silently reports the default role instead.
    $linkable = $name !== null
        && ! $answer->anonymously
        && $creator?->isCreator();
@endphp

@if ($name === null)
    {{-- nobody to credit --}}
@elseif ($linkable)
    <a href="{{ route('creators.show', $creator) }}" {{ $attributes->merge(['class' => 'hover:underline']) }}>{{ $name }}</a>
@else
    <span {{ $attributes }}>{{ $name }}</span>
@endif
