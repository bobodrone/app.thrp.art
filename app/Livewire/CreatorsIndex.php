<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The public list of creators — searchable by name, sortable, and linking
 * through to each creator's public profile.
 */
class CreatorsIndex extends Component
{
    use WithPagination;

    public const PER_PAGE = 25;

    /** Sortable columns, mapped to their default direction. */
    private const SORTS = ['name' => 'asc', 'answers' => 'desc'];

    public string $search = '';

    public string $sort = 'name';

    public string $direction = 'asc';

    protected $queryString = [
        'search'    => ['except' => ''],
        'sort'      => ['except' => 'name'],
        'direction' => ['except' => 'asc'],
    ];

    /** Typing narrows the list, so page 1 is the only sensible place to be. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if (! array_key_exists($column, self::SORTS)) {
            return;
        }

        // Same column again flips direction; a new column starts at its default.
        [$this->sort, $this->direction] = $this->sort === $column
            ? [$column, $this->direction === 'asc' ? 'desc' : 'asc']
            : [$column, self::SORTS[$column]];

        $this->resetPage();
    }

    public function render()
    {
        $search = trim($this->search);

        // Guard against a hand-edited query string reaching the query builder.
        $sort      = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'name';
        $direction = $this->direction === 'desc' ? 'desc' : 'asc';

        $creators = User::query()
            ->creators()
            ->select(['id', 'name', 'avatar_path', 'bio'])
            ->withCount([
                'answers as answers_count' => fn (Builder $q) => $q->publiclyCredited(),
            ])
            ->when($search !== '', fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%'))
            ->when(
                $sort === 'answers',
                // Ties fall back to name so the order is stable page to page.
                fn (Builder $q) => $q->orderBy('answers_count', $direction)->orderBy('name'),
                fn (Builder $q) => $q->orderBy('name', $direction),
            )
            ->paginate(self::PER_PAGE);

        return view('livewire.creators.index', [
            'creators' => $creators,
            'sort'      => $sort,
            'direction' => $direction,
        ])
            ->layout('layouts.app')
            ->title('Responders — THRP');
    }
}
