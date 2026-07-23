<?php

namespace App\Livewire;

use App\Enums\QuestionStatus;
use App\Models\Question;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class AdminQuestionsTable extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'q')]
    public string $search = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['statusFilter', 'search']);
        $this->resetPage();
    }

    public function render()
    {
        $validStatuses = array_map(fn (QuestionStatus $s) => $s->value, QuestionStatus::cases());

        $questions = Question::query()
            ->with(['asker:id,name', 'claimer:id,name', 'answerer:id,name'])
            ->when(in_array($this->statusFilter, $validStatuses, true), function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when(trim($this->search) !== '', function ($q) {
                $q->where('content', 'like', '%' . trim($this->search) . '%');
            })
            ->latest('created_at')
            ->paginate(100);

        return view('livewire.admin.questions-table', [
            'questions' => $questions,
        ])
        ->layout('layouts.app')
        ->title('All Questions — Admin — THRP');
    }
}