<?php

namespace App\Livewire;

use App\Jobs\NotifyAdminsOfContactMessage;
use App\Models\ContactMessage;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The public contact form.
 *
 * Anyone can post here, signed in or not, so it needs spam protection — but
 * the brief ruled out anything that costs money or needs an account
 * elsewhere. Three free layers instead, checked in cheapest-first order:
 *
 *  1. A honeypot field. Hidden from people, filled in by form-stuffing bots.
 *     A hit is answered with the success screen: telling a bot it failed only
 *     teaches whoever runs it to fix the bot.
 *  2. A minimum fill-in time. Nobody reads and answers a form in two seconds.
 *  3. A per-IP rate limit, hourly and daily, on Laravel's RateLimiter — which
 *     runs on the database cache store this app already has configured.
 *
 * None of the three is airtight alone; together they stop the volume that
 * makes an open form unusable, with no puzzle for a real visitor to solve.
 */
class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $subject = '';

    public string $message = '';

    /**
     * The honeypot. A real browser never puts anything here — the field is
     * hidden and taken out of the tab order. Anything at all in it is a bot.
     */
    public string $website = '';

    /**
     * When the form was handed over, as a unix timestamp. Locked so the page
     * cannot post back an older value to walk past the timer.
     */
    #[Locked]
    public int $startedAt = 0;

    public bool $submitted = false;

    protected function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'min:2', 'max:60'],
            'email'   => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'min:3', 'max:120'],
            'message' => ['required', 'string', 'min:20', 'max:2000'],
        ];
    }

    public function mount(): void
    {
        $this->startedAt = time();

        // Save signed-in people retyping what we already know about them.
        if ($user = auth()->user()) {
            $this->name  = $user->name;
            $this->email = $user->email;
        }
    }

    public function submit(): void
    {
        // 1. Honeypot — accept and discard, so the bot has nothing to learn.
        if (trim($this->website) !== '') {
            $this->submitted = true;

            return;
        }

        // 2. Too fast to have been typed.
        if (time() - $this->startedAt < (int) config('contact.spam.min_seconds')) {
            $this->addError('message', 'That was quick! Please take a moment and send it again.');

            return;
        }

        // 3. Too many already from this address.
        if ($this->rateLimited()) {
            return;
        }

        $this->validate();

        $contactMessage = ContactMessage::create([
            'user_id' => auth()->id(),
            'name'    => $this->name,
            'email'   => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
            'ip_hash' => $this->ipHash(),
        ]);

        $this->recordAttempt();

        NotifyAdminsOfContactMessage::dispatch($contactMessage);

        $this->submitted = true;
    }

    /**
     * Checks both windows without spending an attempt — the counters only go
     * up once a message is actually stored, so a visitor who trips validation
     * five times has not used up their quota.
     */
    protected function rateLimited(): bool
    {
        foreach ($this->limits() as [$key, $max, , $window]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $seconds = RateLimiter::availableIn($key);

                $this->addError('message', sprintf(
                    'You have already sent %d messages %s. Please try again in %s.',
                    $max,
                    $window,
                    $this->humanise($seconds),
                ));

                return true;
            }
        }

        return false;
    }

    protected function recordAttempt(): void
    {
        foreach ($this->limits() as [$key, , $decay]) {
            RateLimiter::hit($key, $decay);
        }
    }

    /**
     * @return array<int, array{0: string, 1: int, 2: int, 3: string}>
     */
    protected function limits(): array
    {
        $ip = $this->ipHash();

        return [
            ['contact:hour:' . $ip, (int) config('contact.spam.max_per_hour'), 3600, 'in the last hour'],
            ['contact:day:' . $ip, (int) config('contact.spam.max_per_day'), 86400, 'today'],
        ];
    }

    /** Hashed so neither the cache keys nor the stored rows hold a raw IP. */
    protected function ipHash(): string
    {
        return hash('sha256', (string) request()->ip());
    }

    protected function humanise(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' seconds';
        }

        $minutes = (int) ceil($seconds / 60);

        if ($minutes < 60) {
            return $minutes . ' minutes';
        }

        return (int) ceil($minutes / 60) . ' hours';
    }

    public function render()
    {
        return view('livewire.contact.form', [
            'honeypotField' => config('contact.spam.honeypot'),
        ])
            ->layout('layouts.app')
            ->title('Contact — THRP');
    }
}
