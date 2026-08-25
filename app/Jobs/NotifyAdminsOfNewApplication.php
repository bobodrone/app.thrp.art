<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Mail\ApplicationReceived;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotifyAdminsOfNewApplication implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $name,
        public string $message,
    ) {}

    public function handle(): void
    {
        $admins = User::where('role', UserRole::Admin)->get();
        if ($admins->isEmpty()) {
            return;
        }

        $preview  = Str::limit($this->message, 300);
        $reviewUrl = config('app.url') . route('admin.applications', [], false);

        foreach ($admins as $admin) {
            Mail::to($admin)->send(new ApplicationReceived(
                applicantName: $this->name,
                applicantEmail: $this->email,
                applicantMessage: $preview,
                reviewUrl: $reviewUrl,
            ));
        }
    }
}
