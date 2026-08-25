<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Mail\ApplicationReceived;
use App\Mail\ApplicationRejected;
use App\Mail\UserRoleInvite;
use App\Models\CreatorApplication;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The role is stored as 'creator' but presented as "Responder" everywhere a
 * human can read it. These sweeps guard that boundary: identifiers may keep the
 * old name, rendered copy may not.
 */
class UiCopySaysResponderTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_rendered_page_says_creator(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $creator = User::factory()->creator()->create();
        $member = User::factory()->create(['role' => UserRole::Member]);
        $question = Question::factory()->answeredBy($creator, 'Water it less.')
            ->create(['asked_by' => $member->id]);
        CreatorApplication::create([
            'email' => 'ada@example.com',
            'name' => 'Ada Applicant',
            'message' => 'I would like to help.',
            'status' => ApplicationStatus::Pending,
            'applied_at' => now(),
        ]);

        $pages = [
            [null,     '/'],
            [null,     '/responders'],
            [null,     "/responders/{$creator->id}"],
            [null,     '/apply'],
            [null,     "/questions/{$question->id}"],
            [$member,  '/settings'],
            [$member,  '/my-questions'],
            [$creator, '/responder'],
            [$creator, '/responder/profile'],
            [$creator, '/responder/answered'],
            [$creator, "/responder/questions/{$question->id}"],
            [$admin,   '/admin/applications'],
            [$admin,   '/admin/questions'],
            [$admin,   '/admin/users'],
        ];

        foreach ($pages as [$as, $url]) {
            $r = $as ? $this->actingAs($as)->get($url) : $this->get($url);
            $r->assertOk();
            $this->assertStringNotContainsStringIgnoringCase(
                'creator',
                $this->visibleText($r->getContent()),
                "'creator' still rendered on {$url}"
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

        preg_match_all('~\\b(?:aria-label|alt|title|placeholder)="([^"]*)"~', $html, $attrs);

        $body = preg_replace('~<(script|style)\\b.*?</\\1>~s', ' ', $html);
        $body = preg_replace('~<!--.*?-->~s', ' ', $body);
        $body = strip_tags($body);

        return html_entity_decode(
            ($title[1] ?? '').' '.implode(' ', $attrs[1]).' '.$body
        );
    }

    public function test_no_email_says_creator(): void
    {
        $mails = [
            new UserRoleInvite(UserRole::Creator, 'http://x/reset'),
            new ApplicationReceived('Ada', 'ada@example.com', 'I would like to help.', 'http://x/review'),
            new ApplicationRejected('Ada'),
        ];

        foreach ($mails as $mail) {
            $name = class_basename($mail);
            $this->assertStringNotContainsStringIgnoringCase(
                'creator', (string) $mail->envelope()->subject, $name.' subject'
            );

            $this->assertStringNotContainsStringIgnoringCase('creator', $mail->render(), $name.' body');
        }

        // Literal copy in every email template, with {{ }} expressions stripped
        // so blade variable names (identifiers, deliberately unchanged) don't count.
        foreach (glob(resource_path('views/emails/*.blade.php')) as $view) {
            $copy = preg_replace('~\{\{.*?\}\}~s', '', file_get_contents($view));
            $this->assertStringNotContainsStringIgnoringCase('creator', $copy, basename($view));
        }
    }
}
