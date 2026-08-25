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

    #[Url(as: 'deleted')]
    public bool $showDeleted = false;

    /** Hidden questions are listed by default — they are live rows, not removed ones. */
    #[Url(as: 'hidden')]
    public bool $hiddenOnly = false;

    #[Locked]
    public ?int $editingId = null;

    public string $editContent = '';

    public string $editAnswer = '';

    public bool $editHasAnswer = false;

    public bool $showEdit = false;

    #[Locked]
    public ?int $hidingId = null;

    public string $hideReason = '';

    public bool $showHide = false;

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingShowDeleted(): void
    {
        $this->resetPage();
    }

    public function updatingHiddenOnly(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['statusFilter', 'search', 'showDeleted', 'hiddenOnly']);
        $this->resetPage();
    }

    public function edit(int $id): void
    {
        $question = Question::findOrFail($id);

        $this->editingId     = $question->id;
        $this->editContent   = $question->content;
        $this->editAnswer    = $question->primaryAnswer->body ?? '';
        $this->editHasAnswer = $question->hasVisibleAnswer();

        $this->resetErrorBag();
        $this->showEdit = true;
    }

    public function saveEdit(): void
    {
        $rules = ['editContent' => ['required', 'string', 'between:10,2000']];

        if ($this->editHasAnswer) {
            $rules['editAnswer'] = ['required', 'string', 'between:10,10000'];
        }

        $this->validate($rules, [
            'editContent.required' => 'Question text is required.',
            'editContent.between'  => 'Question must be 10–2000 characters.',
            'editAnswer.required'  => 'Answer text is required.',
            'editAnswer.between'   => 'Answer must be 10–10 000 characters.',
        ]);

        $question = Question::findOrFail($this->editingId);

        $question->update(['content' => trim($this->editContent)]);

        if ($this->editHasAnswer) {
            $question->primaryAnswer?->update(['body' => $this->editAnswer]);
        }

        $this->reset(['editingId', 'editContent', 'editAnswer', 'editHasAnswer', 'showEdit']);
        session()->flash('admin-questions-ok', 'Question updated.');
    }

    public function deleteAnswer(int $id): void
    {
        // Soft-delete only the main answer: the row is kept for recovery, and
        // the question reopens so it can be reclaimed and answered again. Any
        // alternative answers stay where they are.
        $question = Question::findOrFail($id);

        if ($answer = $question->primaryAnswer) {
            $question->removeAnswer($answer);
        }

        $this->reset(['editingId', 'editContent', 'editAnswer', 'editHasAnswer', 'showEdit']);
        session()->flash('admin-questions-ok', 'Answer removed — question reopened.');
    }

    public function delete(int $id): void
    {
        Question::whereKey($id)->delete();

        session()->flash('admin-questions-ok', 'Question deleted.');
    }

    public function restore(int $id): void
    {
        Question::onlyTrashed()->whereKey($id)->restore();

        session()->flash('admin-questions-ok', 'Question restored.');
    }

    public function forceDelete(int $id): void
    {
        Question::withTrashed()->whereKey($id)->forceDelete();

        session()->flash('admin-questions-ok', 'Question permanently deleted.');
    }

    /**
     * Open the hide dialog. The reason is asked for here rather than assumed,
     * but stays optional — some questions come down without needing a word.
     */
    public function confirmHide(int $id): void
    {
        $question = Question::findOrFail($id);

        $this->hidingId   = $question->id;
        $this->hideReason = $question->hidden_reason ?? '';

        $this->resetErrorBag();
        $this->showHide = true;
    }

    public function hide(): void
    {
        $this->validate(
            ['hideReason' => ['nullable', 'string', 'max:1000']],
            ['hideReason.max' => 'Reason must be 1000 characters or fewer.'],
        );

        $question = Question::findOrFail($this->hidingId);

        // Written for the asker: this is the text they read on their own
        // question page, not an internal note.
        $question->hide(auth()->user(), $this->hideReason);

        $this->reset(['hidingId', 'hideReason', 'showHide']);
        session()->flash('admin-questions-ok', 'Question hidden — the asker can still see it, with your reason.');
    }

    public function unhide(int $id): void
    {
        Question::findOrFail($id)->unhide();

        session()->flash('admin-questions-ok', 'Question is public again.');
    }

    public function restoreAnswer(int $id): void
    {
        $question = Question::findOrFail($id);
        $answer   = $question->primaryAnswer()->withTrashed()->first();

        if ($answer?->trashed()) {
            $question->restoreAnswer($answer);
        }

        session()->flash('admin-questions-ok', 'Answer restored.');
    }

    public function render()
    {
        $validStatuses = array_map(fn (QuestionStatus $s) => $s->value, QuestionStatus::cases());

        $questions = Question::query()
            ->when($this->showDeleted, fn ($q) => $q->withTrashed())
            // Every answer comes along: the table credits all of a question's
            // responders, not only the one holding the main slot.
            ->with(['asker:id,name', 'claimer:id,name', 'hiddenBy:id,name', 'primaryAnswer.author:id,name,role', 'answers.author:id,name,role'])
            ->withCount('answers')
            ->when(in_array($this->statusFilter, $validStatuses, true), function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when(trim($this->search) !== '', function ($q) {
                $q->where('content', 'like', '%' . trim($this->search) . '%');
            })
            ->when($this->hiddenOnly, fn ($q) => $q->whereNotNull('hidden_at'))
            ->latest('created_at')
            ->paginate(100);

        return view('livewire.admin.questions-table', [
            'questions' => $questions,
        ])
            ->layout('layouts.app')
            ->title('All Questions — Admin — THRP');
    }
}
