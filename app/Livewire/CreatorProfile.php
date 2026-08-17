<?php

namespace App\Livewire;

use App\Livewire\Concerns\HandlesImageUploads;
use App\Models\User;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The creator's public face: picture, bio, social links — plus the anonymity
 * preference, which applies to answers posted from here on.
 */
class CreatorProfile extends Component
{
    use HandlesImageUploads, WithFileUploads;

    protected function uploadConfigKey(): string
    {
        return 'avatar';
    }

    /** Most creators have a handful of profiles at most; the cap keeps the form sane. */
    public const MAX_LINKS = 6;

    public string $bio = '';

    /** @var array<int, array{label: string, url: string}> */
    public array $socialLinks = [];

    public bool $postsAnonymously = false;

    /** Pending replacement picture. */
    public ?TemporaryUploadedFile $avatar = null;

    /** Set when the creator clears the already-saved picture. */
    public bool $removeAvatar = false;

    public function mount(): void
    {
        $user = auth()->user();

        $this->bio              = $user->bio ?? '';
        $this->socialLinks      = $user->social_links ?? [];
        $this->postsAnonymously = $user->posts_anonymously;
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.creator.profile', [
            'user'          => $user,
            'avatarPreview' => $this->previewUrl(
                $this->avatar,
                $this->removeAvatar ? null : $user->avatarUrl(),
            ),
            'canAddLink' => count($this->socialLinks) < self::MAX_LINKS,
        ])
        ->layout('layouts.app')
        ->title('Responder Profile — THRP');
    }

    public function updatedAvatar(): void
    {
        // A fresh pick supersedes an earlier "remove".
        $this->removeAvatar = false;

        $this->validateOnly('avatar', $this->imageRules('avatar'), $this->imageMessages('avatar'));
    }

    /**
     * Clearing drops the pending upload *and* marks the saved picture for
     * removal — one button covers both, since the form only ever shows one.
     */
    public function clearAvatar(): void
    {
        $this->reset('avatar');
        $this->resetErrorBag('avatar');
        $this->removeAvatar = true;
    }

    public function addLink(): void
    {
        if (count($this->socialLinks) >= self::MAX_LINKS) {
            return;
        }

        $this->socialLinks[] = ['label' => '', 'url' => ''];
    }

    public function removeLink(int $index): void
    {
        unset($this->socialLinks[$index]);

        // Re-index so Livewire's array binding stays contiguous.
        $this->socialLinks = array_values($this->socialLinks);
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'bio'                  => ['nullable', 'string', 'max:1000'],
            'socialLinks'          => ['array', 'max:'.self::MAX_LINKS],
            'socialLinks.*.label'  => ['required', 'string', 'max:40'],
            'socialLinks.*.url'    => ['required', 'string', 'max:255', 'url:http,https'],
            'postsAnonymously'     => ['boolean'],
        ] + $this->imageRules('avatar'), [
            'bio.max'                     => 'Bio must be 1 000 characters or fewer.',
            'socialLinks.*.label.required' => 'Give the link a name (e.g. Instagram).',
            'socialLinks.*.label.max'      => 'Link names must be 40 characters or fewer.',
            'socialLinks.*.url.required'   => 'Enter the link address.',
            'socialLinks.*.url.url'        => 'Enter a full address starting with http:// or https://.',
        ] + $this->imageMessages('avatar'));

        $user         = auth()->user();
        $previousPath = $user->avatar_path;

        $avatarPath = match (true) {
            (bool) $this->avatar => $this->storeImage($this->avatar),
            $this->removeAvatar  => null,
            default              => $previousPath,
        };

        $user->update([
            'avatar_path'       => $avatarPath,
            'bio'               => trim($this->bio) !== '' ? trim($this->bio) : null,
            'social_links'      => $this->normaliseLinks($validated['socialLinks']),
            'posts_anonymously' => $validated['postsAnonymously'],
        ]);

        if ($previousPath !== $avatarPath) {
            $this->deleteImage($previousPath);
        }

        $this->reset('avatar', 'removeAvatar');

        session()->flash('profile-saved', 'Profile updated.');
    }

    /**
     * @param  array<int, array{label: string, url: string}>  $links
     * @return array<int, array{label: string, url: string}>|null
     */
    protected function normaliseLinks(array $links): ?array
    {
        $links = array_values(array_map(static fn (array $link): array => [
            'label' => trim($link['label']),
            'url'   => trim($link['url']),
        ], $links));

        return $links === [] ? null : $links;
    }
}
