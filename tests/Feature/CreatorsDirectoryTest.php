<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Livewire\CreatorsIndex;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreatorsDirectoryTest extends TestCase
{
    use RefreshDatabase;

    /** An answered question credited to $creator, anonymous or not. */
    private function answeredBy(User $creator, bool $anonymously = false, bool $removed = false): Question
    {
        $question = Question::factory()
            ->answeredBy($creator, 'Keep the soil damp but never soggy.', ['anonymously' => $anonymously])
            ->create(['asked_by' => User::factory()->create()->id]);

        if ($removed) {
            $question->removeAnswer($question->primaryAnswer);
        }

        return $question;
    }

    public function test_guest_can_browse_the_creator_list(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Ada Gardener']);
        $member  = User::factory()->create(['name' => 'Ordinary Member']);

        $this->get('/responders')
            ->assertOk()
            ->assertSee('Ada Gardener')
            ->assertDontSee('Ordinary Member')
            ->assertSee(route('creators.show', $creator));
    }

    public function test_admins_are_listed_alongside_creators(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Staff Person']);

        $this->get('/responders')->assertOk()->assertSee($admin->name);
    }

    public function test_default_order_is_nickname_ascending(): void
    {
        User::factory()->creator()->create(['name' => 'Zoe']);
        User::factory()->creator()->create(['name' => 'Adam']);
        User::factory()->creator()->create(['name' => 'Mia']);

        Livewire::test(CreatorsIndex::class)
            ->assertSet('sort', 'name')
            ->assertSet('direction', 'asc')
            ->assertSeeInOrder(['Adam', 'Mia', 'Zoe']);
    }

    public function test_sorting_toggles_and_can_order_by_answer_count(): void
    {
        $busy  = User::factory()->creator()->create(['name' => 'Zoe']);
        $quiet = User::factory()->creator()->create(['name' => 'Adam']);
        $this->answeredBy($busy);
        $this->answeredBy($busy);

        Livewire::test(CreatorsIndex::class)
            ->call('sortBy', 'answers')
            ->assertSet('direction', 'desc')
            ->assertSeeInOrder(['Zoe', 'Adam'])
            // Clicking the same column again flips the direction.
            ->call('sortBy', 'answers')
            ->assertSet('direction', 'asc')
            ->assertSeeInOrder(['Adam', 'Zoe']);
    }

    public function test_an_unknown_sort_column_is_ignored(): void
    {
        User::factory()->creator()->create();

        Livewire::test(CreatorsIndex::class)
            ->call('sortBy', 'email')
            ->assertSet('sort', 'name')
            ->assertOk();
    }

    public function test_search_narrows_the_list(): void
    {
        User::factory()->creator()->create(['name' => 'Bertil Blomma']);
        User::factory()->creator()->create(['name' => 'Cecilia Cactus']);

        Livewire::test(CreatorsIndex::class)
            ->set('search', 'cact')
            ->assertSee('Cecilia Cactus')
            ->assertDontSee('Bertil Blomma')
            ->set('search', 'nobody here')
            ->assertSee('No responder matches');
    }

    public function test_answer_count_only_includes_publicly_credited_answers(): void
    {
        $creator = User::factory()->creator()->create();
        $this->answeredBy($creator);
        $this->answeredBy($creator, anonymously: true);
        $this->answeredBy($creator, removed: true);

        $this->get(route('creators.show', $creator))
            ->assertOk()
            ->assertSee('1 answer published');
    }

    public function test_profile_page_shows_bio_and_links(): void
    {
        $creator = User::factory()->creator()->create([
            'name'         => 'Ada Gardener',
            'bio'          => 'I grow tomatoes on a balcony.',
            'social_links' => [['label' => 'Instagram', 'url' => 'https://instagram.com/balcony']],
        ]);

        $this->get(route('creators.show', $creator))
            ->assertOk()
            ->assertSee('Ada Gardener')
            ->assertSee('I grow tomatoes on a balcony.')
            ->assertSee('Instagram')
            ->assertSee('https://instagram.com/balcony');
    }

    public function test_profile_page_falls_back_to_initials_without_an_avatar(): void
    {
        $creator = User::factory()->creator()->create(['name' => 'Ada Gardener']);

        $this->assertSame('AG', $creator->initials());
        $this->get(route('creators.show', $creator))->assertOk()->assertSee('AG');
    }

    public function test_non_http_links_are_not_rendered(): void
    {
        $creator = User::factory()->creator()->create([
            'social_links' => [
                ['label' => 'Bad', 'url' => 'javascript:alert(1)'],
                ['label' => 'Good', 'url' => 'https://example.com'],
            ],
        ]);

        $this->assertSame(
            [['label' => 'Good', 'url' => 'https://example.com']],
            $creator->publicSocialLinks(),
        );

        $this->get(route('creators.show', $creator))
            ->assertOk()
            ->assertSee('Good')
            ->assertDontSee('javascript:alert(1)');
    }

    public function test_members_have_no_public_profile(): void
    {
        $this->get(route('creators.show', User::factory()->create()))->assertNotFound();
    }

    public function test_creator_sees_a_link_back_to_the_editor_on_their_own_profile(): void
    {
        $creator = User::factory()->creator()->create();

        // The nav carries the editor URL for every signed-in creator, so the
        // ownership block is identified by its own copy, not by the link.
        $this->actingAs($creator)
            ->get(route('creators.show', $creator))
            ->assertOk()
            ->assertSee('This is your public profile');
    }

    // Separate test: actingAs() sticks for the rest of a test, so the guest
    // case cannot share one with the authenticated case above.
    public function test_visitors_do_not_see_the_editor_link(): void
    {
        $creator = User::factory()->creator()->create();

        $this->get(route('creators.show', $creator))
            ->assertOk()
            ->assertDontSee('This is your public profile');

        $this->actingAs(User::factory()->creator()->create())
            ->get(route('creators.show', $creator))
            ->assertOk()
            ->assertDontSee('This is your public profile');
    }
}
