<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPassword;
use App\Notifications\VerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'name', 'email', 'password', 'role', 'email_verified_at',
    'avatar_path', 'bio', 'social_links', 'posts_anonymously',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role'              => UserRole::Member->value,
        'posts_anonymously' => false,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
            'social_links'      => 'array',
            'posts_anonymously' => 'boolean',
        ];
    }

    /**
     * Public URL of the profile picture, or null when none has been uploaded.
     */
    public function avatarUrl(): ?string
    {
        if ($this->avatar_path === null) {
            return null;
        }

        return Storage::disk(config('uploads.avatar.disk'))->url($this->avatar_path);
    }

    /** Creators and admins both work the creator side of the app. */
    public function isCreator(): bool
    {
        return $this->role->isAtLeast(UserRole::Creator);
    }

    /** Everyone who can hold a creator profile — the public creator list. */
    public function scopeCreators(Builder $q): Builder
    {
        return $q->whereIn('role', [UserRole::Creator, UserRole::Admin]);
    }

    /** Up to two initials, for the avatar placeholder. */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $letters = array_map(
            fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice($words, 0, 2),
        );

        return implode('', $letters) ?: '?';
    }

    /**
     * Social links safe to render: anything whose scheme is not http(s) is
     * dropped, so an old or hand-edited row cannot smuggle a javascript: URL
     * into an href.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public function publicSocialLinks(): array
    {
        return array_values(array_filter(
            $this->social_links ?? [],
            static fn (array $link): bool => in_array(
                strtolower((string) parse_url($link['url'] ?? '', PHP_URL_SCHEME)),
                ['http', 'https'],
                true,
            ),
        ));
    }

    public function questionsAsked(): HasMany
    {
        return $this->hasMany(Question::class, 'asked_by');
    }

    public function questionsClaimed(): HasMany
    {
        return $this->hasMany(Question::class, 'claimed_by');
    }

    /** Every answer this creator has written, main or alternative. */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class, 'created_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CreatorApplication::class, 'email', 'email');
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmail);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }
}
