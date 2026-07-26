<?php

namespace Tests\Feature;

use App\Livewire\CreatorsIndex;
use App\Models\User;
use App\Services\MarkdownRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreatorBioMarkdownTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $bio): string
    {
        return app(MarkdownRenderer::class)->renderBio($bio);
    }

    public function test_emphasis_links_and_lists_are_rendered(): void
    {
        $html = $this->render("I grow **tomatoes** and *chillies*.\n\n* balcony\n* rooftop\n\n[My shop](https://example.com)");

        $this->assertStringContainsString('<strong>tomatoes</strong>', $html);
        $this->assertStringContainsString('<em>chillies</em>', $html);
        $this->assertStringContainsString('<li>balcony</li>', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
    }

    public function test_headings_degrade_to_plain_text(): void
    {
        $html = $this->render("# Drone is king!\n\n## tutiluren");

        $this->assertStringNotContainsString('<h1', $html);
        $this->assertStringNotContainsString('<h2', $html);
        // Each heading keeps its own block, so consecutive ones don't run together.
        $this->assertStringContainsString('<p>Drone is king!</p>', $html);
        $this->assertStringContainsString('<p>tutiluren</p>', $html);
    }

    public function test_layout_breaking_blocks_are_stripped(): void
    {
        $html = $this->render("![huge](https://example.com/x.png)\n\n> a quote\n\n---");

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('<blockquote', $html);
        $this->assertStringNotContainsString('<hr', $html);
        $this->assertStringContainsString('a quote', $html);
    }

    public function test_html_and_unsafe_links_cannot_get_through(): void
    {
        $html = $this->render('<script>alert(1)</script><img src=x onerror=alert(1)> [x](javascript:alert(1))');

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onerror', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_external_links_open_safely(): void
    {
        $html = $this->render('[shop](https://example.com)');

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('noopener', $html);
        $this->assertStringContainsString('noreferrer', $html);
    }

    public function test_profile_page_renders_the_bio_as_markdown(): void
    {
        $creator = User::factory()->creator()->create([
            'bio' => "# Drone is king!\n\nI grow **tomatoes**.",
        ]);

        $this->get(route('creators.show', $creator))
            ->assertOk()
            ->assertSee('<strong>tomatoes</strong>', escape: false)
            ->assertDontSee('# Drone is king!')
            ->assertSee('Drone is king!');
    }

    public function test_directory_preview_is_plain_single_line_text(): void
    {
        User::factory()->creator()->create([
            'name' => 'Ada Gardener',
            'bio'  => "# Heading\n\nI grow **tomatoes** and *chillies*.",
        ]);

        Livewire::test(CreatorsIndex::class)
            ->assertSee('Heading I grow tomatoes and chillies.')
            ->assertDontSee('<strong>', escape: false)
            ->assertDontSee('**tomatoes**');
    }

    public function test_excerpt_is_truncated(): void
    {
        $excerpt = app(MarkdownRenderer::class)->excerpt(str_repeat('a very long bio ', 20), 40);

        // Str::limit() counts the limit before appending its ellipsis.
        $this->assertLessThanOrEqual(43, mb_strlen($excerpt));
        $this->assertStringEndsWith('...', $excerpt);
    }
}
