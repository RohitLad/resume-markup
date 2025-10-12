<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OpenAIService
{
    /**
     * Parse a resume PDF using OpenAI GPT-4o
     */
    public function parseResumePdf(string $filePath): array
    {
        // For PDFs, we need to use the Assistants API with file upload
        // First, upload the file
        $fileResponse = OpenAI::files()->upload([
            'purpose' => 'assistants',
            'file' => fopen($filePath, 'r'),
        ]);

        $fileId = $fileResponse->id;

        try {
            // Create a thread
            $thread = OpenAI::threads()->create([]);

            // Add a message with the file
            OpenAI::threads()->messages()->create($thread->id, [
                'role' => 'user',
                'content' => 'Please analyze this resume PDF and extract the following information in JSON format:

- Personal Information (name, email, phone, location)
- Professional Summary
- Work Experience (array of jobs with company, position, dates, description)
- Education (array of degrees with institution, degree, dates)
- Skills (array of technical and soft skills)
- Certifications (array if any)
- Languages (array if mentioned)

Return only valid JSON without any additional text or formatting.',
                'attachments' => [
                    [
                        'file_id' => $fileId,
                        'tools' => [['type' => 'file_search']]
                    ]
                ]
            ]);

            // Run with GPT-4o
            $run = OpenAI::threads()->runs()->create($thread->id, [
                'assistant_id' => config('services.openai.assistant_id'),
                'model' => 'gpt-4o',
            ]);

            // Poll for completion
            $maxAttempts = 30;
            $attempts = 0;

            do {
                sleep(1);
                $runStatus = OpenAI::threads()->runs()->retrieve($thread->id, $run->id);
                $attempts++;
            } while ($runStatus->status !== 'completed' && $attempts < $maxAttempts);

            if ($runStatus->status !== 'completed') {
                throw new \Exception('OpenAI processing timed out');
            }

            // Get the response
            $messages = OpenAI::threads()->messages()->list($thread->id);
            $lastMessage = $messages->data[0];

            $content = '';
            foreach ($lastMessage->content as $contentPart) {
                if ($contentPart->type === 'text') {
                    $content .= $contentPart->text->value;
                }
            }

            // Parse JSON - handle both direct JSON and markdown-wrapped JSON
            $parsedData = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Try to extract JSON from markdown code blocks
                if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $content, $matches)) {
                    $parsedData = json_decode($matches[1], true);
                }

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON response from OpenAI: ' . $content);
                }
            }

            return $parsedData;

        } finally {
            // Clean up
            try {
                OpenAI::files()->delete($fileId);
            } catch (\Exception $e) {
                \Log::warning('Failed to delete OpenAI file: ' . $e->getMessage());
            }
        }
    }

    /**
     * Alternative method using chat completions with vision (if PDF is converted to images)
     */
    public function parseResumeWithVision(string $imagePath): array
    {
        $imageData = base64_encode(file_get_contents($imagePath));

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an expert resume parser. Extract structured information from resume images and return it in a clean JSON format.'
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Please analyze this resume image and extract the following information in JSON format:

- Personal Information (name, email, phone, location)
- Professional Summary
- Work Experience (array of jobs with company, position, dates, description)
- Education (array of degrees with institution, degree, dates)
- Skills (array of technical and soft skills)
- Certifications (array if any)
- Languages (array if mentioned)

Return only valid JSON without any additional text or formatting.'
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:image/jpeg;base64,{$imageData}"
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 2000,
        ]);

        $content = $response->choices[0]->message->content;
        $parsedData = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON response from OpenAI: ' . $content);
        }

        return $parsedData;
    }
}