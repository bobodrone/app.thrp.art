<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
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
    /** @use HasFactory<\Database\Factories\QuestionFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'status'     => QuestionStatus::class,
        'claimed_at' => 'datetime',
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
     * Take an open question. The status check lives in the WHERE clause so two
     * creators clicking at the same moment cannot both win — the loser gets false.
     */
    public function claimBy(User $user): bool
    {
        return static::whereKey($this->getKey())
            ->where('status', QuestionStatus::Asked)
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
            && $this->status === QuestionStatus::Asked;
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
     * anything is written, so a creator who lost the question while writing
     * lands nothing at all.
     */
    public function publishPrimaryAnswerFrom(User $author, string $body, ?string $imagePath = null): ?Answer
    {
        return DB::transaction(function () use ($author, $body, $imagePath) {
            $claimed = static::whereKey($this->getKey())
                ->where('status', QuestionStatus::Claimed)
                ->where('claimed_by', $author->id)
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
            // Snapshotted, so a later change of preference cannot unmask (or
            // retroactively anonymise) an answer already published.
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
     * How the main answer's creator should be credited to $viewer.
     */
    public function answererNameFor(?User $viewer): ?string
    {
        return $this->primaryAnswer?->authorNameFor($viewer);
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

    public function asker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asked_by');
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function primaryAnswer(): BelongsTo
    {
        return $this->belongsTo(Answer::class, 'primary_answer_id');
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
