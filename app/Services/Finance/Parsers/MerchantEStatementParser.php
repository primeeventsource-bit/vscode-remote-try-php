<?php

namespace App\Services\Finance\Parsers;

/**
 * MerchantE Solutions statement parser.
 */
class MerchantEStatementParser extends KurvStatementParser
{
    public function version(): string { return '2.0.0'; }
    public function slug(): string { return 'merchante'; }

    protected function parseHeader(string $text): array
    {
        $header = $this->emptyResult()['header'];
        $header['processor_name'] = 'MerchantE Solutions';

        if (preg_match('/(?:Merchant|DBA|Business)\s*[:\-]?\s*(.+)/i', $text, $m)) {
            $header['merchant_name'] = trim($m[1]);
        }
        if (preg_match('/(?:Merchant\s*(?:#|No|Number)|MID|MES\s*ID)\s*[:\-]?\s*([\d\-\*]+)/i', $text, $m)) {
            $header['merchant_number'] = trim($m[1]);
        }
        $header['statement_month'] = $this->parseStatementMonth($text);
        $header['routing_last4'] = $this->extractLast4($text, 'Routing');
        $header['deposit_account_last4'] = $this->extractLast4($text, 'Account') ?? $this->extractLast4($text, 'DDA');

        $this->log('info', 'header', 'Header parsed', $header);
        return $header;
    }
}
