<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $casts = [
        'status'     => QuestionStatus::class,
        'claimed_at' => 'datetime',
        'hidden_at'  => 'datetime',
    ];

    protected $fillable = [
        'content', 'status', 'asked_by', 'claimed_by', 'primary_answer_id', 'claimed_at',
    ];

    /**
     * Whether this question has a main answer that has not been soft-deleted.
     * A hidden answer resolves the relation to null, so the question reads as
     * unanswered without losing the pointer needed to restore it.
     */
    public function hasVisibleAnswer(): bool
    {
        return $this->primaryAnswer !== null;
    }

    /**
     * The main answer exists but has been hidden by an admin — the question
     * reads as unanswered while the row stays recoverable.
     */
    public function hasHiddenAnswer(): bool
    {
        return $this->primary_answer_id !== null && $this->primaryAnswer === null;
    }

    /**
     * Taken out of public view by an admin. Distinct from a soft delete: the
     * question is still live, the asker still sees it, and `status` still says
     * where in the lifecycle it stopped — so unhiding puts it back untouched.
     */
    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /**
     * Pull this question out of public view. The reason is optional and is
     * shown to the asker, not just logged — hiding without a word is allowed,
     * hiding with one that only admins can read is not the point.
     */
    public function hide(User $admin, ?string $reason = null): void
    {
        $reason = trim((string) $reason);

        $this->forceFill([
            'hidden_at'     => now(),
            'hidden_by'     => $admin->id,
            'hidden_reason' => $reason === '' ? null : $reason,
        ])->save();
    }

    /**
     * Put it back. The reason goes with it — it described a state that no
     * longer holds, and leaving it behind would show the asker a stale notice
     * if the question were ever hidden again without one.
     */
    public function unhide(): void
    {
        $this->forceFill([
            'hidden_at'     => null,
            'hidden_by'     => null,
            'hidden_reason' => null,
        ])->save();
    }

    /**
     * Whether $user may hide and unhide this question. Moderation, so admins
     * only — the same bar as taking an answer down.
     */
    public function isHideableBy(?User $user): bool
    {
        return $user?->role === UserRole::Admin;
    }

    /**
     * Whether $user may see this question at all. Hiding only closes the door
     * to the public: the asker keeps sight of their own question (that is where
     * the reason is read), and admins see everything.
     */
    public function isViewableBy(?User $user): bool
    {
        return ! $this->isHidden()
            || ($user !== null && ($user->id === $this->asked_by || $user->role === UserRole::Admin));
    }

    /**
     * Take an open question. The status check lives in the WHERE clause so two
     * creators clicking at the same moment cannot both win — the loser gets
     * false. Hidden questions are excluded there too, so a page left open from
     * before the hide cannot claim one.
     */
    public function claimBy(User $user): bool
    {
        return static::whereKey($this->getKey())
            ->where('status', QuestionStatus::Asked)
            ->whereNull('hidden_at')
            ->update([
                'status'     => QuestionStatus::Claimed,
                'claimed_by' => $user->id,
                'claimed_at' => now(),
            ]) === 1;
    }

    /**
     * Creators and admins may claim, but only while nobody else has. Claiming
     * only ever races for the main answer — alternatives need no claim.
     */
    public function isClaimableBy(?User $user): bool
    {
        return $user !== null
            && $user->role->isAtLeast(UserRole::Creator)
            && $this->status === QuestionStatus::Asked
            && ! $this->isHidden();
    }

    /**
     * Claimed by this user and still waiting on their answer — nobody else
     * gets a way in.
     */
    public function isAwaitingAnswerFrom(?User $user): bool
    {
        return $user !== null
            && $this->status === QuestionStatus::Claimed
            && $this->claimed_by === $user->id;
    }

    /**
     * Whether $user may add an alternative answer. No claim is needed — each
     * creator writes their own row — but the main answer has to be up first,
     * and one answer per creator is the limit.
     */
    public function isAnswerableBy(?User $user): bool
    {
        return $user !== null
            && $user->role->isAtLeast(UserRole::Creator)
            && $this->hasVisibleAnswer()
            && ! $this->isHidden()
            && ! $this->hasAnswerFrom($user);
    }

    /**
     * Whether $user already holds a visible answer here. A hidden one does not
     * count: re-answering revives that row rather than adding a second.
     */
    public function hasAnswerFrom(User $user): bool
    {
        if ($this->relationLoaded('answers')) {
            return $this->answers->contains('created_by', $user->id);
        }

        return $this->answers()->writtenBy($user->id)->exists();
    }

    /**
     * Publish the claimer's answer into the main slot, or null when the claim
     * no longer holds. The claim is re-checked in the WHERE clause and before
     * anything is written, so a creator who lost the question while writing —
     * or who was still writing when an admin hid it — lands nothing at all.
     */
    public function publishPrimaryAnswerFrom(User $author, string $body, ?string $imagePath = null): ?Answer
    {
        return DB::transaction(function () use ($author, $body, $imagePath) {
            $claimed = static::whereKey($this->getKey())
                ->where('status', QuestionStatus::Claimed)
                ->where('claimed_by', $author->id)
                ->whereNull('hidden_at')
                ->update(['status' => QuestionStatus::Answered]);

            if ($claimed !== 1) {
                return null;
            }

            $answer = $this->upsertAnswerFrom($author, $body, $imagePath);

            static::whereKey($this->getKey())->update(['primary_answer_id' => $answer->id]);

            $this->forceFill([
                'status'            => QuestionStatus::Answered,
                'primary_answer_id' => $answer->id,
            ])->syncOriginal();

            $this->setRelation('primaryAnswer', $answer);

            return $answer;
        });
    }

    /**
     * Add this creator's alternative take alongside the main answer, or null
     * when they are not allowed one.
     */
    public function addAlternativeAnswerFrom(User $author, string $body, ?string $imagePath = null): ?Answer
    {
        if (! $this->isAnswerableBy($author)) {
            return null;
        }

        return $this->upsertAnswerFrom($author, $body, $imagePath);
    }

    /**
     * Hide an answer, keeping it recoverable. Losing the main answer reopens
     * the question so it can be claimed and answered again; alternatives just
     * disappear from the list.
     */
    public function removeAnswer(Answer $answer): void
    {
        DB::transaction(function () use ($answer) {
            $answer->delete();

            if ($this->primary_answer_id === $answer->id) {
                $this->forceFill([
                    'status'     => QuestionStatus::Asked,
                    'claimed_by' => null,
                    'claimed_at' => null,
                ])->save();
            }
        });
    }

    /**
     * Unhide an answer. If the main slot was refilled while this one was
     * hidden, it comes back as an alternative instead.
     */
    public function restoreAnswer(Answer $answer): void
    {
        DB::transaction(function () use ($answer) {
            $answer->restore();

            if ($this->primary_answer_id === $answer->id) {
                $this->forceFill(['status' => QuestionStatus::Answered])->save();
            }
        });
    }

    /**
     * Whether $user may hide and restore answers here. Moderation, so admins
     * only — a creator can rewrite their own answer but never take one down.
     */
    public function isModeratableBy(?User $user): bool
    {
        return $user?->role === UserRole::Admin;
    }

    /**
     * Whether $user may move this answer into the main slot. Moderation, so
     * admins only — and never for the answer already sitting there.
     */
    public function isPromotableBy(?User $user, Answer $answer): bool
    {
        return $user?->role === UserRole::Admin
            && $answer->question_id === $this->id
            && $this->primary_answer_id !== $answer->id;
    }

    /**
     * Move an existing alternative into the main slot — how an admin replaces a
     * removed main answer without making the asker wait for a fresh claim.
     */
    public function promoteToPrimary(Answer $answer): void
    {
        $this->forceFill([
            'status'            => QuestionStatus::Answered,
            'primary_answer_id' => $answer->id,
            // The claim tracks whoever holds the main answer.
            'claimed_by'        => $answer->created_by,
            'claimed_at'        => $this->claimed_at ?? now(),
        ])->save();
    }

    /**
     * Create this creator's answer, or overwrite the one they already have.
     * A reopened question still carries the creator's hidden row — reusing it
     * keeps the one-answer-per-creator index happy.
     */
    protected function upsertAnswerFrom(User $author, string $body, ?string $imagePath): Answer
    {
        $answer = $this->answers()->withTrashed()->firstOrNew(['created_by' => $author->id]);

        $answer->forceFill([
            'body'         => $body,
            'image_path'   => $imagePath,
            // Snapshotted at publication, so a later change of preference cannot
            // unmask (or retroactively anonymise) an answer already published.
            // Re-publishing into a reused row is a fresh publication and does
            // take the current preference — the only way the flag ever moves.
            'anonymously'  => $author->posts_anonymously,
            'published_at' => now(),
            'deleted_at'   => null,
        ])->save();

        return $answer;
    }

    /**
     * How many answers are on show — the main one plus its alternatives.
     */
    public function visibleAnswerCount(): int
    {
        return $this->relationLoaded('answers')
            ? $this->answers->count()
            : $this->answers()->count();
    }

    /**
     * Alternative answers, oldest first. Reads the loaded `answers` relation,
     * so eager-load it when rendering a list.
     */
    public function otherAnswers(): Collection
    {
        return $this->answers
            ->reject(fn (Answer $answer) => $answer->id === $this->primary_answer_id)
            ->sortBy('published_at')
            ->values();
    }

    /**
     * Every visible answer, main one first and the alternatives behind it in
     * publication order — the reading order for crediting responders. Reads the
     * loaded `answers` relation, so eager-load it when rendering a list.
     *
     * A hidden main answer drops out with the rest: `answers` excludes
     * soft-deleted rows, so the question credits nobody it no longer shows.
     * An answer whose author has been deleted drops out too — there is no
     * longer anyone to name.
     */
    public function creditedAnswers(): Collection
    {
        $others  = $this->otherAnswers();
        $primary = $this->answers->firstWhere('id', $this->primary_answer_id);

        return ($primary === null ? $others : $others->prepend($primary))
            ->filter(fn (Answer $answer) => $answer->author !== null)
            ->values();
    }

    /**
     * How the main answer's creator should be credited to $viewer.
     */
    public function answererNameFor(?User $viewer): ?string
    {
        return $this->primaryAnswer?->authorNameFor($viewer);
    }

    /**
     * How the creator who claimed this question should be credited while they
     * write, or null when nobody holds it. There is no answer to snapshot yet,
     * so the preference is read live — naming them here would unmask an
     * anonymous creator for the whole window between claiming and publishing.
     *
     * Needs `posts_anonymously` on the eager load (`claimer:id,name,posts_anonymously`):
     * a partially loaded User reports null and reads as "not anonymous".
     */
    public function claimerNameFor(?User $viewer): ?string
    {
        if ($this->claimer === null) {
            return null;
        }

        if ($this->claimer->posts_anonymously && $viewer?->role !== UserRole::Admin) {
            return Answer::ANONYMOUS_AUTHOR;
        }

        return $this->claimer->name;
    }

    /**
     * Public URL of the image attached to the main answer, or null when there
     * is none — or when that answer is hidden.
     */
    public function answerImageUrl(): ?string
    {
        return $this->primaryAnswer?->imageUrl();
    }

    /**
     * Whether $user may edit the main answer.
     */
    public function isAnswerEditableBy(?User $user): bool
    {
        return $this->primaryAnswer?->isEditableBy($user) ?? false;
    }

    /**
     * Whether any visible answer here has an edit form $user can open — the
     * main one or an alternative. What decides between an edit link and a
     * plain view link in the answered list, where a responder's own answer is
     * as often an alternative as it is the main one.
     */
    public function hasEditableAnswerFor(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->relationLoaded('answers')) {
            return $this->answers->contains(fn (Answer $answer) => $answer->isEditFormOpenTo($user));
        }

        if (! $user->role->isAtLeast(UserRole::Creator)) {
            return false;
        }

        // An admin may edit anyone's, so any answer at all will do.
        return $user->role === UserRole::Admin
            ? $this->answers()->exists()
            : $this->answers()->writtenBy($user->id)->exists();
    }

    public function asker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asked_by');
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function hiddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function primaryAnswer(): BelongsTo
    {
        return $this->belongsTo(Answer::class, 'primary_answer_id');
    }

    /**
     * Everything the public is allowed to see. Every listing that is not the
     * admin table or the asker's own should go through this.
     */
    public function scopeVisible(Builder $q): Builder
    {
        return $q->whereNull('hidden_at');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', QuestionStatus::Asked);
    }

    public function scopeClaimedBy(Builder $q, int $userId): Builder
    {
        return $q->where('claimed_by', $userId)
            ->where('status', QuestionStatus::Claimed);
    }

    public function scopeAnswered(Builder $q): Builder
    {
        return $q->where('status', QuestionStatus::Answered);
    }

    /**
     * Questions this creator has answered — as the main answer or as an
     * alternative.
     */
    public function scopeAnsweredBy(Builder $q, int $userId): Builder
    {
        return $q->whereHas('answers', fn (Builder $a) => $a->writtenBy($userId));
    }
}
