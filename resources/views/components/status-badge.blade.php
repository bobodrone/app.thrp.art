@props([
    'status' => null,
])

@php
    /** @var \App\Enums\QuestionStatus|string|null $status */
    $value = $status instanceof \App\Enums\QuestionStatus ? $status->value : (string) $status;

    $style = match ($value) {
        'asked'    => 'bg-sun-100 text-soil-700',
        'claimed'  => 'bg-sky-100 text-sky-600',
        'answered' => 'bg-leaf-600 text-white',
        default     => 'bg-soil-200 text-soil-700',
    };
    $label = match ($value) {
        'asked'    => 'Asked',
        'claimed'  => 'In progress',
        'answered' => 'Responded',
        default     => ucfirst($value ?? ''),
    };
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 font-body text-xs font-semibold {{ $style }}">
    {{ $label }}
</span>