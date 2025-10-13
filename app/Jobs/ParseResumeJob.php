<?php

namespace App\Jobs;

use App\Models\Profile;
use App\Services\AIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ParseResumeJob implements ShouldQueue
{
    use Queueable;

    protected string $userId;
    protected string $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct(string $userId, string $filePath)
    {
        $this->userId = $userId;
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Copy the file to a temp location if needed
        $tempPath = storage_path('app/temp/' . uniqid() . '.pdf');
        if (!file_exists(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }
        copy($this->filePath, $tempPath);

        try {
            $aiService = app(AIService::class);
            $parsedData = $aiService->parseResumePdf($tempPath);

            // Merge with empty structure to ensure all fields exist
            $parsedData = array_merge($this->getEmptyStructure(), $parsedData);

            Profile::updateOrCreate(
                ['user_id' => $this->userId],
                ['data' => $parsedData]
            );

            // Clean up temp file
            unlink($tempPath);
        } catch (\Exception $e) {
            // Log error or handle
            unlink($tempPath);
            throw $e;
        }
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
