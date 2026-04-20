<?php

namespace App\Services\Finance;

use App\Models\Processor;

/**
 * Detects which processor a merchant statement belongs to
 * by scanning extracted text for known patterns.
 */
class ProcessorDetectionService
{
    /**
     * Known processor signature patterns.
     * Each key is a parser_slug; values are arrays of regex patterns.
     */
    private const SIGNATURES = [
        'nuvei' => [
            '/nuvei/i',
            '/pivotal\s*payments/i',
            '/safe[- ]?charge/i',
        ],
        'national_processing' => [
            '/national\s*processing/i',
            '/npc\s/i',
            '/national\s*bankcard/i',
        ],
        'kurv' => [
            '/kurv/i',
            '/kurv\s*payment/i',
        ],
        'merchante' => [
            '/merchant\s*e\s*solutions/i',
            '/merchant\s*e/i',
            '/mes\s/i',
        ],
        'nexio' => [
            '/nexio/i',
            '/cmg\s*technologies/i',
        ],
        'netevia' => [
            '/netevia/i',
            '/net\s*evia/i',
        ],
    ];

    /**
     * Detect the processor from raw statement text.
     *
     * @return array{slug: string|null, name: string|null, processor_id: int|null, confidence: int}
     */
    public static function detect(string $rawText): array
    {
        $scores = [];

        foreach (self::SIGNATURES as $slug => $patterns) {
            $matchCount = 0;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $rawText)) {
                    $matchCount++;
                }
            }
            if ($matchCount > 0) {
                $scores[$slug] = $matchCount;
            }
        }

        // Also check DB-stored custom patterns from processors table
        try {
            $dbProcessors = Processor::whereNotNull('detection_patterns')->where('active', true)->get();
            foreach ($dbProcessors as $proc) {
                $patterns = $proc->detection_patterns;
                if (!is_array($patterns)) continue;
                $matchCount = 0;
                foreach ($patterns as $pattern) {
                    if (preg_match('/' . preg_quote($pattern, '/') . '/i', $rawText)) {
                        $matchCount++;
                    }
                }
                if ($matchCount > 0) {
                    $key = $proc->parser_slug ?? 'db_' . $proc->id;
                    $scores[$key] = ($scores[$key] ?? 0) + $matchCount;
                }
            }
        } catch (\Throwable $e) {
            // DB may not be available during tests
        }

        if (empty($scores)) {
            return ['slug' => null, 'name' => null, 'processor_id' => null, 'confidence' => 0];
        }

        arsort($scores);
        $bestSlug = array_key_first($scores);
        $bestScore = $scores[$bestSlug];

        // Confidence: 1 match = 50, 2 = 75, 3+ = 95
        $confidence = min(95, 25 + ($bestScore * 25));

        // Look up processor in DB
        $processor = Processor::where('parser_slug', $bestSlug)
            ->orWhere('name', 'LIKE', '%' . str_replace('_', ' ', $bestSlug) . '%')
            ->first();

        return [
            'slug' => $bestSlug,
            'name' => $processor?->name ?? ucwords(str_replace('_', ' ', $bestSlug)),
            'processor_id' => $processor?->id,
            'confidence' => $confidence,
        ];
    }
}
