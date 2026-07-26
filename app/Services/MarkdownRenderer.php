<?php

namespace App\Services;

use App\Services\Markdown\HeadingAsParagraphRenderer;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use League\CommonMark\MarkdownConverter;
use Purifier;

class MarkdownRenderer
{
    protected ?GithubFlavoredMarkdownConverter $converter = null;

    protected ?MarkdownConverter $bioConverter = null;

    public function render(string $markdown): string
    {
        $html = $this->converter()->convert($markdown)->getContent();

        return $this->sanitize($html);
    }

    /**
     * Short profile copy — bios. Parsed as full Markdown so the things people
     * actually type (lists, emphasis, links) come out right, then sanitised
     * against the narrower 'bio' profile, which drops headings, images and
     * other blocks that would break the card they sit in.
     */
    public function renderBio(string $markdown): string
    {
        $html = $this->bioConverter()->convert($markdown)->getContent();

        return Purifier::clean($html, 'bio');
    }

    /**
     * A one-line, plain-text preview of Markdown — for table cells and other
     * places where rendered HTML cannot be truncated safely.
     */
    public function excerpt(string $markdown, int $limit = 64): string
    {
        $text = html_entity_decode(
            strip_tags($this->renderBio($markdown)),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        return Str::limit(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), $limit);
    }

    protected function converter(): GithubFlavoredMarkdownConverter
    {
        if ($this->converter instanceof GithubFlavoredMarkdownConverter) {
            return $this->converter;
        }

        return $this->converter = new GithubFlavoredMarkdownConverter($this->config());
    }

    protected function bioConverter(): MarkdownConverter
    {
        if ($this->bioConverter instanceof MarkdownConverter) {
            return $this->bioConverter;
        }

        // Core CommonMark only: no tables or task lists, which have no place in
        // a bio and survive sanitising as stray text rather than markup.
        $environment = new Environment($this->config() + [
            'external_link' => [
                'internal_hosts'     => parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost',
                'open_in_new_window' => true,
                // Creator-supplied links: don't pass on referrers or ranking.
                'nofollow'   => 'external',
                'noopener'   => 'all',
                'noreferrer' => 'all',
            ],
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new ExternalLinkExtension);

        // Priority above the core heading renderer, which it replaces.
        $environment->addRenderer(Heading::class, new HeadingAsParagraphRenderer, 10);

        return $this->bioConverter = new MarkdownConverter($environment);
    }

    /**
     * Shared by both converters. The 'external_link' block that used to live
     * here was never applied — GithubFlavoredMarkdownConverter does not load
     * ExternalLinkExtension — and its keys did not match the extension's
     * schema, so it is set per-converter now, where the extension is loaded.
     */
    protected function config(): array
    {
        return [
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
        ];
    }

    protected function sanitize(string $html): string
    {
        return Purifier::clean($html, 'markdown');
    }
}
