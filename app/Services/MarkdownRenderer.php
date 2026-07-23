<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\InlinesOnly\InlinesOnlyExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Purifier;

class MarkdownRenderer
{
    protected ?GithubFlavoredMarkdownConverter $converter = null;

    public function render(string $markdown): string
    {
        $html = $this->converter()->convert($markdown)->getContent();

        return $this->sanitize($html);
    }

    protected function converter(): GithubFlavoredMarkdownConverter
    {
        if ($this->converter instanceof GithubFlavoredMarkdownConverter) {
            return $this->converter;
        }

        $config = [
            'html_input'         => 'strip',
            'allow_unsafe_links' => false,
            'external_link'      => [
                'internal_hosts'     => config('app.url_host'),
                'open_targets'        => '_blank',
                'rel'                 => ['noopener', 'noreferrer'],
                'remove_target_for_internal_hosts' => true,
            ],
        ];

        return $this->converter = new GithubFlavoredMarkdownConverter($config);
    }

    protected function sanitize(string $html): string
    {
        return Purifier::clean($html, 'markdown');
    }
}