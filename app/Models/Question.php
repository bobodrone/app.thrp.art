<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Question extends Model
{
    /** @use HasFactory<\Database\Factories\QuestionFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'status'            => QuestionStatus::class,
        'claimed_at'        => 'datetime',
        'answered_at'       => 'datetime',
        'answer_deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'content', 'status', 'asked_by', 'claimed_by', 'answered_by',
        'answer', 'answer_image_path', 'claimed_at', 'answered_at', 'answer_deleted_at',
    ];

    /**
     * Whether this question has an answer that has not been soft-deleted.
     */
    public function hasVisibleAnswer(): bool
    {
        return $this->answer !== null && $this->answer_deleted_at === null;
    }

    /**
     * Only the creator who wrote the answer, or an admin, may edit it —
     * and only while there is a visible (non-deleted) answer.
     */
    public function isAnswerEditableBy(?User $user): bool
    {
        if (! $user || ! $this->hasVisibleAnswer()) {
            return false;
        }

        return $this->answered_by === $user->id
            || $user->role === UserRole::Admin;
    }

    /**
     * Public URL of the image attached to the answer, or null when there is
     * none — or when the answer itself is hidden.
     */
    public function answerImageUrl(): ?string
    {
        if ($this->answer_image_path === null || ! $this->hasVisibleAnswer()) {
            return null;
        }

        return Storage::disk(config('uploads.answer_image.disk'))->url($this->answer_image_path);
    }

    public function asker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asked_by');
    }

    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function answerer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'answered_by');
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

    public function scopeAnsweredBy(Builder $q, int $userId): Builder
    {
        return $q->answered()->where('answered_by', $userId);
    }
}