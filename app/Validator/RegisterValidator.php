<?php

namespace App\Validator;

use Illuminate\Support\Facades\Validator;

class RegisterValidator
{
    public function validate(array $fields, bool $passwordConfirmed = true)
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        ];

        if (false) {  // todo: add a config option to enable/disable password during registration
            $rules['password'] = ['required', 'string', 'min:8'];

            if ($passwordConfirmed) {
                $rules['password'][] = 'confirmed';
            }
        }

        if (config('app.recaptcha_enabled')) {
            $rules[recaptchaFieldName()] = recaptchaRuleName();
        }

        return Validator::make($fields, $rules);
    }
}
