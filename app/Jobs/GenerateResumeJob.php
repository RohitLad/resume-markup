<?php

namespace App\Jobs;

use App\Models\Resume;
use App\Services\N8NService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateResumeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Resume $resume
    ) {}

    public function handle(N8nService $n8nService): void
    {
        try {
            $user = $this->resume->user;
            $profile = $user->profile;

            if (! $profile || ! $profile->data) {
                \Log::warning('Resume generation failed: No profile data found', [
                    'resume_id' => $this->resume->id,
                    'user_id' => $user->id,
                ]);

                return; // No profile data to generate from
            }

            $profileData = $profile->data;

            $markdown = $n8nService->generateResumeMarkdown(
                $profileData,
                $this->resume->job_title,
                $this->resume->job_description
            );

            $this->resume->update(['content' => $markdown]);

            \Log::info('Resume generated successfully', [
                'resume_id' => $this->resume->id,
                'user_id' => $user->id,
                'job_title' => $this->resume->job_title,
            ]);

        } catch (\Exception $e) {
            \Log::error('Resume generation failed', [
                'resume_id' => $this->resume->id,
                'user_id' => $this->resume->user_id,
                'job_title' => $this->resume->job_title,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update resume with error status for user visibility
            $this->resume->update([
                'content' => 'Error generating resume: '.$e->getMessage(),
            ]);

            // Re-throw to mark job as failed
            throw $e;
        }
    }
}
