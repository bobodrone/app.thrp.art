<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The second of the two layers protecting SVG uploads. The first (validation
 * plus the sanitiser) decides what may be stored; this one makes what is stored
 * harmless even if the first were bypassed.
 */
class ServeUploadedSvgTest extends TestCase
{
    use RefreshDatabase;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = config('uploads.answer_image.svg.disk');
        Storage::fake($this->disk);
    }

    private function store(string $path, string $contents = '<svg xmlns="http://www.w3.org/2000/svg"/>'): string
    {
        Storage::disk($this->disk)->put($path, $contents);

        return route('media.svg', ['path' => $path]);
    }

    public function test_a_stored_svg_is_served_with_headers_that_make_it_inert(): void
    {
        $response = $this->get($this->store('answers/example.svg'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');

        // The load-bearing one. Honoured for navigations but ignored for
        // subresource loads, so <img> keeps working while the bare URL can only
        // ever download — never execute as a page on this origin.
        $response->assertHeader('Content-Disposition', 'attachment');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('sandbox', $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
    }

    public function test_avatars_are_served_as_well_as_answer_images(): void
    {
        $this->get($this->store('avatars/example.svg'))->assertOk();
    }

    public function test_a_missing_file_is_a_404(): void
    {
        $this->get(route('media.svg', ['path' => 'answers/nothing-here.svg']))->assertNotFound();
    }

    public function test_a_non_svg_path_is_refused_even_when_the_file_exists(): void
    {
        Storage::disk($this->disk)->put('answers/secret.txt', 'not for you');

        $this->get(route('media.svg', ['path' => 'answers/secret.txt']))->assertNotFound();
    }

    public function test_a_file_outside_the_upload_directories_is_refused(): void
    {
        Storage::disk($this->disk)->put('elsewhere/private.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $this->get(route('media.svg', ['path' => 'elsewhere/private.svg']))->assertNotFound();
    }

    public function test_traversal_out_of_the_upload_directory_is_refused(): void
    {
        Storage::disk($this->disk)->put('elsewhere/private.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');

        $this->get('/media/answers/../elsewhere/private.svg')->assertNotFound();
        $this->get('/media/'.rawurlencode('../').'elsewhere/private.svg')->assertNotFound();
    }
}
