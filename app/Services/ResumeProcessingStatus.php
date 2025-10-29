<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ResumeProcessingStatus
{
    const PARSING_PREFIX = 'resume_parsing:';

    const GENERATION_PREFIX = 'resume_generation:';

    const KNOWLEDGE_BASE_PREFIX = 'knowledge_base_generation:';

    const TTL_MINUTES = 30;

    /**
     * Start resume parsing for a user
     */
    public static function startParsing(int $userId): void
    {
        Cache::put(self::PARSING_PREFIX.$userId, [
            'started_at' => now(),
            'type' => 'parsing',
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * Check if resume parsing is active for a user
     */
    public static function isParsingActive(int $userId): bool
    {
        return Cache::has(self::PARSING_PREFIX.$userId);
    }

    /**
     * Finish resume parsing for a user
     */
    public static function finishParsing(int $userId): void
    {
        Cache::forget(self::PARSING_PREFIX.$userId);
    }

    /**
     * Start resume generation for a specific resume
     */
    public static function startGeneration(string $resumeId): void
    {
        Cache::put(self::GENERATION_PREFIX.$resumeId, [
            'started_at' => now(),
            'type' => 'generation',
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * Check if resume generation is active for a specific resume
     */
    public static function isGenerationActive(string $resumeId): bool
    {
        return Cache::has(self::GENERATION_PREFIX.$resumeId);
    }

    /**
     * Finish resume generation for a specific resume
     */
    public static function finishGeneration(string $resumeId): void
    {
        Cache::forget(self::GENERATION_PREFIX.$resumeId);
    }

    /**
     * Start knowledge base generation for a user
     */
    public static function startKnowledgeBaseGeneration(int $userId): void
    {
        Cache::put(self::KNOWLEDGE_BASE_PREFIX.$userId, [
            'started_at' => now(),
            'type' => 'knowledge_base_generation',
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * Check if knowledge base generation is active for a user
     */
    public static function isKnowledgeBaseGenerationActive(int $userId): bool
    {
        return Cache::has(self::KNOWLEDGE_BASE_PREFIX.$userId);
    }

    /**
     * Finish knowledge base generation for a user
     */
    public static function finishKnowledgeBaseGeneration(int $userId): void
    {
        Cache::forget(self::KNOWLEDGE_BASE_PREFIX.$userId);
    }
}
