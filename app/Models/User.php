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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'email', 'password', 'role', 'email_verified_at',
    'avatar_path', 'bio', 'social_links', 'posts_anonymously',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Where a scrubbed account's placeholder address points — a reserved TLD, so it can never be delivered to. */
    public const ANONYMISED_EMAIL_DOMAIN = 'removed.invalid';

    protected $attributes = [
        'role'              => UserRole::Member->value,
        'posts_anonymously' => false,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'blocked_at'        => 'datetime',
            'anonymised_at'     => 'datetime',
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

    /**
     * Everyone who can hold a creator profile — the public creator list. A
     * blocked account drops out of it: the block is not much of a block if
     * their profile is still on the directory page.
     */
    public function scopeCreators(Builder $q): Builder
    {
        return $q->whereIn('role', [UserRole::Creator, UserRole::Admin])
            ->whereNull('blocked_at');
    }

    // ── Moderation ────────────────────────────────────────────────────────

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * Shut the account. The reason is read by the person themselves at the
     * sign-in screen, so an empty one is stored as none rather than as "".
     *
     * Their open sessions go with it: without that, blocking someone
     * mid-spam does nothing until they happen to log out.
     */
    public function block(User $admin, ?string $reason = null): void
    {
        $reason = trim((string) $reason);

        // forceFill: moderation columns are deliberately absent from #[Fillable],
        // so no request payload can ever reach them.
        $this->forceFill([
            'blocked_at'     => now(),
            'blocked_by'     => $admin->id,
            'blocked_reason' => $reason === '' ? null : $reason,
        ])->save();

        $this->killSessions();
    }

    /**
     * What a blocked person is told when they try to get in. Written for them,
     * which is why the reason is quoted rather than paraphrased.
     */
    public function blockNotice(): string
    {
        return $this->blocked_reason === null
            ? 'This account has been blocked. Get in touch if you think that is a mistake.'
            : 'This account has been blocked. Reason: ' . $this->blocked_reason;
    }

    /** Clears all three columns — an unblocked account carries no history of it. */
    public function unblock(): void
    {
        $this->forceFill([
            'blocked_at'     => null,
            'blocked_by'     => null,
            'blocked_reason' => null,
        ])->save();
    }

    /**
     * Strip the person out of the account and leave everything they wrote
     * standing. Deleting the row is not the alternative it looks like:
     * questions.asked_by cascades, so it would take their questions and the
     * responders' answers on those questions down with it.
     */
    public function anonymise(): void
    {
        $this->deleteImage();

        $this->forceFill([
            'name'              => 'Deleted user',
            'email'             => 'deleted-' . $this->id . '@' . self::ANONYMISED_EMAIL_DOMAIN,
            'email_verified_at' => null,
            // No reset flow can reach this address, so the account is closed
            // for good rather than merely locked.
            'password'          => Hash::make(Str::random(64)),
            'remember_token'    => null,
            'avatar_path'       => null,
            'bio'               => null,
            'social_links'      => null,
            'role'              => UserRole::Member,
            'anonymised_at'     => now(),
        ])->save();

        $this->killSessions();
    }

    public function isAnonymised(): bool
    {
        return $this->anonymised_at !== null;
    }

    /** Sessions live in the database, so ending them is a delete, not a wait. */
    private function killSessions(): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $this->id)
                ->delete();
        }
    }

    private function deleteImage(): void
    {
        if ($this->avatar_path !== null) {
            Storage::disk(config('uploads.avatar.disk'))->delete($this->avatar_path);
        }
    }

    public function scopeBlocked(Builder $q): Builder
    {
        return $q->whereNotNull('blocked_at');
    }

    public function scopeNotBlocked(Builder $q): Builder
    {
        return $q->whereNull('blocked_at');
    }

    public function blockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
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
