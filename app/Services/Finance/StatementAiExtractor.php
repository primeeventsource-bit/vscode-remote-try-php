<?php

namespace App\Services\Finance;

/**
 * AI-powered statement data extractor.
 * Sends statement text to OpenAI and returns structured line items:
 * transactions, chargebacks, fees, reserves, payouts, and summary totals.
 *
 * Used when deterministic parsing gets low confidence or misses data.
 */
class StatementAiExtractor
{
    /**
     * Extract all structured data from statement text via AI.
     */
    public static function extract(string $content, string $filename, array $detection): array
    {
        $apiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY');
        if (!$apiKey) return self::empty('No OpenAI API key configured');

        // Redact sensitive card data before sending
        $safe = self::redactSensitiveData($content);

        // Limit to ~6000 chars to control cost (gpt-4o-mini is cheap but be reasonable)
        $excerpt = substr($safe, 0, 6000);

        $prompt = self::buildPrompt($excerpt, $filename, $detection);

        try {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'You are a merchant statement parser. You extract structured financial data from processor statements. Always return valid JSON. Never fabricate data — use null for fields you cannot find.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.05,
                    'max_tokens' => 4000,
                ]),
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                return self::empty('AI API returned HTTP ' . $httpCode);
            }

            $data = json_decode($response, true);
            $text = $data['choices'][0]['message']['content'] ?? '';

            return self::parseAiResponse($text);
        } catch (\Throwable $e) {
            report($e);
            return self::empty('AI extraction failed: ' . $e->getMessage());
        }
    }

    private static function buildPrompt(string $content, string $filename, array $detection): string
    {
        $processorHint = $detection['processor'] ?? 'unknown';
        $midHint = $detection['mid_number'] ?? 'unknown';

        return <<<PROMPT
Parse this merchant processing statement and extract ALL financial data.

Filename: {$filename}
Detected processor: {$processorHint}
Detected MID: {$midHint}

Return a JSON object with this exact structure:
{
  "summary": {
    "gross_volume": <number or null>,
    "net_volume": <number or null>,
    "refunds": <number or null>,
    "chargebacks": <number or null>,
    "fees": <number or null>,
    "reserves": <number or null>,
    "payouts": <number or null>,
    "ending_balance": <number or null>,
    "start_date": "<YYYY-MM-DD or null>",
    "end_date": "<YYYY-MM-DD or null>"
  },
  "transactions": [
    {
      "date": "<YYYY-MM-DD>",
      "description": "<description>",
      "amount": <number>,
      "reference": "<transaction ID or null>",
      "card_brand": "<Visa/Mastercard/Amex/Discover or null>",
      "last4": "<last 4 digits or null>",
      "status": "<approved/declined/settled/refunded or null>",
      "type": "<sale/refund/void or null>"
    }
  ],
  "chargebacks": [
    {
      "date": "<YYYY-MM-DD>",
      "amount": <positive number>,
      "reason_code": "<reason code or null>",
      "reason_description": "<description>",
      "card_brand": "<brand or null>",
      "reference": "<chargeback ID or null>",
      "original_transaction_ref": "<original txn ref or null>",
      "due_date": "<YYYY-MM-DD or null>",
      "status": "<open/won/lost/pending or null>"
    }
  ],
  "fees": [
    {
      "date": "<YYYY-MM-DD or null>",
      "description": "<fee description>",
      "amount": <number>,
      "category": "<processing_fee/interchange_fee/monthly_fee/chargeback_fee/pci_fee/other>"
    }
  ],
  "reserves": [
    {
      "date": "<YYYY-MM-DD or null>",
      "description": "<description>",
      "amount": <number>,
      "type": "<reserve_hold/reserve_release>"
    }
  ],
  "payouts": [
    {
      "date": "<YYYY-MM-DD or null>",
      "description": "<description>",
      "amount": <number>,
      "reference": "<deposit/payout ID or null>"
    }
  ]
}

IMPORTANT RULES:
- Extract EVERY transaction, chargeback, fee, reserve, and payout you can find
- Chargeback amounts must be POSITIVE numbers
- Fee amounts should be NEGATIVE (they are deductions)
- For chargebacks, extract the reason code if visible (e.g. "10.4", "75", "4837")
- For chargebacks, extract the due/response date if visible
- If you see sections labeled "Chargebacks", "Disputes", "Retrievals" — those are chargebacks
- If you see "Reserve Hold", "Holdback" — those are reserve_hold entries
- If you see "Reserve Release" — those are reserve_release entries
- If you see "Deposit", "Funding", "ACH", "Payout" — those are payouts
- Do NOT guess or fabricate data. Use null for anything you can't find.
- Return ONLY the JSON object, no markdown formatting.

Statement text:
{$content}
PROMPT;
    }

    private static function parseAiResponse(string $text): array
    {
        // Strip markdown code block wrappers if present
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);

        $parsed = json_decode($text, true);
        if (!is_array($parsed)) {
            // Try to find JSON within the text
            if (preg_match('/\{[\s\S]*\}/s', $text, $m)) {
                $parsed = json_decode($m[0], true);
            }
        }

        if (!is_array($parsed)) {
            return self::empty('AI response was not valid JSON');
        }

        // Convert to internal line format
        $lines = [];

        // Transactions
        foreach ($parsed['transactions'] ?? [] as $txn) {
            $lines[] = [
                'type' => ($txn['type'] ?? '') === 'refund' ? 'refund' : 'transaction',
                'date' => $txn['date'] ?? null,
                'description' => $txn['description'] ?? null,
                'amount' => (float) ($txn['amount'] ?? 0),
                'reference' => $txn['reference'] ?? null,
                'card_brand' => $txn['card_brand'] ?? null,
                'last4' => $txn['last4'] ?? null,
                'status' => $txn['status'] ?? 'approved',
                'currency' => 'USD',
                'confidence' => 0.80,
                'raw' => $txn,
            ];
        }

        // Chargebacks
        foreach ($parsed['chargebacks'] ?? [] as $cb) {
            $lines[] = [
                'type' => 'chargeback',
                'date' => $cb['date'] ?? null,
                'description' => $cb['reason_description'] ?? ('Chargeback ' . ($cb['reason_code'] ?? '')),
                'amount' => abs((float) ($cb['amount'] ?? 0)),
                'reference' => $cb['reference'] ?? null,
                'card_brand' => $cb['card_brand'] ?? null,
                'reason_code' => $cb['reason_code'] ?? null,
                'reason_description' => $cb['reason_description'] ?? null,
                'original_transaction_ref' => $cb['original_transaction_ref'] ?? null,
                'due_date' => $cb['due_date'] ?? null,
                'status' => $cb['status'] ?? 'open',
                'currency' => 'USD',
                'confidence' => 0.80,
                'raw' => $cb,
            ];
        }

        // Fees
        foreach ($parsed['fees'] ?? [] as $fee) {
            $lines[] = [
                'type' => 'fee',
                'date' => $fee['date'] ?? null,
                'description' => $fee['description'] ?? 'Fee',
                'amount' => (float) ($fee['amount'] ?? 0),
                'reference' => null,
                'category' => $fee['category'] ?? 'processing_fee',
                'currency' => 'USD',
                'confidence' => 0.80,
                'raw' => $fee,
            ];
        }

        // Reserves
        foreach ($parsed['reserves'] ?? [] as $res) {
            $lines[] = [
                'type' => $res['type'] ?? 'reserve_hold',
                'date' => $res['date'] ?? null,
                'description' => $res['description'] ?? 'Reserve',
                'amount' => (float) ($res['amount'] ?? 0),
                'reference' => null,
                'currency' => 'USD',
                'confidence' => 0.80,
                'raw' => $res,
            ];
        }

        // Payouts
        foreach ($parsed['payouts'] ?? [] as $po) {
            $lines[] = [
                'type' => 'payout',
                'date' => $po['date'] ?? null,
                'description' => $po['description'] ?? 'Payout/Deposit',
                'amount' => (float) ($po['amount'] ?? 0),
                'reference' => $po['reference'] ?? null,
                'currency' => 'USD',
                'confidence' => 0.80,
                'raw' => $po,
            ];
        }

        return [
            'summary' => $parsed['summary'] ?? null,
            'lines' => $lines,
            'ai_source' => true,
        ];
    }

    private static function redactSensitiveData(string $content): string
    {
        // Redact full card numbers (13-19 digits)
        $content = preg_replace('/\b(\d{4})\d{5,11}(\d{4})\b/', '$1****$2', $content);
        // Redact SSN patterns
        $content = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/', '***-**-****', $content);
        // Redact CVV-like 3-4 digit standalone numbers near card keywords
        $content = preg_replace('/(?:cvv|cvc|cv2|security\s*code)\s*[:\-]?\s*\d{3,4}/i', 'CVV: ***', $content);
        return $content;
    }

    private static function empty(string $note): array
    {
        return ['summary' => null, 'lines' => [], 'ai_note' => $note, 'ai_source' => true];
    }
}
