<?php

namespace App\Jobs;

use App\Models\Profile;
use App\Models\Resume;
use App\Services\ResumeProcessingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class N8NWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $payload
    ) {}

    public function handle(): void
    {
        try {
            Log::info('Processing n8n webhook', [
                'request_id' => $this->payload['request_id'] ?? null,
                'type' => $this->payload['type'] ?? null,
                'success' => $this->payload['success'] ?? null,
            ]);

            if (!isset($this->payload['type'])) {
                Log::error('Missing type in webhook payload', ['payload' => $this->payload]);
                return;
            }

            if (!isset($this->payload['success']) || !$this->payload['success']) {
                Log::error('Webhook indicates failure', [
                    'type' => $this->payload['type'],
                    'error' => $this->payload['error'] ?? 'Unknown error',
                    'request_id' => $this->payload['request_id'] ?? null,
                ]);
                return;
            }

            switch ($this->payload['type']) {
                case 'parse_resume':
                    $this->handleParseResume();
                    break;
                case 'generate_resume':
                    $this->handleGenerateResume();
                    break;
                default:
                    Log::error('Unknown webhook type', ['type' => $this->payload['type']]);
            }

        } catch (\Exception $e) {
            Log::error('Failed to process n8n webhook', [
                'error' => $e->getMessage(),
                'payload' => $this->payload,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function handleParseResume(): void
    {
        $userId = $this->payload['user_id'] ?? null;
        $data = $this->payload['content'] ?? null;

        if (!$userId || !$data) {
            Log::error('Missing user_id or data in parse resume webhook', [
                'user_id' => $userId,
                'has_data' => !empty($data),
            ]);
            return;
        }

        // Merge with empty structure to ensure all fields exist
        $parsedData = array_merge($this->getEmptyStructure(), $data);

        Profile::updateOrCreate(
            ['user_id' => $userId],
            ['data' => $parsedData]
        );

        // Clear the parsing status cache
        ResumeProcessingStatus::finishParsing((int) $userId);

        Log::info('Successfully processed resume parsing webhook', [
            'user_id' => $userId,
            'fields_parsed' => count($parsedData),
        ]);
    }

    private function handleGenerateResume(): void
    {
        $resumeId = $this->payload['resume_id'] ?? null;
        $content = $this->payload['content'] ?? null;

        if (!$resumeId || !$content) {
            Log::error('Missing resume_id or content in generate resume webhook', [
                'resume_id' => $resumeId,
                'has_content' => !empty($content),
            ]);
            return;
        }

        $resume = Resume::find($resumeId);
        if (!$resume) {
            Log::error('Resume not found for webhook processing', ['resume_id' => $resumeId]);
            return;
        }

        $resume->update(['content' => $content]);

        // Clear the generation status cache
        ResumeProcessingStatus::finishGeneration((int) $resumeId);

        Log::info('Successfully processed resume generation webhook', [
            'resume_id' => $resumeId,
            'user_id' => $resume->user_id,
            'content_length' => strlen($content),
        ]);
    }

    protected function getEmptyStructure(): array
    {
        return [
            'basics' => [
                'name' => '',
                'label' => '',
                'image' => '',
                'email' => '',
                'phone' => '',
                'url' => '',
                'summary' => '',
                'location' => [
                    'address' => '',
                    'postalCode' => '',
                    'city' => '',
                    'countryCode' => '',
                    'region' => '',
                ],
                'profiles' => [],
            ],
            'work' => [],
            'volunteer' => [],
            'education' => [],
            'awards' => [],
            'certificates' => [],
            'publications' => [],
            'skills' => [],
            'languages' => [],
            'interests' => [],
            'references' => [],
            'projects' => [],
        ];
    }
}