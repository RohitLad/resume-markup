<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class RegistrationService
{
    public function registerUser(array $userData): User
    {
        return User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => isset($userData['password']) ? bcrypt($userData['password']) : bcrypt(Str::random(16)),
        ]);
    }
}
