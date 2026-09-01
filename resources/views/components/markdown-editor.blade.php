@props([
    'name' => 'answer',
    'initial' => '',
    'wireModel' => null,
])

<div
    x-data="{ preview: '' }"
    x-init="if (window.thrpMarkdown) preview = window.thrpMarkdown(@js($initial))"
    class="grid grid-cols-2 gap-4"
>
    <div class="flex flex-col gap-1">
        <p class="font-body text-xs font-medium text-soil-600">Write (Markdown)</p>
        <textarea
            @if ($wireModel) wire:model="{{ $wireModel }}" @else name="{{ $name }}" @endif
            x-on:input="if (window.thrpMarkdown) preview = window.thrpMarkdown($event.target.value)"
            rows="14"
            placeholder="Write your response in Markdown…"
            class="flex-1 resize-none rounded-xl border-leaf-200 font-mono text-sm shadow-sm"
        >{{ $initial }}</textarea>
    </div>

    <div class="flex flex-col gap-1">
        <p class="font-body text-xs font-medium text-soil-600">Preview</p>
        <div wire:ignore
            x-show="preview"
            x-cloak
            class="min-h-[14rem] overflow-auto rounded-xl border border-leaf-200 bg-soil-50 p-3 prose prose-sm max-w-none prose-headings:font-display"
            x-html="preview"
        ></div>
        <div
            x-show="!preview"
            x-cloak
            class="flex min-h-[14rem] items-center justify-center rounded-xl border border-leaf-200 bg-soil-50 p-3"
        >
            <p class="font-body text-sm italic text-soil-400">Preview will appear here…</p>
        </div>
    </div>

    <x-markdown-cheatsheet />
</div>