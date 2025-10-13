<?php

namespace App\Jobs;

use App\Models\Profile;
use App\Services\N8NService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Log;

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
        try {
            // Get the full path - filePath is already relative like 'resumes/filename.pdf'
            $fullPath = Storage::disk('public')->path($this->filePath);

            if (! file_exists($fullPath)) {
                Log::error('Resume file not found', [
                    'user_id' => $this->userId,
                    'file_path' => $this->filePath,
                    'full_path' => $fullPath,
                ]);
                throw new \Exception('Resume file not found');
            }

            $n8nService = app(N8NService::class);
            $parsedData = $n8nService->parseResumePdf($fullPath);

            // Merge with empty structure to ensure all fields exist
            $parsedData = array_merge($this->getEmptyStructure(), $parsedData);

            Profile::updateOrCreate(
                ['user_id' => $this->userId],
                ['data' => $parsedData]
            );

            // Optionally delete the file after processing
            // Storage::disk('public')->delete($this->filePath);

        } catch (\Exception $e) {
            Log::error('Resume parsing failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);

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
