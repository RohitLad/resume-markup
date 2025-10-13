<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class N8NService
{
    private const DEFAULT_TIMEOUT = 120;
    private const CONNECTION_TEST_TIMEOUT = 10;

    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => self::DEFAULT_TIMEOUT,
            'http_errors' => false, // We'll handle errors manually
        ]);
    }

    /**
     * Generate resume markdown using n8n workflow
     */
    public function generateResumeMarkdown(array $profileData, string $jobTitle, string $jobDescription): string
    {
        $fullUrl = $this->buildUrl('generate_resume', 'generate-resume');
        $startTime = microtime(true);

        Log::info('Sending request to n8n', [
            'url' => $fullUrl,
            'job_title' => $jobTitle,
            'profile_keys' => array_keys($profileData),
        ]);

        try {
            $response = $this->client->request('POST', $fullUrl, [
                'json' => [
                    'job_title' => $jobTitle,
                    'job_description' => $jobDescription,
                    'profile' => $profileData,
                ],
                'headers' => $this->getHeaders(),
            ]);

            $responseTime = microtime(true) - $startTime;
            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                $body = $response->getBody()->getContents();
                Log::error('n8n request failed', [
                    'status' => $statusCode,
                    'body' => $body,
                    'url' => $fullUrl,
                ]);
                throw new \Exception("n8n workflow failed with status {$statusCode}: {$body}");
            }

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            $content = $this->extractContent($data, ['resume', 'content', 'markdown']);

            if (empty($content)) {
                throw new \Exception('Empty resume content received from n8n workflow');
            }

            Log::info('Successfully received resume from n8n', [
                'content_length' => strlen($content),
                'response_time_seconds' => round($responseTime, 2),
            ]);

            return $content;

        } catch (GuzzleException $e) {
            $this->logError('n8n resume generation failed', $e, $fullUrl, microtime(true) - $startTime, [
                'job_title' => $jobTitle,
            ]);
            throw new \Exception('Failed to generate resume: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse resume PDF using n8n workflow
     */
    public function parseResumePdf(string $filePath): array
    {
        $this->validateFile($filePath);

        $fullUrl = $this->buildUrl('parse_resume', 'parse-resume');
        $fileContent = file_get_contents($filePath);
        $fileSize = strlen($fileContent);

        Log::info('Sending PDF to n8n for parsing', [
            'url' => $fullUrl,
            'file_size' => $fileSize,
            'file_size_mb' => round($fileSize / 1024 / 1024, 2),
            'filename' => basename($filePath)
        ]);

        try {
            $headers = array_merge($this->getHeaders(), [
                'content-type' => 'application/pdf',
            ]);

            $response = $this->client->request('POST', $fullUrl, [
                'body' => $fileContent,
                'headers' => $headers,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                $body = $response->getBody()->getContents();
                Log::error('n8n PDF parsing failed', [
                    'status' => $statusCode,
                    'body' => $body,
                    'url' => $fullUrl
                ]);
                throw new \Exception("n8n workflow failed with status {$statusCode}: {$body}");
            }

            $rawBody = $response->getBody()->getContents();
            
            Log::info('n8n response received', [
                'status' => $statusCode,
                'body_length' => strlen($rawBody),
                'body_preview' => substr($rawBody, 0, 200),
                'content_type' => $response->getHeaderLine('Content-Type')
            ]);

            if (empty($rawBody)) {
                throw new \Exception('Empty response from n8n workflow');
            }

            $data = $this->parseJsonResponse($rawBody);
            $parsedData = $this->extractParsedData($data);

            Log::info('Successfully parsed resume from n8n', [
                'fields_parsed' => count($parsedData),
                'fields' => array_keys($parsedData)
            ]);

            return $parsedData;

        } catch (GuzzleException $e) {
            $this->logError('n8n PDF parsing failed', $e, $fullUrl, 0, [
                'file_path' => $filePath,
                'file_size' => $fileSize
            ]);
            throw new \Exception('Failed to parse resume PDF: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Test the n8n connection
     */
    public function testConnection(): bool
    {
        try {
            $n8nUrl = config('services.n8n.url');
            
            if (!$n8nUrl) {
                Log::warning('N8N URL not configured');
                return false;
            }

            $client = new Client(['timeout' => self::CONNECTION_TEST_TIMEOUT]);
            $response = $client->request('GET', $n8nUrl, ['http_errors' => false]);

            $isSuccessful = $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
            
            Log::info('N8N connection test', [
                'success' => $isSuccessful,
                'status' => $response->getStatusCode()
            ]);

            return $isSuccessful;

        } catch (\Exception $e) {
            Log::error('n8n connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Build the full URL for an n8n endpoint
     */
    private function buildUrl(string $configKey, string $defaultEndpoint): string
    {
        $n8nUrl = config('services.n8n.url');
        
        if (!$n8nUrl) {
            throw new \Exception('N8N URL not configured in services.n8n.url');
        }

        $endpoint = config("services.n8n.endpoints.{$configKey}", $defaultEndpoint);
        
        return rtrim($n8nUrl, '/') . '/' . ltrim($endpoint, '/');
    }

    /**
     * Get headers including API key if configured
     */
    private function getHeaders(): array
    {
        $headers = [];
        
        $apiKey = config('services.n8n.api_key');
        
        if ($apiKey) {
            $headers['API_KEY'] = $apiKey;
        }

        return $headers;
    }

    /**
     * Extract content from response data using multiple possible keys
     */
    private function extractContent(array $data, array $possibleKeys): ?string
    {
        foreach ($possibleKeys as $key) {
            if (isset($data[$key]) && !empty($data[$key])) {
                return $data[$key];
            }
        }

        Log::error('Invalid n8n response format - missing expected content keys', [
            'expected_keys' => $possibleKeys,
            'actual_keys' => array_keys($data)
        ]);
        
        throw new \Exception('Invalid response from n8n workflow - missing content in keys: ' . implode(', ', $possibleKeys));
    }

    /**
     * Parse JSON response with error handling
     */
    private function parseJsonResponse(string $rawBody): array
    {
        Log::debug('n8n raw response', ['body_preview' => substr($rawBody, 0, 500)]);

        $data = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Failed to parse JSON response', [
                'raw_body_preview' => substr($rawBody, 0, 200),
                'json_error' => json_last_error_msg()
            ]);
            throw new \Exception('Invalid JSON response from n8n workflow: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Extract parsed data from n8n response
     */
    private function extractParsedData(array $data): array
    {
        // The response may be wrapped in 'data' or 'content' or be direct
        $parsedData = $data['data'] ?? $data['content'] ?? $data;

        if (!is_array($parsedData)) {
            Log::error('Invalid response format - expected array', [
                'data_type' => gettype($parsedData),
                'full_response_keys' => array_keys($data)
            ]);
            throw new \Exception('Invalid response format from n8n workflow - expected array, got ' . gettype($parsedData));
        }

        return $parsedData;
    }

    /**
     * Validate file exists and is readable
     */
    private function validateFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Resume file not found: ' . $filePath);
        }

        if (!is_readable($filePath)) {
            throw new \Exception('Resume file is not readable: ' . $filePath);
        }
    }

    /**
     * Centralized error logging
     */
    private function logError(string $message, \Exception $exception, string $url, float $responseTime = 0, array $context = []): void
    {
        Log::error($message, array_merge([
            'error' => $exception->getMessage(),
            'url' => $url,
            'response_time_seconds' => round($responseTime, 2),
            'exception_class' => get_class($exception),
        ], $context));
    }
}