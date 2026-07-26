<?php

namespace App\Services\Markdown;

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Renders Markdown headings as ordinary paragraphs.
 *
 * Bios sit in a card under the creator's name, where a real <h1> would be the
 * loudest thing on the page. Simply stripping the tag afterwards is not enough:
 * two consecutive headings would then run together into a single line, so the
 * block has to survive as a paragraph.
 */
final class HeadingAsParagraphRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        Heading::assertInstanceOf($node);

        return new HtmlElement('p', [], $childRenderer->renderNodes($node->children()));
    }
}
