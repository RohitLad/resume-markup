<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    public function createUser(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => isset($data['password']) ? Hash::make($data['password']) : Hash::make(Str::random(32)),
        ]);
    }

    public function updateUserLastSeen(User $user)
    {
        $user->last_seen_at = now();
        $user->save();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', strtolower($email))->first();
    }
}
