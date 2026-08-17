<?php

namespace App\Jobs;

use App\Mail\AnswerNotification;
use App\Models\Question;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotifyAskerOfAnswer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Question $question) {}

    public function handle(): void
    {
        $asker = $this->question->asker;
        if (! $asker) {
            return;
        }

        $preview = Str::limit($this->question->content, 200);
        $url     = route('questions.show', $this->question->id, absolute: false);
        $url     = config('app.url') . $url;

        Mail::to($asker)->send(new AnswerNotification(
            askerName: $asker->name ?? 'there',
            questionPreview: $preview,
            questionUrl: $url,
        ));
    }
}
