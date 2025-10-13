<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AIService
{
    /**
     * Parse a resume PDF using Gemini API
     */
    public function parseResumePdf(string $filePath): array
    {
        $apiKey = config('gemini.api_key');
        if (!$apiKey) {
            throw new \Exception('Gemini API key not configured');
        }

        // Read the PDF file
        $fileContent = file_get_contents($filePath);
        $base64File = base64_encode($fileContent);

        // Gemini API endpoint
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . config('gemini.model') . ":generateContent?key={$apiKey}";

        $prompt = $this->getParsePDFPrompt();

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ],
                        [
                            'inline_data' => [
                                'mime_type' => 'application/pdf',
                                'data' => $base64File
                            ]
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 4096,
            ]
        ];

        $response = Http::timeout(config('gemini.timeout', 30))
            ->post($url, $payload);

        if (!$response->successful()) {
            throw new \Exception('Gemini API error: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Invalid response from Gemini API');
        }

        $content = $data['candidates'][0]['content']['parts'][0]['text'];

        // Parse JSON - handle both direct JSON and markdown-wrapped JSON
        $parsedData = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract JSON from markdown code blocks
            if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $content, $matches)) {
                $parsedData = json_decode($matches[1], true);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from Gemini: ' . $content);
            }
        }

        return $parsedData;
    }

    /**
     * Generate resume markdown from profile data
     */
    public function generateResumeMarkdown(array $profileData, string $jobTitle, string $jobDescription): string
    {
        $apiKey = config('gemini.api_key');
        if (!$apiKey) {
            throw new \Exception('Gemini API key not configured');
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . config('gemini.model') . ":generateContent?key={$apiKey}";

        $prompt = "Generate a professional resume in Markdown format based on the following profile data. Tailor it for the job title: {$jobTitle}. Job description: {$jobDescription}

Profile Data:
" . json_encode($profileData, JSON_PRETTY_PRINT) . "

Please create a well-formatted Markdown resume that highlights relevant experience and skills for this position. Include sections for contact information, summary, experience, education, and skills.";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 8192,
            ]
        ];

        $response = Http::timeout(config('gemini.timeout', 30))
            ->post($url, $payload);

        if (!$response->successful()) {
            throw new \Exception('Gemini API error: ' . $response->body());
        }

        $data = $response->json();

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new \Exception('Invalid response from Gemini API');
        }

        return $data['candidates'][0]['content']['parts'][0]['text'];
    }

    public function getParsePDFPrompt(){
        return 
        
            'You are an expert resume parser. Your task is to analyze resume PDF files and extract structured information in the JSON Resume format (https://jsonresume.org/schema/). This is a standardized format for resumes.

            When given a resume PDF, you should:
            
            1. Read and analyze the entire document
            2. Extract all relevant information sections
            3. Map the information to the JSON Resume schema
            4. Format the data as valid JSON
            5. Return ONLY the JSON - no additional text, explanations, or formatting
            
            Required JSON Resume structure:
            {
              "basics": {
                "name": "string",
                "label": "string (professional title/role)",
                "image": "string (leave empty if not available)",
                "email": "string",
                "phone": "string",
                "url": "string (personal website if available)",
                "summary": "string (professional summary)",
                "location": {
                  "address": "string (leave empty if not detailed)",
                  "postalCode": "string",
                  "city": "string",
                  "countryCode": "string (2-letter code)",
                  "region": "string (state/province)"
                },
                "profiles": [
                  {
                    "network": "string (LinkedIn, Twitter, GitHub, etc.)",
                    "username": "string",
                    "url": "string"
                  }
                ]
              },
              "work": [
                {
                  "name": "string (company name)",
                  "position": "string (job title)",
                  "url": "string (company website if available)",
                  "startDate": "string (YYYY-MM-DD or YYYY-MM)",
                  "endDate": "string (YYYY-MM-DD, YYYY-MM, or \"Present\")",
                  "summary": "string (job description)",
                  "highlights": ["string (key achievements)"]
                }
              ],
              "volunteer": [
                {
                  "organization": "string",
                  "position": "string",
                  "url": "string",
                  "startDate": "string",
                  "endDate": "string",
                  "summary": "string",
                  "highlights": ["string"]
                }
              ],
              "education": [
                {
                  "institution": "string (school/university name)",
                  "url": "string (school website if available)",
                  "area": "string (field of study)",
                  "studyType": "string (degree type: Bachelor, Master, etc.)",
                  "startDate": "string (YYYY-MM-DD or YYYY-MM)",
                  "endDate": "string (YYYY-MM-DD or YYYY-MM)",
                  "score": "string (GPA if available)",
                  "courses": ["string (relevant courses if mentioned)"]
                }
              ],
              "awards": [
                {
                  "title": "string",
                  "date": "string (YYYY-MM-DD)",
                  "awarder": "string (organization)",
                  "summary": "string"
                }
              ],
              "certificates": [
                {
                  "name": "string",
                  "date": "string (YYYY-MM-DD)",
                  "issuer": "string",
                  "url": "string"
                }
              ],
              "publications": [
                {
                  "name": "string",
                  "publisher": "string",
                  "releaseDate": "string (YYYY-MM-DD)",
                  "url": "string",
                  "summary": "string"
                }
              ],
              "skills": [
                {
                  "name": "string (skill category)",
                  "level": "string (Beginner, Intermediate, Advanced, Master, Expert)",
                  "keywords": ["string (specific skills)"]
                }
              ],
              "languages": [
                {
                  "language": "string",
                  "fluency": "string (Native, Fluent, Professional, Conversational, Basic)"
                }
              ],
              "interests": [
                {
                  "name": "string",
                  "keywords": ["string"]
                }
              ],
              "references": [
                {
                  "name": "string",
                  "reference": "string"
                }
              ],
              "projects": [
                {
                  "name": "string",
                  "startDate": "string (YYYY-MM-DD)",
                  "endDate": "string (YYYY-MM-DD)",
                  "description": "string",
                  "highlights": ["string"],
                  "url": "string"
                }
              ]
            }
            
            Guidelines:
            - Extract information exactly as it appears in the resume
            - Use empty strings or omit optional fields if information is not available
            - Format dates consistently (prefer YYYY-MM-DD when possible)
            - Group related skills into categories with appropriate levels
            - Include all social media profiles found (LinkedIn, Twitter, GitHub, etc.)
            - Map certifications to the "certificates" array
            - Use "Present" for current positions
            - Return valid JSON only - no markdown, no code blocks, no explanations
            ';
    }
}