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

/*
 * SVG settings for one upload kind. SVG is not a raster format and is not
 * treated like one anywhere:
 *
 *  - It is validated by App\Rules\SafeSvg, not by the `mimetypes:` rule.
 *  - It gets its own, far smaller cap: an SVG is text, and a large one is an
 *    attack (entity expansion, deeply nested elements) rather than a big photo.
 *  - It is stored on a private disk and served by the media route, never off
 *    the public symlink. Fetched as a document, an SVG is a page on this app's
 *    own origin; only `<img>` renders it inertly. See ServeUploadedSvg.
 */
$svg = static fn (string $prefix): array => [
    'enabled' => filter_var(env($prefix.'_SVG_ENABLED', true), FILTER_VALIDATE_BOOL),
    'max_kb'  => (int) env($prefix.'_SVG_MAX_KB', 128),
    'disk'    => env($prefix.'_SVG_DISK', 'local'),
];

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
        // browser-supplied type.
        //
        // Raster formats only. SVG is never validated this way: finfo reports it
        // as image/svg, text/plain or text/html depending on the file's contents
        // and the libmagic build, so the check is unreliable in both directions.
        // See the 'svg' block below and App\Rules\SafeSvg.
        'mime_types' => $csv(env('ANSWER_IMAGE_MIME_TYPES', 'image/jpeg,image/png,image/gif,image/webp')),

        'svg' => $svg('ANSWER_IMAGE'),
    ],

    // Creator profile picture. Same shape as answer_image — smaller by default,
    // since it is only ever shown as a thumbnail.
    'avatar' => [
        'disk'       => env('AVATAR_DISK', 'public'),
        'directory'  => trim((string) env('AVATAR_DIRECTORY', 'avatars'), '/'),
        'max_kb'     => (int) env('AVATAR_MAX_KB', 1024),
        'extensions' => $csv(env('AVATAR_EXTENSIONS', 'jpg,jpeg,png,gif,webp')),
        'mime_types' => $csv(env('AVATAR_MIME_TYPES', 'image/jpeg,image/png,image/gif,image/webp')),
        'svg'        => $svg('AVATAR'),
    ],

];
