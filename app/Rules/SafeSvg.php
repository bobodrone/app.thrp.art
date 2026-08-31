<?php

namespace App\Rules;

use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Validates an uploaded SVG.
 *
 * SVG is the one image format that is also a document: fetched by URL rather
 * than referenced from an `<img>` tag, it renders as a page on this app's own
 * origin, with script, network access and the session cookie. Two independent
 * layers keep that from mattering — this rule and the sanitiser in
 * HandlesImageUploads reject or strip dangerous content, and ServeUploadedSvg
 * makes the served response inert even if both are bypassed.
 *
 * This rule is the strict half: rather than trying to clean a hostile file, it
 * refuses anything it does not fully understand, so the sanitiser only ever
 * sees plausible input. It never uses the `mimetypes:` rule — finfo reports SVG
 * as image/svg, text/plain or text/html depending on the file and the libmagic
 * build, so that check is unreliable in both directions.
 */
class SafeSvg implements ValidationRule
{
    /**
     * @param  int  $maxKb  Cap in kilobytes, from the upload kind's config. An
     *                      SVG is text; a large one is an attack, not a photo.
     */
    public function __construct(private readonly int $maxKb) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('That file is not a valid image.');

            return;
        }

        if ($value->getSize() > $this->maxKb * 1024) {
            $fail('SVG files must be smaller than '.$this->maxKb.' KB.');

            return;
        }

        $svg = (string) file_get_contents($value->getRealPath());

        // Checked on the raw bytes, before any parser touches them: a DOCTYPE is
        // how both XXE and entity-expansion bombs arrive, and there is no
        // legitimate reason for an uploaded icon to carry one.
        if (preg_match('/<!DOCTYPE/i', $svg) === 1) {
            $fail('SVG files must not contain a DOCTYPE declaration.');

            return;
        }

        $document = $this->parse($svg);

        if ($document === null) {
            $fail('That SVG could not be read — it is not well-formed XML.');

            return;
        }

        if (strtolower((string) $document->documentElement?->localName) !== 'svg') {
            $fail('That file is not an SVG image.');

            return;
        }

        if ($this->hasExternalReference($document)) {
            $fail('SVG files must not reference anything outside themselves — remove links to external images, fonts or stylesheets.');
        }
    }

    /**
     * Parse without resolving anything. LIBXML_NONET blocks network access, and
     * LIBXML_NOENT is deliberately *not* passed: it would substitute entities
     * rather than leave them alone, which is the expansion attack.
     */
    private function parse(string $svg): ?DOMDocument
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument;
            $loaded   = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

            return ($loaded && $document->documentElement !== null) ? $document : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Anything pointing outside the file itself. The sanitiser can strip these,
     * but a rejection is better than a silent edit: an SVG that only renders
     * correctly with its remote pieces would otherwise be stored broken, and
     * every such reference leaks the viewer's IP to whoever supplied it.
     */
    private function hasExternalReference(DOMDocument $document): bool
    {
        $xpath = new DOMXPath($document);

        foreach ($xpath->query('//@*') ?: [] as $attribute) {
            $name  = strtolower($attribute->localName);
            $value = trim($attribute->value);

            // Any attribute at all may carry a javascript: payload.
            if (preg_match('/^\s*javascript:/i', $value) === 1) {
                return true;
            }

            if (! in_array($name, ['href', 'src'], true)) {
                continue;
            }

            // Same-document fragments (`#gradient-1`) are the normal, safe case.
            if (str_starts_with($value, '#')) {
                continue;
            }

            // Inline data: payloads stay within the file. They are left to the
            // sanitiser, which restricts them to image media types.
            if (preg_match('/^\s*data:/i', $value) === 1) {
                continue;
            }

            if ($value !== '') {
                return true;
            }
        }

        // url(...) and @font-face src: inside a <style> block never reach the
        // attribute scan above.
        foreach ($xpath->query('//*[local-name()="style"]') ?: [] as $style) {
            if ($style instanceof DOMElement && preg_match('/url\s*\(\s*[\'"]?\s*(?!#|data:)/i', $style->textContent) === 1) {
                return true;
            }
        }

        return false;
    }
}
