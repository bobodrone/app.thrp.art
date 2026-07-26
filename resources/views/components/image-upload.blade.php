@props([
    'wireModel',              // Livewire property holding the pending upload
    'clearAction' => null,    // Livewire method that clears it
    'currentUrl'  => null,    // server-rendered preview: staged upload, else saved image
    'label'       => 'Image (optional)',
    'config'      => 'answer_image',  // which config/uploads.php entry sets the limits
    'removeLabel' => 'Remove image',
])

@php
    $config  = config('uploads.'.$config);
    // Accept real MIME types only. iOS transcodes HEIC photos to JPEG when the
    // picker's accept list excludes HEIC, which is why we never list it here.
    $accept  = implode(',', $config['mime_types']);
    $maxMb   = round($config['max_kb'] / 1024, 1);
    $hint    = strtoupper(implode(', ', $config['extensions'])).' · max '.$maxMb.' MB';
    $inputId = 'image-upload-'.$wireModel;
@endphp

{{--
    What's on screen comes from the server ($currentUrl — the staged upload's
    temporary URL, or the saved image). Alpine only holds `localPreview`, an
    optimistic thumbnail shown while the file is still uploading: Livewire
    re-renders wipe Alpine state, so it can never be the source of truth.
--}}
<div
    x-data="{
        dragging: false,
        uploading: false,
        progress: 0,
        localPreview: null,
        // Dropping a file feeds it to the input, whose change event then drives
        // both Livewire's upload and showPreview() below.
        drop(files) {
            if (! files || ! files.length) return;
            this.$refs.input.files = files;
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
        },
        showPreview(file) {
            if (! file) return;
            const reader = new FileReader();
            reader.onload = (e) => { this.localPreview = e.target.result };
            reader.readAsDataURL(file);
        },
        clear() {
            this.localPreview = null;
            this.progress = 0;
            this.$refs.input.value = '';
        },
    }"
    x-on:livewire-upload-start="uploading = true; progress = 0"
    x-on:livewire-upload-finish="uploading = false; progress = 100"
    x-on:livewire-upload-error="uploading = false; clear()"
    x-on:livewire-upload-progress="progress = $event.detail.progress"
    class="flex flex-col gap-1"
>
    @if ($label !== '')
        <p class="font-body text-xs font-medium text-soil-600">{{ $label }}</p>
    @endif

    <label
        for="{{ $inputId }}"
        x-on:dragover.prevent="dragging = true"
        x-on:dragleave.prevent="dragging = false"
        x-on:drop.prevent="dragging = false; drop($event.dataTransfer.files)"
        :class="dragging ? 'border-leaf-600 bg-leaf-50' : 'border-leaf-200 bg-soil-50'"
        class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-4 text-center transition-colors hover:border-leaf-600"
    >
        @if ($currentUrl)
            <img src="{{ $currentUrl }}" alt="" class="max-h-48 w-auto rounded-lg object-contain">
        @else
            {{-- Nothing staged server-side yet: the in-flight thumbnail, if any --}}
            <template x-if="localPreview">
                <img :src="localPreview" alt="" class="max-h-48 w-auto rounded-lg object-contain">
            </template>

            <template x-if="! localPreview">
                <div class="py-4">
                    <p class="font-body text-sm font-medium text-soil-600">
                        <span class="hidden sm:inline">Drag an image here, or </span>tap to choose a photo
                    </p>
                    <p class="mt-1 font-body text-xs text-soil-400">{{ $hint }}</p>
                </div>
            </template>
        @endif

        <input
            id="{{ $inputId }}"
            x-ref="input"
            type="file"
            wire:model="{{ $wireModel }}"
            x-on:change="showPreview($event.target.files[0])"
            accept="{{ $accept }}"
            class="sr-only"
        >
    </label>

    {{-- Upload progress --}}
    <div x-show="uploading" x-cloak class="h-1.5 w-full overflow-hidden rounded-full bg-leaf-100">
        <div class="h-full bg-leaf-600 transition-all" :style="`width: ${progress}%`"></div>
    </div>

    <div class="flex items-center justify-between gap-3">
        <p x-show="uploading" x-cloak class="font-body text-xs text-soil-400">Uploading… <span x-text="progress + '%'"></span></p>

        @if ($clearAction)
            <button
                type="button"
                x-show="localPreview || {{ $currentUrl ? 'true' : 'false' }}"
                x-cloak
                x-on:click="clear()"
                wire:click="{{ $clearAction }}"
                class="ml-auto font-body text-xs text-soil-400 hover:text-poppy-600 hover:underline"
            >
                {{ $removeLabel }}
            </button>
        @endif
    </div>

    @error($wireModel)
        <p class="font-body text-xs text-poppy-600">{{ $message }}</p>
    @enderror
</div>
