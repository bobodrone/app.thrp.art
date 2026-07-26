<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Staging, validating and storing an uploaded image. Every limit comes from the
 * config/uploads.php entry named by uploadConfigKey() on the using component.
 */
trait HandlesImageUploads
{
    /** Key under `uploads.` in config/uploads.php. */
    abstract protected function uploadConfigKey(): string;

    protected function uploadConfig(): array
    {
        return config('uploads.'.$this->uploadConfigKey());
    }

    /**
     * What the upload widget should display: the staged upload if there is one,
     * otherwise the fallback (usually the already-saved image).
     *
     * Livewire cannot build a preview URL for every format it accepts — HEIC has
     * no entry in livewire.temporary_file_upload.preview_mimes — so a failure
     * here just means "no server-side preview", not a broken upload.
     */
    protected function previewUrl(?TemporaryUploadedFile $file, ?string $fallback = null): ?string
    {
        if ($file === null) {
            return $fallback;
        }

        try {
            return $file->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function imageRules(string $field): array
    {
        $config = $this->uploadConfig();

        return [$field => [
            'nullable',
            'file',
            'extensions:'.implode(',', $config['extensions']),
            'mimetypes:'.implode(',', $config['mime_types']),
            'max:'.$config['max_kb'],
        ]];
    }

    protected function imageMessages(string $field): array
    {
        $config = $this->uploadConfig();

        return [
            "{$field}.extensions" => 'Image must be a '.implode(', ', $config['extensions']).' file.',
            "{$field}.mimetypes"  => 'That file is not a valid image.',
            "{$field}.max"        => 'Image must be smaller than '.round($config['max_kb'] / 1024, 1).' MB.',
            "{$field}.uploaded"   => 'Upload failed — the file may be larger than the server allows.',
        ];
    }

    protected function storeImage(TemporaryUploadedFile $file): string
    {
        $config = $this->uploadConfig();

        // store() picks a random filename — never trust the client's.
        return $file->store($config['directory'], $config['disk']);
    }

    protected function deleteImage(?string $path): void
    {
        if ($path !== null) {
            Storage::disk($this->uploadConfig()['disk'])->delete($path);
        }
    }
}
