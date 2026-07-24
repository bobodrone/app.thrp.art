<?php

/*
|--------------------------------------------------------------------------
| Upload limits
|--------------------------------------------------------------------------
|
| Limits for creator-supplied files. Everything is driven from .env so a
| deploy is not needed to tighten or loosen them.
|
| Note: PHP's own upload_max_filesize / post_max_size still cap every upload.
| Keep them at or above ANSWER_IMAGE_MAX_KB, otherwise large files are
| rejected by PHP before Laravel ever sees them.
|
*/

$csv = static fn (string $value): array => array_values(array_filter(array_map(
    static fn (string $item): string => strtolower(trim($item)),
    explode(',', $value)
)));

return [

    'answer_image' => [
        // Filesystem disk (config/filesystems.php) the image is stored on.
        'disk' => env('ANSWER_IMAGE_DISK', 'public'),

        // Directory on that disk.
        'directory' => trim((string) env('ANSWER_IMAGE_DIRECTORY', 'answers'), '/'),

        // Max size per image, in kilobytes.
        'max_kb' => (int) env('ANSWER_IMAGE_MAX_KB', 2048),

        // Allowed file extensions. HEIC/HEIF are deliberately absent: only
        // Safari renders them, and iOS transcodes to JPEG on upload when the
        // file picker's accept list excludes them (which it does — see below).
        'extensions' => $csv(env('ANSWER_IMAGE_EXTENSIONS', 'jpg,jpeg,png,gif,webp')),

        // Allowed MIME types, checked against the file's real content — not the
        // browser-supplied type. Doubles as the picker's `accept` list.
        'mime_types' => $csv(env('ANSWER_IMAGE_MIME_TYPES', 'image/jpeg,image/png,image/gif,image/webp')),
    ],

];
