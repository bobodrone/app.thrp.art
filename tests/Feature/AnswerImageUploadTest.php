<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\CreatorQuestionDetail;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AnswerImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function disk()
    {
        return Storage::disk(config('uploads.answer_image.disk'));
    }

    public function test_creator_can_attach_an_image_to_a_new_answer(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->claimedBy($creator)->create([
            'asked_by' => User::factory()->create()->id,
        ]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answerImage', UploadedFile::fake()->image('garden.jpg', 800, 600))
            ->set('answer', 'Here is what the seedling should look like.')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        $q->refresh();
        $this->assertNotNull($q->primaryAnswer->image_path);
        $this->assertStringStartsWith('answers/', $q->primaryAnswer->image_path);
        $this->disk()->assertExists($q->primaryAnswer->image_path);
    }

    public function test_answer_without_an_image_still_works(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->claimedBy($creator)->create([
            'asked_by' => User::factory()->create()->id,
        ]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', 'No picture needed for this one, it is all words.')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        $this->assertNull($q->refresh()->primaryAnswer?->image_path);
    }

    public function test_image_over_the_configured_limit_is_rejected(): void
    {
        config()->set('uploads.answer_image.max_kb', 100);

        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->claimedBy($creator)->create([
            'asked_by' => User::factory()->create()->id,
        ]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answerImage', UploadedFile::fake()->image('huge.jpg')->size(500))
            ->assertHasErrors(['answerImage' => 'max']);

        $this->assertNull($q->refresh()->primaryAnswer?->image_path);
    }

    public function test_non_image_file_is_rejected(): void
    {
        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->claimedBy($creator)->create([
            'asked_by' => User::factory()->create()->id,
        ]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answerImage', UploadedFile::fake()->create('notes.pdf', 20, 'application/pdf'))
            ->assertHasErrors('answerImage');

        $this->assertNull($q->refresh()->primaryAnswer?->image_path);
    }

    public function test_extension_outside_the_configured_list_is_rejected(): void
    {
        config()->set('uploads.answer_image.extensions', ['png']);
        config()->set('uploads.answer_image.mime_types', ['image/png']);

        $creator = User::factory()->creator()->create();
        $q       = Question::factory()->claimedBy($creator)->create([
            'asked_by' => User::factory()->create()->id,
        ]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answerImage', UploadedFile::fake()->image('photo.jpg'))
            ->assertHasErrors('answerImage');
    }

    public function test_editing_replaces_the_image_and_deletes_the_old_file(): void
    {
        $creator = User::factory()->creator()->create();
        $old     = UploadedFile::fake()->image('old.jpg')->store('answers', 'public');

        $q = Question::factory()
            ->answeredBy($creator, 'The original answer text.', ['image_path' => $old])
            ->create(['asked_by' => User::factory()->create()->id]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->set('answerImageDraft', UploadedFile::fake()->image('new.jpg'))
            ->set('answerDraft', 'The revised answer text.')
            ->call('updateAnswer')
            ->assertHasNoErrors();

        $q->refresh();
        $this->assertNotSame($old, $q->primaryAnswer->image_path);
        $this->disk()->assertMissing($old);
        $this->disk()->assertExists($q->primaryAnswer->image_path);
    }

    public function test_creator_can_remove_the_image_while_editing(): void
    {
        $creator = User::factory()->creator()->create();
        $old     = UploadedFile::fake()->image('old.jpg')->store('answers', 'public');

        $q = Question::factory()
            ->answeredBy($creator, 'The original answer text.', ['image_path' => $old])
            ->create(['asked_by' => User::factory()->create()->id]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->call('clearAnswerImageDraft')
            ->call('updateAnswer')
            ->assertHasNoErrors();

        $this->assertNull($q->refresh()->primaryAnswer?->image_path);
        $this->disk()->assertMissing($old);
    }

    public function test_picking_a_new_image_after_removing_one_previews_and_saves_it(): void
    {
        $creator = User::factory()->creator()->create();
        $old     = UploadedFile::fake()->image('old.jpg')->store('answers', 'public');

        $q = Question::factory()
            ->answeredBy($creator, 'The original answer text.', ['image_path' => $old])
            ->create(['asked_by' => User::factory()->create()->id]);

        $component = Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->call('startEditAnswer')
            ->call('clearAnswerImageDraft')
            ->assertSet('removeAnswerImage', true)
            ->set('answerImageDraft', UploadedFile::fake()->image('replacement.jpg'));

        // The re-pick must supersede the removal and be previewable in the form.
        $component->assertSet('removeAnswerImage', false)
            ->assertSee('/preview-file/', escape: false);

        $component->set('answerDraft', 'The revised answer text.')
            ->call('updateAnswer')
            ->assertHasNoErrors();

        $q->refresh();
        $this->assertNotNull($q->primaryAnswer->image_path);
        $this->assertNotSame($old, $q->primaryAnswer->image_path);
        $this->disk()->assertExists($q->primaryAnswer->image_path);
    }

    public function test_image_is_shown_on_the_public_question_page(): void
    {
        $path = UploadedFile::fake()->image('shown.jpg')->store('answers', 'public');

        $q = Question::factory()
            ->answeredBy(User::factory()->creator()->create(), 'An answer with a picture attached.', ['image_path' => $path])
            ->create(['asked_by' => User::factory()->create()->id]);

        $this->get("/questions/{$q->id}")
            ->assertStatus(200)
            ->assertSee($this->disk()->url($path), escape: false);
    }

    public function test_image_is_shown_in_the_expanded_card_on_the_home_feed(): void
    {
        $path = UploadedFile::fake()->image('feed.jpg')->store('answers', 'public');

        Question::factory()
            ->answeredBy(User::factory()->creator()->create(), 'An answer with a picture attached.', ['image_path' => $path])
            ->create(['asked_by' => User::factory()->create()->id]);

        // The feed narrows its select(), so primary_answer_id has to be listed
        // there or the relation never loads and no image renders.
        $this->get('/')
            ->assertStatus(200)
            ->assertSee($this->disk()->url($path), escape: false);
    }

    public function test_image_is_hidden_when_the_answer_is_soft_deleted(): void
    {
        $path = UploadedFile::fake()->image('hidden.jpg')->store('answers', 'public');

        $q = Question::factory()
            ->answeredBy(User::factory()->creator()->create(), 'An answer an admin removed.', ['image_path' => $path])
            ->create(['asked_by' => User::factory()->create()->id]);
        $q->removeAnswer($q->primaryAnswer);
        $q->refresh();

        $this->assertNull($q->answerImageUrl());
        $this->get("/questions/{$q->id}")->assertDontSee($this->disk()->url($path), escape: false);
    }

    public function test_members_cannot_reach_the_creator_upload_form(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);
        $q      = Question::factory()->create(['asked_by' => $member->id]);

        $this->actingAs($member)->get("/creator/questions/{$q->id}")->assertForbidden();
    }
}
