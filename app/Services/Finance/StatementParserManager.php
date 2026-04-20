<?php

namespace App\Services\Finance;

use App\Services\Finance\Parsers\StatementParserInterface;
use App\Services\Finance\Parsers\NuveiStatementParser;
use App\Services\Finance\Parsers\NationalProcessingStatementParser;
use App\Services\Finance\Parsers\KurvStatementParser;
use App\Services\Finance\Parsers\MerchantEStatementParser;
use App\Services\Finance\Parsers\NexioStatementParser;
use App\Services\Finance\Parsers\NeteviaStatementParser;

/**
 * Routes statement text to the correct processor-specific parser.
 */
class StatementParserManager
{
    private static array $parsers = [
        'nuvei' => NuveiStatementParser::class,
        'national_processing' => NationalProcessingStatementParser::class,
        'kurv' => KurvStatementParser::class,
        'merchante' => MerchantEStatementParser::class,
        'nexio' => NexioStatementParser::class,
        'netevia' => NeteviaStatementParser::class,
    ];

    /**
     * Resolve and return the correct parser for a processor slug.
     */
    public static function resolve(?string $slug): StatementParserInterface
    {
        $class = self::$parsers[$slug] ?? NuveiStatementParser::class;
        return new $class();
    }

    /**
     * Register a custom parser at runtime.
     */
    public static function register(string $slug, string $parserClass): void
    {
        self::$parsers[$slug] = $parserClass;
    }

    /**
     * Get all registered parser slugs.
     */
    public static function slugs(): array
    {
        return array_keys(self::$parsers);
    }

    /**
     * Full pipeline: detect processor → route to parser → return parsed data.
     */
    public static function parseStatement(string $rawText, int $statementId, ?string $overrideSlug = null): array
    {
        // Detect processor if not overridden
        if ($overrideSlug) {
            $detection = [
                'slug' => $overrideSlug,
                'name' => ucwords(str_replace('_', ' ', $overrideSlug)),
                'processor_id' => null,
                'confidence' => 100,
            ];
        } else {
            $detection = ProcessorDetectionService::detect($rawText);
        }

        $parser = self::resolve($detection['slug']);
        $parsed = $parser->parse($rawText, $statementId);

        // Inject detection metadata
        $parsed['_meta'] = [
            'parser_slug' => $parser->slug(),
            'parser_version' => $parser->version(),
            'detected_processor' => $detection['name'],
            'detection_confidence' => $detection['confidence'],
            'processor_id' => $detection['processor_id'],
            'parse_confidence' => $parsed['confidence'] ?? 50,
        ];

        return $parsed;
    }
}
