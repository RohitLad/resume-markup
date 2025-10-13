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
   * Generate ATS-optimized resume markdown from profile data
   */
  public function generateResumeMarkdown(array $profileData, string $jobTitle, string $jobDescription): string
  {
    $apiKey = config('gemini.api_key');
    if (!$apiKey) {
      throw new \Exception('Gemini API key not configured');
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/" . config('gemini.model') . ":generateContent?key={$apiKey}";

    $prompt = $this->getResumeGenerationPrompt($profileData, $jobTitle, $jobDescription);

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
        'temperature' => 0.3, // Lower temperature for more consistent, professional output
        'maxOutputTokens' => 8192,
      ]
    ];

    $response = Http::timeout(config('gemini.timeout', 60)) // Increased timeout for complex generation
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

  /**
   * Build comprehensive resume generation prompt
   */
  private function getResumeGenerationPrompt(array $profileData, string $jobTitle, string $jobDescription): string
  {
    return "You are an expert resume writer and ATS (Applicant Tracking System) optimization specialist. Your task is to create a highly targeted, ATS-compatible resume in Markdown format that maximizes the candidate's chances of getting past automated screening systems and impressing human recruiters.

      ## TARGET POSITION
      **Job Title:** {$jobTitle}
      **Job Description:** {$jobDescription}

      ## CANDIDATE PROFILE DATA
      " . json_encode($profileData, JSON_PRETTY_PRINT) . "

      ## CRITICAL REQUIREMENTS

      ### 1. ATS COMPATIBILITY (HIGHEST PRIORITY)
      - Use standard section headers: Contact Information, Professional Summary, Work Experience, Education, Skills
      - Avoid tables, columns, graphics, or complex formatting
      - Use plain text only - no special characters or symbols
      - Include exact keywords from the job description naturally
      - Spell out acronyms on first use
      - Use standard date formats (MM/YYYY)

      ### 2. KEYWORD OPTIMIZATION
      - Extract and incorporate key skills, technologies, and qualifications from the job description
      - Use industry-standard terminology
      - Include both technical and soft skills mentioned in the job posting
      - Prioritize keywords that appear in the job description

      ### 3. EXPERIENCE TAILORING
      - Reorder work experience to prioritize roles most relevant to the target position
      - Customize job descriptions to highlight achievements matching job requirements
      - Quantify accomplishments with metrics (%, numbers, dollar amounts) where possible
      - Use action verbs that match the job's required responsibilities
      - Focus on transferable skills and relevant experience

      ### 4. SKILLS PRIORITIZATION
      - Group skills by category (Technical, Soft Skills, Tools, etc.)
      - Prioritize skills mentioned in the job description
      - Include proficiency levels where available
      - Remove irrelevant skills to keep resume focused

      ### 5. PROFESSIONAL FORMATTING
      - Use clear, scannable structure
      - Keep descriptions concise but impactful (2-4 lines per role)
      - Use consistent formatting throughout
      - Ensure contact information is complete and professional

      ## OUTPUT FORMAT
      Return ONLY the resume in Markdown format. No explanations, no additional text.

      Example structure:
      # [Full Name]
      [Phone] | [Email] | [Location] | [LinkedIn/Portfolio]

      ## Professional Summary
      [2-3 sentence summary tailored to the job]

      ## Work Experience
      ### [Job Title], [Company Name] - [City, State]
      [MM/YYYY] - [MM/YYYY]
      - [Achievement with metrics]
      - [Relevant responsibility]
      - [Technical skill demonstration]

      ## Education
      ### [Degree], [Field of Study]
      [School Name] - [City, State]
      [MM/YYYY]

      ## Skills
      - **Technical:** [skill1], [skill2], [skill3]
      - **Tools & Software:** [tool1], [tool2]
      - **Soft Skills:** [skill1], [skill2]

      ## OUTPUT INSTRUCTIONS
      - Generate a complete, professional resume
      - Ensure all sections are populated with relevant information
      - Make the content compelling and achievement-oriented
      - Optimize for both ATS parsing and human readability
      - Keep total length to 1 page worth of content (approximately 600-800 words)";
  }

  public function getParsePDFPrompt()
  {
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