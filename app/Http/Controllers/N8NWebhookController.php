<?php

namespace App\Http\Controllers;

use App\Jobs\N8NWebhookJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class N8NWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        Log::info('N8N webhook received', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'query_params' => $request->query(),
            'body_preview' => substr($request->getContent(), 0, 500),
        ]);

        // Validate API key (check both header and query parameter)
        $apiKey = $request->header('API_KEY') ?: $request->query('api_key');
        if ($apiKey !== config('services.n8n.api_key')) {
            Log::warning('N8N webhook unauthorized', [
                'provided_key' => substr($apiKey ?: 'none', 0, 10) . '...',
                'expected_key' => substr(config('services.n8n.api_key') ?: 'none', 0, 10) . '...',
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Dispatch job for processing
        N8NWebhookJob::dispatch($request->all());

        Log::info('N8N webhook accepted and job dispatched');

        return response()->json(['status' => 'accepted']);
    }
}