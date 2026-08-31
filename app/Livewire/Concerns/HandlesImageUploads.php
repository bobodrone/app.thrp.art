<?php

namespace App\Livewire\Concerns;

use App\Rules\SafeSvg;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Staging, validating and storing an uploaded image. Every limit comes from the
 * config/uploads.php entry named by uploadConfigKey() on the using component.
 *
 * Raster and SVG take deliberately different paths. A raster image is inert
 * bytes: validated by real MIME type and stored as uploaded, on the public disk
 * that Apache serves directly. An SVG is a document that would run script if it
 * were ever fetched as a page, so it is validated by App\Rules\SafeSvg, stripped
 * by the sanitiser, stored on a private disk and served only by
 * App\Http\Controllers\ServeUploadedSvg, which makes the response inert.
 */
trait HandlesImageUploads
{
    /** Key under `uploads.` in config/uploads.php. */
    abstract protected function uploadConfigKey(): string;

    protected function uploadConfig(): array
    {
        return config('uploads.'.$this->uploadConfigKey());
    }

    protected function svgEnabled(): bool
    {
        return (bool) $this->uploadConfig()['svg']['enabled'];
    }

    /** Extensions offered to the picker: the raster list, plus SVG when enabled. */
    protected function acceptedExtensions(): array
    {
        $config = $this->uploadConfig();

        return $this->svgEnabled()
            ? [...$config['extensions'], 'svg']
            : $config['extensions'];
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

        // Livewire's temporary-file URL would serve the upload exactly as it
        // arrived — unsanitised, from this app's origin. Signed and short-lived,
        // but there is no reason to hand out that URL at all: inline the
        // sanitised markup instead, which an <img> renders identically.
        if ($this->isSvg($file)) {
            $clean = $this->sanitiseSvg((string) file_get_contents($file->getRealPath()));

            return $clean === null
                ? null
                : 'data:image/svg+xml;base64,'.base64_encode($clean);
        }

        try {
            return $file->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The content rules depend on what was actually picked, so they are chosen
     * from the pending upload rather than combined into one set. A raster image
     * is checked against its real MIME type and the raster cap; an SVG gets
     * neither — `mimetypes:` is unreliable for it, and its own cap is far
     * smaller — and is handed to SafeSvg instead.
     */
    protected function imageRules(string $field): array
    {
        $config = $this->uploadConfig();
        $file   = $this->{$field} ?? null;

        $rules = [
            'nullable',
            'file',
            'extensions:'.implode(',', $this->acceptedExtensions()),
        ];

        if ($this->svgEnabled() && $file instanceof TemporaryUploadedFile && $this->isSvg($file)) {
            $rules[] = new SafeSvg($config['svg']['max_kb']);
        } else {
            $rules[] = 'mimetypes:'.implode(',', $config['mime_types']);
            $rules[] = 'max:'.$config['max_kb'];
        }

        return [$field => $rules];
    }

    protected function imageMessages(string $field): array
    {
        $config = $this->uploadConfig();

        return [
            "{$field}.extensions" => 'Image must be a '.implode(', ', $this->acceptedExtensions()).' file.',
            "{$field}.mimetypes"  => 'That file is not a valid image.',
            "{$field}.max"        => 'Image must be smaller than '.round($config['max_kb'] / 1024, 1).' MB.',
            "{$field}.uploaded"   => 'Upload failed — the file may be larger than the server allows.',
        ];
    }

    /**
     * Returns the stored path. The extension in that path is what later decides
     * which disk the file lives on and how it is served, so no extra column is
     * needed to tell an SVG from a raster image.
     */
    protected function storeImage(TemporaryUploadedFile $file): string
    {
        $config = $this->uploadConfig();

        if ($this->isSvg($file)) {
            $clean = $this->sanitiseSvg((string) file_get_contents($file->getRealPath()));

            // SafeSvg has already passed, so unparseable here means the
            // sanitiser disagrees with the validator. Refuse rather than guess.
            if ($clean === null) {
                throw ValidationException::withMessages([
                    'image' => 'That SVG could not be processed safely.',
                ]);
            }

            // Never the client's filename, and always .svg whatever was claimed.
            $path = $config['directory'].'/'.Str::random(40).'.svg';

            Storage::disk($config['svg']['disk'])->put($path, $clean);

            return $path;
        }

        // store() picks a random filename — never trust the client's.
        return $file->store($config['directory'], $config['disk']);
    }

    protected function deleteImage(?string $path): void
    {
        if ($path !== null) {
            Storage::disk(uploaded_image_disk($path, $this->uploadConfigKey()))->delete($path);
        }
    }

    private function isSvg(TemporaryUploadedFile $file): bool
    {
        return strtolower((string) $file->getClientOriginalExtension()) === 'svg';
    }

    /**
     * Strip everything executable or remote. Returns null when the sanitiser
     * cannot make sense of the input, which it signals both by returning false
     * and — for input that is well-formed XML but not an SVG — by throwing.
     * Either way the answer is the same: refuse the file.
     */
    private function sanitiseSvg(string $svg): ?string
    {
        try {
            $sanitizer = new Sanitizer;
            $sanitizer->removeRemoteReferences(true);

            $clean = $sanitizer->sanitize($svg);
        } catch (\Throwable) {
            return null;
        }

        return $clean === false ? null : $clean;
    }
}
