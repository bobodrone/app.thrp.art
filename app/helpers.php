<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

if (! function_exists('format_date')) {
    /**
     * Format a Carbon date as "YYYY-MM-DD HH:mm" (matches the SvelteKit formatDate util).
     */
    function format_date(Illuminate\Support\Carbon|Carbon|DateTimeInterface|string|null $date): string
    {
        if ($date === null) {
            return '';
        }

        return Illuminate\Support\Carbon::parse($date)->format('Y-m-d H:i');
    }
}

if (! function_exists('uploaded_image_url')) {
    /**
     * Public URL of a stored upload, or null when there is none.
     *
     * The extension decides where it comes from. Raster images are served off
     * the public/storage symlink by the web server; SVG lives on a private disk
     * and comes out only through the media route, which strips it of any power
     * to act as a page. See App\Http\Controllers\ServeUploadedSvg.
     *
     * @param  string  $configKey  Entry in config/uploads.php.
     */
    function uploaded_image_url(?string $path, string $configKey): ?string
    {
        if ($path === null) {
            return null;
        }

        if (str_ends_with(strtolower($path), '.svg')) {
            return route('media.svg', ['path' => $path]);
        }

        return Storage::disk(config("uploads.{$configKey}.disk"))->url($path);
    }
}

if (! function_exists('uploaded_image_disk')) {
    /**
     * Which configured disk a stored upload lives on.
     *
     * Decided by the extension, so no extra column is needed to tell the two
     * apart: SVG is kept off the public disk deliberately (see
     * App\Http\Controllers\ServeUploadedSvg), everything else is served
     * statically from it.
     *
     * @param  string  $configKey  Entry in config/uploads.php.
     */
    function uploaded_image_disk(string $path, string $configKey): string
    {
        $config = config("uploads.{$configKey}");

        return str_ends_with(strtolower($path), '.svg')
            ? $config['svg']['disk']
            : $config['disk'];
    }
}
