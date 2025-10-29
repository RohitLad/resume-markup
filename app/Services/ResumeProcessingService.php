<?php

namespace App\Services;

use App\Models\Resume;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ResumeProcessingService
{
    public function __construct(private N8NService $n8nService) {}

    /**
     * Initiate resume parsing by sending PDF to n8n workflow
     */
    public function initiateResumeParsing(string $userId, string $filePath): string
    {
        $fullPath = Storage::disk('public')->path($filePath);

        if (! file_exists($fullPath)) {
            Log::error('Resume file not found for parsing', [
                'user_id' => $userId,
                'file_path' => $filePath,
                'full_path' => $fullPath,
            ]);
            throw new \Exception('Resume file not found');
        }

        // Mark parsing as active
        ResumeProcessingStatus::startParsing((int) $userId);

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
     * Initiate knowledge base generation by sending profile data to n8n workflow
     */
    public function initiateKnowledgeBaseGeneration(string $userId): string
    {
        $profile = \App\Models\Profile::where('user_id', $userId)->first();

        if (! $profile || ! $profile->data) {
            Log::warning('Knowledge base generation failed: No profile data found', [
                'user_id' => $userId,
            ]);
            throw new \Exception('No profile data found for knowledge base generation');
        }

        // Mark knowledge base generation as active for this user
        ResumeProcessingStatus::startKnowledgeBaseGeneration((int) $userId);

        $webhookUrl = $this->n8nService->getWebhookUrl();
        $requestId = $this->n8nService->generateKnowledgeBase(
            $profile->data,
            $webhookUrl,
            [
                'user_id' => $userId,
            ]
        );

        Log::info('Knowledge base generation initiated', [
            'user_id' => $userId,
            'request_id' => $requestId,
            'webhook_url' => $webhookUrl,
        ]);

        return $requestId;
    }

    /**
     * Check if knowledge base needs to be regenerated
     */
    public function needsKnowledgeBaseUpdate(string $userId): bool
    {
        $profile = \App\Models\Profile::where('user_id', $userId)->first();

        if (! $profile || ! $profile->data) {
            return false;
        }

        // If knowledge base has never been generated, or profile was updated after knowledge base
        return ! $profile->knowledgebase_updated_at || 
               $profile->knowledgebase_updated_at->lt($profile->updated_at);
    }

    /**
     * Initiate resume generation by sending profile data to n8n workflow
     */
    public function initiateResumeGeneration(Resume $resume): string
    {
        $user = $resume->user;
        $profile = $user->profile;

        if (! $profile || ! $profile->data) {
            Log::warning('Resume generation failed: No profile data found', [
                'resume_id' => $resume->id,
                'user_id' => $user->id,
            ]);
            throw new \Exception('No profile data found for resume generation');
        }

        // Check if knowledge base needs to be updated first
        if ($this->needsKnowledgeBaseUpdate($user->id)) {
            Log::info('Knowledge base needs update before resume generation', [
                'user_id' => $user->id,
                'resume_id' => $resume->id,
                'profile_updated_at' => $profile->updated_at,
                'knowledgebase_updated_at' => $profile->knowledgebase_updated_at,
            ]);
            
            throw new \Exception('Knowledge base needs to be updated before resume generation');
        }

        // Mark generation as active for this specific resume
        ResumeProcessingStatus::startGeneration($resume->id);

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
