@props([
    'draft',            // current answerDraft text
    'imagePreview',     // staged upload, saved image, or null once cleared
    'heading' => 'Edit response',
])

{{-- Bound to the CreatorQuestionDetail component; only one answer is ever open. --}}
<p class="mb-4 font-body text-sm font-medium text-soil-700">{{ $heading }}</p>

<div class="space-y-4">
    <x-image-upload
        wire-model="answerImageDraft"
        clear-action="clearAnswerImageDraft"
        :current-url="$imagePreview"
    />
    <x-markdown-editor wire-model="answerDraft" :initial="$draft" />
    @error('answerDraft') <p class="font-body text-xs text-poppy-600">{{ $message }}</p> @enderror

    {{-- Edits are silent unless the responder says otherwise: a typo fix should
         not land in the asker's inbox, a rewrite probably should. --}}
    <label class="flex items-start gap-2.5 font-body text-sm text-soil-600">
        <input type="checkbox" wire:model="notifyAskerOfEdit"
            class="mt-0.5 h-4 w-4 shrink-0 rounded border-soil-300 text-leaf-600 focus:ring-leaf-500">
        <span>Notify the person who asked this question about this edit</span>
    </label>

    <div class="flex justify-end gap-3">
        <button type="button" wire:click="cancelEditAnswer"
            class="rounded-xl border border-soil-300 px-5 py-2 font-body text-sm text-soil-600 hover:bg-soil-50">
            Cancel
        </button>
        <button type="button" wire:click="updateAnswer"
            class="rounded-xl bg-leaf-600 px-6 py-2 font-body text-sm font-semibold text-white hover:bg-leaf-500">
            Save changes
        </button>
    </div>
</div>
