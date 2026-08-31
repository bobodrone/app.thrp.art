<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves an uploaded SVG, and makes it inert.
 *
 * Raster uploads live on the public disk and are served straight off the
 * public/storage symlink by the web server. SVG cannot be: fetched as a
 * top-level document rather than referenced from an `<img>` tag, an SVG is a
 * page on this app's own origin, with script and the session cookie. Anyone can
 * turn an upload into such a link simply by pasting its URL.
 *
 * So SVG is stored on a private disk and only ever leaves through here, where
 * the headers are set in PHP and deploy with the code. The alternative —
 * Apache Header directives — was rejected: deploy.sh excludes public/.htaccess
 * from the rsync, the directives no-op silently when mod_headers is off, and
 * nothing in the test suite can prove they are in force.
 *
 * Content-Disposition is what does the real work. It is honoured for
 * navigations but ignored for subresource loads, so pages keep rendering these
 * images normally while the bare URL only ever offers a download.
 */
class ServeUploadedSvg extends Controller
{
    public function __invoke(string $path): Response
    {
        // Every configured upload kind, since answers and avatars use different
        // directories and could in principle use different disks.
        foreach (config('uploads') as $config) {
            $absolute = $this->resolve($config, $path);

            if ($absolute !== null) {
                return $this->respond($absolute);
            }
        }

        abort(404);
    }

    /**
     * The absolute path of the file, or null when $path is not a real SVG inside
     * this upload kind's directory.
     */
    private function resolve(array $config, string $path): ?string
    {
        if (! str_ends_with(strtolower($path), '.svg')) {
            return null;
        }

        $disk = Storage::disk($config['svg']['disk']);
        $root = realpath($disk->path(''));

        if ($root === false) {
            return null;
        }

        // Containment is checked on the resolved path, so neither `..` segments
        // nor a symlink inside the directory can point outside it.
        $absolute = realpath($disk->path($path));

        if ($absolute === false || ! str_starts_with($absolute, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }

        $directory = trim($config['directory'], '/');

        return str_starts_with($absolute, $root.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR)
            ? $absolute
            : null;
    }

    private function respond(string $absolute): BinaryFileResponse
    {
        return response()->file($absolute, [
            // Always declared here, never sniffed from the file and never taken
            // from whatever the uploader claimed.
            'Content-Type'            => 'image/svg+xml',
            'Content-Disposition'     => 'attachment',
            'X-Content-Type-Options'  => 'nosniff',
            // Belt and braces: even if the file were somehow opened as a
            // document, it may load nothing and run nothing.
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            // Stored filenames are random, so a given URL's bytes never change.
            'Cache-Control'           => 'public, max-age=31536000, immutable',
        ]);
    }
}
