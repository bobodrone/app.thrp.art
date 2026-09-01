<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Livewire\AdminQuestionsTable;
use App\Livewire\CreatorQuestionDetail;
use App\Mail\AnswerNotification;
use App\Mail\ContactMessageReceived;
use App\Mail\NewQuestionNotification;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A responder's work on a question is a "response", never an "answer". The
 * models, columns and components keep the old name; rendered copy may not.
 * Companion to UiCopySaysResponderTest, and built the same way — by rendering
 * real pages, not by grepping source.
 */
class UiCopySaysResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_rendered_page_says_answer(): void
    {
        $admin    = User::factory()->create(['role' => UserRole::Admin]);
        $creator  = User::factory()->creator()->create();
        $other    = User::factory()->creator()->create();
        $member   = User::factory()->create(['role' => UserRole::Member]);

        // Fixture copy is written by hand: faker prose and the factory's default
        // body both contain "answer", which would fail this test on content the
        // product did not write.
        $question = Question::factory()
            ->answeredBy($creator, 'Water it less.')
            ->create(['asked_by' => $member->id, 'content' => 'Why is my fern unhappy?']);
        $question->addAlternativeAnswerFrom($other, 'Move it away from the radiator.');

        // A question still waiting, so the claim and empty states render too.
        Question::factory()->create([
            'asked_by' => $member->id,
            'content'  => 'How often should I repot?',
        ]);

        $pages = [
            [null,     '/'],
            [null,     '/responders'],
            [null,     "/responders/{$creator->id}"],
            [null,     '/apply'],
            [null,     '/contact'],
            [null,     "/questions/{$question->id}"],
            [$member,  '/settings'],
            [$member,  '/my-questions'],
            [$creator, '/responder'],
            [$creator, '/responder/profile'],
            [$creator, '/responder/answered'],
            [$creator, "/responder/questions/{$question->id}"],
            [$admin,   '/responder/answered'],
            [$admin,   "/responder/questions/{$question->id}"],
            [$admin,   '/admin/questions'],
            [$admin,   '/admin/users'],
        ];

        // NOT swept: /about. Its prose plays the two words against each other on
        // purpose — "The response may not be the answer you were looking for
        // (it may not be an answer at all)" — and rewording it would cost the
        // passage its point. Editorial copy, not interface chrome.

        foreach ($pages as [$as, $url]) {
            $r = $as ? $this->actingAs($as)->get($url) : $this->get($url);
            $r->assertOk();
            $this->assertStringNotContainsStringIgnoringCase(
                'answer',
                $this->visibleText($r->getContent()),
                "'answer' still rendered on {$url}"
            );
        }
    }

    /**
     * Human-readable copy only: the <title>, plus text nodes and the attributes
     * a screen reader reads out. Deliberately drops hrefs, wire:* attributes and
     * CSS classes, which still legitimately carry the old identifiers.
     */
    private function visibleText(string $html): string
    {
        preg_match('~<title>(.*?)</title>~s', $html, $title);

        preg_match_all('~\b(?:aria-label|alt|title|placeholder)="([^"]*)"~', $html, $attrs);

        $body = preg_replace('~<(script|style)\b.*?</\1>~s', ' ', $html);
        $body = preg_replace('~<!--.*?-->~s', ' ', $body);
        $body = strip_tags($body);

        return html_entity_decode(
            ($title[1] ?? '').' '.implode(' ', $attrs[1]).' '.$body
        );
    }

    public function test_no_email_says_answer(): void
    {
        $mails = [
            // Both wordings of the response notification: the copy that varies
            // lives inside {{ }}, so only rendering reaches it.
            new AnswerNotification('Ada', 'Why is my fern unhappy?', 'http://x/questions/1'),
            new AnswerNotification('Ada', 'Why is my fern unhappy?', 'http://x/questions/1', edited: true),
            new NewQuestionNotification('Ada', 'Why is my fern unhappy?', 'http://x/questions/1'),
            new ContactMessageReceived(
                senderName: 'Grace Hopper',
                senderEmail: 'grace@example.com',
                subjectLine: 'A question',
                body: 'How long do responses usually take?',
                inboxUrl: 'http://x/admin/messages',
            ),
        ];

        foreach ($mails as $mail) {
            $name = class_basename($mail);

            $this->assertStringNotContainsStringIgnoringCase(
                'answer', (string) $mail->envelope()->subject, $name.' subject'
            );

            $this->assertStringNotContainsStringIgnoringCase(
                'answer', strip_tags($mail->render()), $name.' body'
            );
        }
    }

    /**
     * The page sweep above only reaches what a GET renders. The edit forms and
     * the admin edit modal appear after an interaction, so their copy needs
     * driving through Livewire — the editor's default heading was "Edit answer"
     * and no rendered page ever showed it.
     */
    public function test_no_interactive_form_says_answer(): void
    {
        $admin    = User::factory()->create(['role' => UserRole::Admin]);
        $creator  = User::factory()->creator()->create();
        $other    = User::factory()->creator()->create();
        $member   = User::factory()->create(['role' => UserRole::Member]);

        $question = Question::factory()
            ->answeredBy($creator, 'Water it less.')
            ->create(['asked_by' => $member->id, 'content' => 'Why is my fern unhappy?']);
        $alternative = $question->addAlternativeAnswerFrom($other, 'Move it away from the radiator.');

        // The main response's edit form, opened by its author.
        $this->assertNoAnswerIn(
            Livewire::actingAs($creator)
                ->test(CreatorQuestionDetail::class, ['question' => $question])
                ->call('startEditAnswer')
                ->html(),
            'main response edit form',
        );

        // An alternative's edit form, which passes its own heading.
        $this->assertNoAnswerIn(
            Livewire::actingAs($other)
                ->test(CreatorQuestionDetail::class, ['question' => $question])
                ->call('startEditAnswer', $alternative->id)
                ->html(),
            'alternative edit form',
        );

        // The write-a-response form on an unanswered question.
        $fresh = Question::factory()->claimedBy($creator)
            ->create(['asked_by' => $member->id, 'content' => 'How often should I repot?']);

        $this->assertNoAnswerIn(
            Livewire::actingAs($creator)
                ->test(CreatorQuestionDetail::class, ['question' => $fresh])
                ->html(),
            'write-a-response form',
        );

        // The admin table's edit modal, which carries its own response field.
        $this->assertNoAnswerIn(
            Livewire::actingAs($admin)
                ->test(AdminQuestionsTable::class)
                ->call('edit', $question->id)
                ->html(),
            'admin edit modal',
        );
    }

    private function assertNoAnswerIn(string $html, string $what): void
    {
        $this->assertStringNotContainsStringIgnoringCase(
            'answer',
            $this->visibleText($html),
            "'answer' still rendered in the {$what}"
        );
    }

    /**
     * The status is stored as 'answered' and presented as "Responded". The badge
     * duplicates the mapping rather than calling label(), so both are checked —
     * they drifted apart once already.
     */
    public function test_the_answered_status_is_presented_as_responded(): void
    {
        $this->assertSame('answered', QuestionStatus::Answered->value);
        $this->assertSame('Responded', QuestionStatus::Answered->label());

        $this->assertStringContainsString(
            'Responded',
            (string) view('components.status-badge', ['status' => QuestionStatus::Answered])->render(),
        );
    }
}
