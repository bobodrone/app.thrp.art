<?php

namespace Tests\Feature;

use App\Livewire\CreatorProfile;
use App\Livewire\CreatorQuestionDetail;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SVG is accepted, but never on the terms a raster image gets: it is validated
 * by App\Rules\SafeSvg, stripped by the sanitiser, and kept off the public disk
 * so it can only be served by App\Http\Controllers\ServeUploadedSvg.
 */
class SvgUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    private function svgDisk()
    {
        return Storage::disk(config('uploads.answer_image.svg.disk'));
    }

    /** An upload carrying the exact bytes of a fixture, under a chosen name. */
    private function fixture(string $name, ?string $as = null): UploadedFile
    {
        $path = base_path('tests/Fixtures/svg/'.$name);

        return UploadedFile::fake()->createWithContent($as ?? $name, (string) file_get_contents($path));
    }

    private function claimedQuestion(User $creator): Question
    {
        return Question::factory()->claimedBy($creator)->create([
            'asked_by' => User::factory()->create()->id,
        ]);
    }

    // ---------------------------------------------------------------- accepted

    public function test_a_clean_svg_is_accepted_and_stored_on_the_private_disk(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = $this->claimedQuestion($creator);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answerImage', $this->fixture('clean.svg'))
            ->set('answer', 'Here is a diagram that explains it.')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        $path = $q->refresh()->primaryAnswer->image_path;

        $this->assertStringEndsWith('.svg', $path);
        $this->svgDisk()->assertExists($path);

        // The public disk is what the web server hands out unmediated; an SVG
        // must never end up there.
        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_stored_svg_is_reachable_and_renders_on_the_answer_page(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = $this->claimedQuestion($creator);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answerImage', $this->fixture('clean.svg'))
            ->set('answer', 'Here is a diagram that explains it.')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        $url = $q->refresh()->primaryAnswer->imageUrl();

        $this->assertStringContainsString('/media/', $url);
        $this->get($url)->assertOk();
    }

    public function test_an_svg_avatar_is_accepted_and_served_through_the_media_route(): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', $this->fixture('clean.svg'))
            ->call('save')
            ->assertHasNoErrors();

        $creator->refresh();

        $this->assertStringEndsWith('.svg', $creator->avatar_path);
        $this->assertStringContainsString('/media/', $creator->avatarUrl());
        $this->get($creator->avatarUrl())->assertOk();
    }

    public function test_a_same_document_fragment_reference_is_allowed(): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', $this->fixture('internal-fragment.svg'))
            ->assertHasNoErrors('avatar');
    }

    public function test_the_stored_filename_is_random_rather_than_the_uploaded_one(): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', $this->fixture('clean.svg', 'my-logo.svg'))
            ->call('save')
            ->assertHasNoErrors();

        $this->assertStringNotContainsString('my-logo', $creator->refresh()->avatar_path);
    }

    public function test_raster_uploads_are_untouched_by_any_of_this(): void
    {
        // The media route exists for SVG alone. Raster images must keep being
        // served straight off the public disk by the web server, with no
        // Content-Disposition and no PHP in the request path.
        $creator = User::factory()->creator()->create();
        $q       = $this->claimedQuestion($creator);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answerImage', UploadedFile::fake()->image('garden.jpg', 800, 600))
            ->set('answer', 'An answer with a photo attached.')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        $answer = $q->refresh()->primaryAnswer;

        Storage::disk('public')->assertExists($answer->image_path);
        $this->svgDisk()->assertMissing($answer->image_path);
        $this->assertStringNotContainsString('/media/', $answer->imageUrl());
        $this->assertStringContainsString('/storage/', $answer->imageUrl());
    }

    // --------------------------------------------------------------- sanitised

    public function test_dangerous_content_never_reaches_the_stored_file(): void
    {
        // Files that pass validation but still carry something the sanitiser is
        // responsible for removing.
        $creator = User::factory()->creator()->create();
        $q       = $this->claimedQuestion($creator);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answerImage', $this->fixture('script-element.svg'))
            ->set('answer', 'An answer with an image attached.')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        $stored = $this->svgDisk()->get($q->refresh()->primaryAnswer->image_path);

        $this->assertStringNotContainsStringIgnoringCase('<script', $stored);
        $this->assertStringNotContainsStringIgnoringCase('document.cookie', $stored);
        $this->assertStringNotContainsStringIgnoringCase('<!DOCTYPE', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onload=', $stored);
    }

    public function test_event_handler_attributes_are_stripped_from_the_stored_file(): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', $this->fixture('onload-handler.svg'))
            ->call('save')
            ->assertHasNoErrors();

        $stored = $this->svgDisk()->get($creator->refresh()->avatar_path);

        $this->assertStringNotContainsStringIgnoringCase('onload', $stored);
        $this->assertStringNotContainsStringIgnoringCase('onmouseover', $stored);
        $this->assertStringNotContainsStringIgnoringCase('alert(', $stored);
    }

    // ---------------------------------------------------------------- rejected

    public static function rejectedFixtures(): array
    {
        return [
            'javascript: href'      => ['javascript-href.svg'],
            'DOCTYPE entity bomb'   => ['doctype-entity-bomb.svg'],
            'DOCTYPE XXE'           => ['doctype-xxe.svg'],
            'external <image>'      => ['external-image.svg'],
            'remote <use>'          => ['remote-use.svg'],
            'remote @font-face'     => ['remote-font-face.svg'],
            'malformed XML'         => ['not-xml.svg'],
            'root is not <svg>'     => ['not-svg-root.svg'],
        ];
    }

    #[DataProvider('rejectedFixtures')]
    public function test_hostile_or_malformed_svg_is_rejected_as_soon_as_it_is_picked(string $fixture): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', $this->fixture($fixture))
            ->assertHasErrors('avatar');

        $this->assertNull($creator->refresh()->avatar_path);
    }

    public function test_an_svg_over_the_svg_cap_is_rejected_even_though_it_is_under_the_raster_cap(): void
    {
        // 300 KB: comfortably under answer_image.max_kb (2 MB), well over the
        // 128 KB SVG cap. This is the whole point of the separate limit.
        config()->set('uploads.avatar.svg.max_kb', 128);

        $padding = str_repeat('<!-- padding -->', 20000);
        $big     = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'.$padding.'</svg>';

        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', UploadedFile::fake()->createWithContent('big.svg', $big))
            ->assertHasErrors('avatar');
    }

    public function test_svg_can_be_turned_off_by_configuration(): void
    {
        config()->set('uploads.avatar.svg.enabled', false);

        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', $this->fixture('clean.svg'))
            ->assertHasErrors('avatar');
    }

    public function test_a_raster_image_renamed_to_svg_is_rejected(): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', UploadedFile::fake()->createWithContent('actually.svg', 'not xml at all'))
            ->assertHasErrors('avatar');
    }
}
