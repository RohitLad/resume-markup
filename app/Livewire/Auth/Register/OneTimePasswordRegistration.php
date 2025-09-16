<?php

namespace App\Livewire\Auth\Register;

use App\Models\User;
use App\Services\RegistrationService;
use App\Services\UserService;
use App\Validator\RegisterValidator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class OneTimePasswordRegistration extends Component
{
    public string $email;

    public string $name;

    private RegisterValidator $registerValidator;

    private UserService $userService;

    private RegistrationService $registrationService;

    public function boot(
        RegisterValidator $registerValidator,
        UserService $userService,
        RegistrationService $registrationService,
    ) {
        $this->registerValidator = $registerValidator;
        $this->userService = $userService;
        $this->registrationService = $registrationService;
    }

    public function render(): View
    {
        return view('livewire.auth.register.registration-form');
    }

    public function register(): void
    {
        $userFields = [
            'email' => $this->email,
            'name' => $this->name,
        ];

        $this->registerValidator->validate($userFields);

        $user = $this->userService->findByEmail($this->email);

        if ($user) {
            $this->addError('email', __('This email is already registered. Please log in instead.'));

            return;
        }

        $user = $this->registrationService->registerUser($userFields);

        $this->sendCode($user);

        $this->redirect(route('login', ['email' => $this->email]));
    }

    protected function sendCode(User $user): void
    {
        if ($this->rateLimitHit()) {
            return;
        }

        $user->sendOneTimePassword();
    }

    protected function rateLimitHit(): bool
    {
        $rateLimitKey = "one-time-password-component-send-code.{$this->email}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            return true;
        }

        RateLimiter::hit($rateLimitKey, 60); // 60 seconds decay time

        return false;
    }
}
