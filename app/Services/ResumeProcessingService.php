<?php

namespace App\Services;

use App\Models\Resume;
use App\Services\N8NService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ResumeProcessingService
{
    public function __construct(private N8NService $n8nService)
    {
    }

    /**
     * Initiate resume parsing by sending PDF to n8n workflow
     */
    public function initiateResumeParsing(string $userId, string $filePath): string
    {
        $fullPath = Storage::disk('public')->path($filePath);

        if (!file_exists($fullPath)) {
            Log::error('Resume file not found for parsing', [
                'user_id' => $userId,
                'file_path' => $filePath,
                'full_path' => $fullPath,
            ]);
            throw new \Exception('Resume file not found');
        }

        $webhookUrl = $this->n8nService->getWebhookUrl();
        $requestId = $this->n8nService->parseResumePdf($fullPath, $webhookUrl, [
            'user_id' => $userId,
        ]);

        Log::info('Resume parsing initiated', [
            'user_id' => $userId,
            'request_id' => $requestId,
            'webhook_url' => $webhookUrl,
        ]);

        return $requestId;
    }

    /**
     * Initiate resume generation by sending profile data to n8n workflow
     */
    public function initiateResumeGeneration(Resume $resume): string
    {
        $user = $resume->user;
        $profile = $user->profile;

        if (!$profile || !$profile->data) {
            Log::warning('Resume generation failed: No profile data found', [
                'resume_id' => $resume->id,
                'user_id' => $user->id,
            ]);
            throw new \Exception('No profile data found for resume generation');
        }

        $webhookUrl = $this->n8nService->getWebhookUrl();
        $requestId = $this->n8nService->generateResumeMarkdown(
            $profile->data,
            $resume->job_title,
            $resume->job_description,
            $webhookUrl,
            [
                'user_id' => $user->id,
                'resume_id' => $resume->id,
            ]
        );

        Log::info('Resume generation initiated', [
            'resume_id' => $resume->id,
            'user_id' => $user->id,
            'request_id' => $requestId,
            'job_title' => $resume->job_title,
            'webhook_url' => $webhookUrl,
        ]);

        return $requestId;
    }
}