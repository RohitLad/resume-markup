<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gemini API Key
    |--------------------------------------------------------------------------
    |
    | Your Gemini API key from Google AI Studio.
    | Get it from: https://makersuite.google.com/app/apikey
    */

    'api_key' => env('GEMINI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Gemini Model
    |--------------------------------------------------------------------------
    |
    | The Gemini model to use for parsing resumes.
    | Available models: gemini-1.5-flash, gemini-1.5-pro, etc.
    */

    'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | The timeout for API requests in seconds.
    */

    'timeout' => env('GEMINI_TIMEOUT', 30),

];