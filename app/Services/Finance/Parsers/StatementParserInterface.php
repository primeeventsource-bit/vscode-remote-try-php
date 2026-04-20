<?php

namespace App\Services\Finance\Parsers;

/**
 * Contract for all processor-specific statement parsers.
 */
interface StatementParserInterface
{
    /**
     * Parse raw extracted text into a structured normalized array.
     *
     * @param string $rawText The full extracted text from the PDF
     * @param int $statementId The statement ID for logging
     * @return array Normalized parsed data structure
     */
    public function parse(string $rawText, int $statementId): array;

    /**
     * Return the parser version string.
     */
    public function version(): string;

    /**
     * Return the processor slug this parser handles.
     */
    public function slug(): string;
}
