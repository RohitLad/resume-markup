<?php

namespace App\Livewire\Auth\Login;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\OneTimePasswords\Livewire\OneTimePasswordComponent;

class OneTimePasswordLogin extends OneTimePasswordComponent
{
    public function mount(?string $redirectTo = null, ?string $email = ''): void
    {
        parent::mount($redirectTo, request()->query('email', $email));
    }

    public function render(): View
    {
        return view("livewire.auth.login.{$this->showViewName()}");
    }

    protected function rateLimitHit(): bool
    {
        $rateLimitKey = "one-time-password-component-send-code.{$this->email}";

        RateLimiter::hit($rateLimitKey, 60); // 60 seconds decay time

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return true;
        }

        return false;
    }

    protected function sendCode(): void
    {
        $user = $this->findUser();

        if (! $user) {
            return;
        }

        if ($this->rateLimitHit()) {
            return;
        }

        $this->displayingEmailForm = false;

        $user->sendOneTimePassword();
    }
}
