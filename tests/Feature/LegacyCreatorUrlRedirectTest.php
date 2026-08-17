<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public URLs moved from /creator(s) to /responder(s) when the role was
 * renamed in the UI. The old paths stay alive so bookmarks and inbound links
 * keep working.
 */
class LegacyCreatorUrlRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_directory_index_redirects(): void
    {
        $this->get('/creators')->assertRedirect('/responders');
    }

    public function test_a_public_profile_redirects_and_still_resolves(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Petra Pothos']);

        $this->get("/creators/{$creator->id}")
            ->assertRedirect("/responders/{$creator->id}");

        $this->get("/responders/{$creator->id}")
            ->assertOk()
            ->assertSee('Petra Pothos');
    }

    public function test_the_answerer_area_redirects_including_nested_paths(): void
    {
        $this->get('/creator')->assertRedirect('/responder');
        $this->get('/creator/profile')->assertRedirect('/responder/profile');
        $this->get('/creator/questions/7')->assertRedirect('/responder/questions/7');
    }

    public function test_the_admin_listing_redirects(): void
    {
        $this->get('/admin/creators')->assertRedirect('/admin/responders');
    }

    public function test_redirects_are_permanent(): void
    {
        $this->get('/creators')->assertStatus(301);
        $this->get('/creator/profile')->assertStatus(301);
    }

    /**
     * A page that was already open when the rename shipped still posts to the
     * old path. 308 keeps it a POST, so the body survives the hop.
     */
    public function test_a_legacy_post_keeps_its_method(): void
    {
        $creator = User::factory()->creator()->create();
        $question = Question::factory()->create(['asked_by' => User::factory()->create()->id]);

        $this->actingAs($creator)
            ->post("/creator/questions/{$question->id}/claim")
            ->assertStatus(308)
            ->assertRedirect("/responder/questions/{$question->id}/claim");
    }

    public function test_following_a_legacy_post_actually_claims_the_question(): void
    {
        $creator = User::factory()->creator()->create();
        $question = Question::factory()->create(['asked_by' => User::factory()->create()->id]);

        $this->actingAs($creator)
            ->post("/creator/questions/{$question->id}/claim")
            ->assertStatus(308);

        // Re-send exactly as a client honouring 308 would.
        $this->actingAs($creator)
            ->post("/responder/questions/{$question->id}/claim")
            ->assertRedirect(route('creator.questions.show', $question));

        $this->assertSame($creator->id, $question->fresh()->claimed_by);
    }
}
