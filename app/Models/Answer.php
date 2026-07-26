<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * One creator's answer to a question. The question points back at whichever of
 * these is the main answer; the rest are alternatives shown beneath it.
 */
class Answer extends Model
{
    /** @use HasFactory<\Database\Factories\AnswerFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $casts = [
        'anonymously'  => 'boolean',
        'published_at' => 'datetime',
    ];

    protected $fillable = [
        'question_id', 'created_by', 'body', 'image_path', 'anonymously', 'published_at',
    ];

    /** Shown in place of the creator's nickname on an anonymous answer. */
    public const ANONYMOUS_AUTHOR = 'a THRP creator';

    /**
     * Whether this is the question's main answer rather than an alternative.
     */
    public function isPrimary(): bool
    {
        return $this->question?->primary_answer_id === $this->id;
    }

    /**
     * Only the creator who wrote the answer, or an admin, may edit it.
     */
    public function isEditableBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $this->created_by === $user->id
            || $user->role === UserRole::Admin;
    }

    /**
     * Public URL of the attached image, or null when there is none.
     */
    public function imageUrl(): ?string
    {
        if ($this->image_path === null) {
            return null;
        }

        return Storage::disk(config('uploads.answer_image.disk'))->url($this->image_path);
    }

    /**
     * How the answering creator should be credited to $viewer, or null when
     * there is nobody to credit. Admins always see the real nickname so
     * moderation is not blinded; everyone else — including the creator
     * themselves — sees the placeholder on an anonymous answer.
     */
    public function authorNameFor(?User $viewer): ?string
    {
        if ($this->author === null) {
            return null;
        }

        if ($this->anonymously && $viewer?->role !== UserRole::Admin) {
            return self::ANONYMOUS_AUTHOR;
        }

        return $this->author->name;
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->whereNotNull('published_at');
    }

    public function scopeWrittenBy(Builder $q, int $userId): Builder
    {
        return $q->where('created_by', $userId);
    }

    /**
     * Answers that publicly carry their creator's name. Anonymous ones are
     * excluded on purpose: counting them on a public profile would re-attribute
     * what the creator chose not to sign.
     */
    public function scopePubliclyCredited(Builder $q): Builder
    {
        return $q->published()->where('anonymously', false);
    }
}
