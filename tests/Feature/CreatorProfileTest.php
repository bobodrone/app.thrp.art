<?php

namespace Tests\Feature;

use App\Livewire\CreatorProfile;
use App\Livewire\CreatorQuestionDetail;
use App\Models\Answer;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class CreatorProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function disk()
    {
        return Storage::disk(config('uploads.avatar.disk'));
    }

    public function test_creator_can_open_the_profile_page(): void
    {
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)->get('/creator/profile')->assertOk();
    }

    public function test_member_cannot_open_the_profile_page(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get('/creator/profile')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/creator/profile')->assertRedirect('/login');
    }

    public function test_profile_fields_are_saved(): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('bio', '  I grow tomatoes on a balcony.  ')
            ->set('socialLinks', [
                ['label' => 'Instagram', 'url' => 'https://instagram.com/balcony'],
            ])
            ->set('postsAnonymously', true)
            ->call('save')
            ->assertHasNoErrors();

        $creator->refresh();
        $this->assertSame('I grow tomatoes on a balcony.', $creator->bio);
        $this->assertSame(
            [['label' => 'Instagram', 'url' => 'https://instagram.com/balcony']],
            $creator->social_links,
        );
        $this->assertTrue($creator->posts_anonymously);
    }

    public function test_existing_values_are_loaded_into_the_form(): void
    {
        $creator = User::factory()->creator()->create([
            'bio'               => 'Existing bio.',
            'social_links'      => [['label' => 'Site', 'url' => 'https://example.com']],
            'posts_anonymously' => true,
        ]);

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->assertSet('bio', 'Existing bio.')
            ->assertSet('postsAnonymously', true)
            ->assertSet('socialLinks', [['label' => 'Site', 'url' => 'https://example.com']]);
    }

    public function test_links_need_a_label_and_a_valid_url(): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('socialLinks', [['label' => '', 'url' => 'not-a-url']])
            ->call('save')
            ->assertHasErrors(['socialLinks.0.label', 'socialLinks.0.url']);

        $this->assertNull($creator->fresh()->social_links);
    }

    public function test_links_can_be_added_and_removed(): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->call('addLink')
            ->call('addLink')
            ->assertCount('socialLinks', 2)
            ->call('removeLink', 0)
            ->assertCount('socialLinks', 1);
    }

    public function test_link_count_is_capped(): void
    {
        $creator = User::factory()->creator()->create();
        $links   = array_fill(0, CreatorProfile::MAX_LINKS + 1, [
            'label' => 'Site', 'url' => 'https://example.com',
        ]);

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('socialLinks', $links)
            ->call('save')
            ->assertHasErrors('socialLinks');
    }

    public function test_avatar_can_be_uploaded_and_removed(): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', UploadedFile::fake()->image('me.jpg', 400, 400))
            ->call('save')
            ->assertHasNoErrors();

        $creator->refresh();
        $this->assertStringStartsWith('avatars/', $creator->avatar_path);
        $this->disk()->assertExists($creator->avatar_path);
        $this->assertNotNull($creator->avatarUrl());

        $storedPath = $creator->avatar_path;

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->call('clearAvatar')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($creator->fresh()->avatar_path);
        $this->disk()->assertMissing($storedPath);
    }

    public function test_replacing_the_avatar_deletes_the_old_file(): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', UploadedFile::fake()->image('first.jpg'))
            ->call('save');

        $firstPath = $creator->fresh()->avatar_path;

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', UploadedFile::fake()->image('second.jpg'))
            ->call('save');

        $secondPath = $creator->fresh()->avatar_path;

        $this->assertNotSame($firstPath, $secondPath);
        $this->disk()->assertMissing($firstPath);
        $this->disk()->assertExists($secondPath);
    }

    public function test_a_non_image_is_rejected(): void
    {
        $creator = User::factory()->creator()->create();

        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('avatar', UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'))
            ->call('save')
            ->assertHasErrors('avatar');

        $this->assertNull($creator->fresh()->avatar_path);
    }

    public function test_saving_the_profile_does_not_re_anonymise_older_answers(): void
    {
        $creator = User::factory()->creator()->create();
        $asker   = User::factory()->create();
        $q       = Question::factory()->claimedBy($creator)->create(['asked_by' => $asker->id]);

        // Answered while attributed…
        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', 'Water it twice a week and keep it out of the wind.')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        // …then the creator switches to anonymous.
        Livewire::actingAs($creator)
            ->test(CreatorProfile::class)
            ->set('postsAnonymously', true)
            ->call('save');

        $q->refresh();
        $this->assertFalse($q->primaryAnswer->anonymously);
        $this->assertSame($creator->name, $q->answererNameFor($asker));
    }

    public function test_answers_posted_while_anonymous_hide_the_creator_name(): void
    {
        $creator = User::factory()->creator()->create(['posts_anonymously' => true]);
        $asker   = User::factory()->create();
        $admin   = User::factory()->admin()->create();
        $q       = Question::factory()->claimedBy($creator)->create(['asked_by' => $asker->id]);

        Livewire::actingAs($creator)
            ->test(CreatorQuestionDetail::class, ['question' => $q])
            ->set('answer', 'Prune the lower leaves once it starts flowering.')
            ->call('submitAnswer')
            ->assertHasNoErrors();

        $q->refresh();
        $this->assertTrue($q->primaryAnswer->anonymously);
        $this->assertSame(Answer::ANONYMOUS_AUTHOR, $q->answererNameFor($asker));
        $this->assertSame(Answer::ANONYMOUS_AUTHOR, $q->answererNameFor(null));
        // The creator sees what the public sees; admins see through it.
        $this->assertSame(Answer::ANONYMOUS_AUTHOR, $q->answererNameFor($creator));
        $this->assertSame($creator->name, $q->answererNameFor($admin));

        $this->actingAs($asker)
            ->get(route('questions.show', $q))
            ->assertOk()
            ->assertSee(Answer::ANONYMOUS_AUTHOR)
            ->assertDontSee($creator->name);
    }

    public function test_settings_page_links_creators_to_their_profile(): void
    {
        $this->actingAs(User::factory()->creator()->create())
            ->get('/settings')
            ->assertOk()
            ->assertSee(route('creator.profile'));

        $this->actingAs(User::factory()->create())
            ->get('/settings')
            ->assertOk()
            ->assertDontSee(route('creator.profile'));
    }
}
