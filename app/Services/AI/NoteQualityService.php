<?php

namespace App\Services\AI;

/**
 * Scores CRM note quality and provides improvement suggestions.
 * Rule-based — works without OpenAI.
 */
class NoteQualityService
{
    private static array $qualitySignals = [
        'positive' => [
            'interested', 'wants to', 'agreed', 'confirmed', 'spouse', 'wife', 'husband',
            'owns', 'owes', 'maintenance', 'fees', 'timeshare', 'resort', 'weeks',
            'bedroom', 'points', 'annual', 'biennial', 'mortgage', 'paid off',
            'ready', 'willing', 'follow up', 'call back', 'email sent',
            'objection', 'concern', 'question', 'budget', 'timeline',
        ],
        'vague' => [
            'called', 'spoke', 'talked', 'interested', 'will call', 'good lead', 'nice',
            'ok', 'maybe', 'possibly', 'idk', 'n/a', 'tbd', 'pending', 'nothing',
        ],
    ];

    public static function score(string $noteText): array
    {
        $text = strtolower(trim($noteText));
        if (strlen($text) === 0) {
            return ['score' => 0, 'label' => 'empty', 'feedback' => ['Note is empty. Every lead must have notes.']];
        }

        $wordCount = str_word_count($text);
        $feedback = [];
        $score = 0;

        // Length scoring (0-30 points)
        if ($wordCount >= 30) $score += 30;
        elseif ($wordCount >= 15) $score += 20;
        elseif ($wordCount >= 8) $score += 10;
        else {
            $score += 5;
            $feedback[] = 'Note is very short. Add more detail about the conversation.';
        }

        // Positive signal scoring (0-40 points)
        $positiveHits = 0;
        foreach (self::$qualitySignals['positive'] as $signal) {
            if (str_contains($text, $signal)) $positiveHits++;
        }
        $signalScore = min(40, $positiveHits * 8);
        $score += $signalScore;

        if ($positiveHits === 0) {
            $feedback[] = 'Include specifics: what they own, interest level, objections, timeline.';
        }

        // Vagueness penalty (-5 per vague-only word if no positive signals)
        if ($positiveHits < 2) {
            $vagueHits = 0;
            foreach (self::$qualitySignals['vague'] as $v) {
                if ($text === $v || preg_match("/\b{$v}\b/", $text)) $vagueHits++;
            }
            if ($vagueHits > 0 && $positiveHits === 0) {
                $score = max(5, $score - ($vagueHits * 5));
                $feedback[] = 'Notes are too vague. Replace "called, interested" with actual details.';
            }
        }

        // Completeness bonus (0-30 points)
        $hasClientSaid = preg_match('/(said|told|mentioned|explained|asked|stated|wants|needs)/i', $text);
        $hasInterest = preg_match('/(interested|not interested|maybe|wants to|agreed|declined)/i', $text);
        $hasObjection = preg_match('/(objection|concern|worried|afraid|not sure|spouse|money|price|can\'t afford|think about)/i', $text);
        $hasNextStep = preg_match('/(follow up|call back|callback|send|email|transfer|schedule)/i', $text);

        if ($hasClientSaid) $score += 8;
        else $feedback[] = 'Add what the client said in their own words.';

        if ($hasInterest) $score += 8;
        else $feedback[] = 'State the client interest level (interested, not interested, undecided).';

        if ($hasObjection) $score += 7;
        if ($hasNextStep) $score += 7;
        else $feedback[] = 'Add a next step (follow-up date, action planned).';

        $score = min(100, max(0, $score));

        $label = match (true) {
            $score >= 75 => 'excellent',
            $score >= 50 => 'good',
            $score >= 25 => 'fair',
            default => 'poor',
        };

        if ($score >= 75 && empty($feedback)) {
            $feedback[] = 'Good notes. Detailed and actionable.';
        }

        return [
            'score' => $score,
            'label' => $label,
            'word_count' => $wordCount,
            'positive_signals' => $positiveHits,
            'feedback' => $feedback,
        ];
    }
}
