<?php

namespace App\Models;

use App\Enums\QuestionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    /** @use HasFactory<\Database\Factories\QuestionFactory> */
    use HasFactory;

    protected $casts = [
        'status'      => QuestionStatus::class,
        'claimed_at'  => 'datetime',
        'answered_at' => 'datetime',
    ];

    protected $fillable = [
        'content', 'status', 'asked_by', 'claimed_by', 'answered_by',
        'answer', 'claimed_at', 'answered_at',
    ];

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

    public function scopeAnsweredBy(Builder $q, int $userId): Builder
    {
        return $q->where('answered_by', $userId)
            ->where('status', QuestionStatus::Answered);
    }
}