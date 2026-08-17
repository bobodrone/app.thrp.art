<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Mail\NewQuestionNotification;
use App\Models\Question;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotifyCreatorsOfNewQuestion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Question $question) {}

    public function handle(): void
    {
        $creators = User::whereIn('role', [UserRole::Creator, UserRole::Admin])->get();
        if ($creators->isEmpty()) {
            return;
        }

        $preview = Str::limit($this->question->content, 200);
        $url     = route('creator.questions.show', $this->question->id, absolute: false);
        $url     = config('app.url') . $url;

        foreach ($creators as $creator) {
            Mail::to($creator)->send(new NewQuestionNotification(
                creatorName: $creator->name ?? 'Responder',
                questionPreview: $preview,
                questionUrl: $url,
            ));
        }
    }
}
