<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class N8NService
{
    /**
     * Generate resume markdown using n8n workflow
     */
    public function generateResumeMarkdown(array $profileData, string $jobTitle, string $jobDescription): string
    {
        $n8nUrl = config('services.n8n.url');
        $endpoint = config('services.n8n.endpoints.generate_resume', 'generate-resume');

        if (! $n8nUrl) {
            throw new \Exception('N8N URL not configured');
        }

        $fullUrl = rtrim($n8nUrl, '/').'/'.ltrim($endpoint, '/');

        $startTime = microtime(true);

        Log::info('Sending request to n8n', [
            'url' => $fullUrl,
            'job_title' => $jobTitle,
            'profile_keys' => array_keys($profileData),
        ]);

        try {
            $apiKey = config('services.n8n.api_key');
            $httpClient = Http::timeout(120); // Increased timeout for n8n processing

            if ($apiKey) {
                $httpClient = $httpClient->withHeaders([
                    'API_KEY' => $apiKey,
                ]);
            }

            $response = $httpClient->post($fullUrl, [
                'job_title' => $jobTitle,
                'job_description' => $jobDescription,
                'profile' => $profileData,
            ]);

            $responseTime = microtime(true) - $startTime;

            if (! $response->successful()) {
                Log::error('n8n request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $fullUrl,
                ]);
                throw new \Exception('n8n workflow failed: '.$response->body());
            }

            $data = $response->json();

            // Check if the response contains the generated resume
            if (! isset($data['resume']) && ! isset($data['content']) && ! isset($data['markdown'])) {
                Log::error('Invalid n8n response format', ['response' => $data]);
                throw new \Exception('Invalid response from n8n workflow - missing resume content');
            }

            // Handle different possible response formats
            $content = $data['resume'] ?? $data['content'] ?? $data['markdown'] ?? '';

            if (empty($content)) {
                throw new \Exception('Empty resume content received from n8n workflow');
            }

            Log::info('Successfully received resume from n8n', [
                'content_length' => strlen($content),
                'response_time_seconds' => round($responseTime, 2),
            ]);

            return $content;

        } catch (\Exception $e) {
            $responseTime = microtime(true) - $startTime;
            Log::error('n8n service error', [
                'error' => $e->getMessage(),
                'url' => $fullUrl,
                'job_title' => $jobTitle,
                'response_time_seconds' => round($responseTime, 2),
            ]);
            throw $e;
        }
    }

    /**
     * Test the n8n connection
     */
    public function testConnection(): bool
    {
        try {
            $n8nUrl = config('services.n8n.url');
            if (! $n8nUrl) {
                return false;
            }

            $response = Http::timeout(10)->get($n8nUrl);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('n8n connection test failed', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
