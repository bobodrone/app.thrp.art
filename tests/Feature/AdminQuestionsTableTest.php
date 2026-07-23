<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminQuestionsTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_is_forbidden_from_admin_questions(): void
    {
        $member = User::factory()->create(['role' => UserRole::Member]);

        $this->actingAs($member)->get('/admin/questions')->assertForbidden();
    }

    public function test_creator_is_forbidden_from_admin_questions(): void
    {
        $creator = User::factory()->creator()->create();

        $this->actingAs($creator)->get('/admin/questions')->assertForbidden();
    }

    public function test_admin_sees_all_questions(): void
    {
        $admin  = User::factory()->admin()->create(['name' => 'Ada Admin']);
        $asker  = User::factory()->create(['name' => 'Audrey Asker']);
        $creator = User::factory()->creator()->create(['name' => 'Carl Creator']);
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Open question']);
        Question::factory()->claimedBy($creator)->create(['asked_by' => $asker->id, 'content' => 'Claimed question']);
        Question::factory()->answeredBy($creator)->create(['asked_by' => $asker->id, 'content' => 'Answered question']);

        $response = $this->actingAs($admin)->get('/admin/questions');

        $response->assertStatus(200);
        $response->assertSee('All Questions');
        $response->assertSee('Open question');
        $response->assertSee('Claimed question');
        $response->assertSee('Answered question');
        $response->assertSee('Audrey Asker');
        $response->assertSee('Carl Creator');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/questions')->assertRedirect('/login');
    }

    public function test_status_filter_only_shows_matching_questions(): void
    {
        $admin  = User::factory()->admin()->create();
        $asker  = User::factory()->create();
        $creator = User::factory()->creator()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'An asked one here']);
        Question::factory()->answeredBy($creator)->create(['asked_by' => $asker->id, 'content' => 'An answered one here']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('statusFilter', 'answered')
            ->assertSee('An answered one here')
            ->assertDontSee('An asked one here');
    }

    public function test_invalid_status_filter_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Visible question']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('statusFilter', 'bogus')
            ->assertSee('Visible question');
    }

    public function test_search_filters_content_case_insensitively(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'How to bake sourdough bread']);
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Best hiking trails in Japan']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('search', 'sourdough')
            ->assertSee('How to bake sourdough bread')
            ->assertDontSee('Best hiking trails in Japan');
    }

    public function test_reset_filters_clears_status_and_search(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        $creator = User::factory()->creator()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Open question']);
        Question::factory()->answeredBy($creator)->create(['asked_by' => $asker->id, 'content' => 'Answered question']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('statusFilter', 'answered')
            ->set('search', 'nonexistent')
            ->assertDontSee('Open question')
            ->call('resetFilters')
            ->assertSet('statusFilter', '')
            ->assertSet('search', '')
            ->assertSee('Open question')
            ->assertSee('Answered question');
    }

    public function test_url_sync_carries_filter_state(): void
    {
        $admin  = User::factory()->admin()->create();
        $asker  = User::factory()->create();
        $creator = User::factory()->creator()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Open question']);
        Question::factory()->answeredBy($creator)->create(['asked_by' => $asker->id, 'content' => 'Answered question']);

        $response = $this->actingAs($admin)->call('GET', '/admin/questions', ['status' => 'answered', 'q' => 'answered']);

        $response->assertStatus(200);
        $response->assertSee('Answered question');
        $response->assertDontSee('Open question');
    }

    public function test_pagination_preserves_filters(): void
    {
        $admin  = User::factory()->admin()->create();
        $asker  = User::factory()->create();
        // 3 answered questions → page=1 with 100/page only has 1 page
        Question::factory()->answeredBy(User::factory()->creator()->create())->create(['asked_by' => $asker->id, 'content' => 'Answered A']);
        Question::factory()->answeredBy(User::factory()->creator()->create())->create(['asked_by' => $asker->id, 'content' => 'Answered B']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('statusFilter', 'answered')
            ->assertSee('Answered A')
            ->assertSee('Answered B');
    }

    public function test_empty_state_shows_when_no_matches(): void
    {
        $admin = User::factory()->admin()->create();
        $asker = User::factory()->create();
        Question::factory()->create(['asked_by' => $asker->id, 'content' => 'Visible question']);

        $component = Livewire::actingAs($admin)
            ->test(\App\Livewire\AdminQuestionsTable::class)
            ->set('search', 'zzznomatchzzz')
            ->assertSee('No questions match your filters.');
    }

    public function test_table_shows_relative_users_and_formatted_dates(): void
    {
        $admin   = User::factory()->admin()->create();
        $asker   = User::factory()->create(['name' => 'Audrey']);
        $claimer = User::factory()->creator()->create(['name' => 'Carl']);
        $answerer = User::factory()->creator()->create(['name' => 'Clea']);
        $q = Question::factory()->create(['asked_by' => $asker->id, 'content' => 'A detailed q', 'created_at' => now()]);
        Question::where('id', $q->id)->update([
            'status' => QuestionStatus::Answered->value,
            'claimed_by' => $claimer->id,
            'answered_by' => $answerer->id,
        ]);

        $response = $this->actingAs($admin)->get('/admin/questions');

        $response->assertSee('Audrey');
        $response->assertSee('Carl');
        $response->assertSee('Clea');
        $response->assertSee(format_date($q->fresh()->created_at));
    }
}