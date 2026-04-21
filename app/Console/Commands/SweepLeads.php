<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadSweepLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SweepLeads extends Command
{
    protected $signature = 'leads:sweep
        {--dry-run : Report what would change without writing}
        {--limit=0 : Max leads to process (0 = no limit)}';

    protected $description = 'Auto-fix fields stored in the wrong column on existing leads; log every change to lead_sweep_log.';

    private const EMAIL_RE = '/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $q = Lead::query();
        if ($limit > 0) $q->limit($limit);

        $scanned = 0; $changed = 0; $conflicts = 0;

        $q->chunkById(500, function ($leads) use (&$scanned, &$changed, &$conflicts, $dry) {
            foreach ($leads as $lead) {
                $scanned++;
                $updates = [];
                $logs = [];

                // Rule 1: whitespace on text fields (idempotent — no log unless changed)
                foreach (['resort', 'owner_name', 'phone1', 'phone2', 'city', 'st', 'zip', 'resort_location', 'email'] as $f) {
                    $v = $lead->{$f};
                    if (!is_string($v)) continue;
                    $trim = trim($v);
                    if ($trim !== $v) {
                        $updates[$f] = $trim;
                        $logs[] = ['field' => $f, 'old' => $v, 'new' => $trim, 'rule' => 'trim_whitespace'];
                        $lead->{$f} = $trim; // in-memory so later rules see clean values
                    }
                }

                // Rule 2: email lowercase (idempotent)
                $email = $updates['email'] ?? $lead->email;
                if (is_string($email) && $email !== '' && $email !== strtolower($email)) {
                    $lower = strtolower($email);
                    $updates['email'] = $lower;
                    $logs[] = ['field' => 'email', 'old' => $email, 'new' => $lower, 'rule' => 'email_lowercase'];
                    $lead->email = $lower;
                }

                // Rule 3: owner_name Title-Case from ALL CAPS
                $ownerName = $updates['owner_name'] ?? $lead->owner_name;
                if (is_string($ownerName) && $ownerName !== '' && ctype_upper(preg_replace('/[^A-Z]/', '', $ownerName)) && preg_match('/[A-Z]{3,}/', $ownerName)) {
                    // Entirely ALL CAPS (ignoring non-letters) and has at least one 3+ letter run → re-case
                    $titled = self::smartTitleCase($ownerName);
                    if ($titled !== $ownerName) {
                        $updates['owner_name'] = $titled;
                        $logs[] = ['field' => 'owner_name', 'old' => $ownerName, 'new' => $titled, 'rule' => 'owner_name_titlecase'];
                        $lead->owner_name = $titled;
                    }
                }

                // Rule 4: email-shaped value in non-email field → move to email
                foreach (['phone1', 'phone2', 'resort_location', 'city', 'owner_name'] as $f) {
                    $v = $updates[$f] ?? $lead->{$f};
                    if (!is_string($v) || $v === '') continue;
                    if (preg_match(self::EMAIL_RE, $v)) {
                        if (($updates['email'] ?? $lead->email) === '' || ($updates['email'] ?? $lead->email) === null) {
                            $updates['email'] = strtolower($v);
                            $updates[$f] = '';
                            $logs[] = ['field' => 'email', 'old' => null, 'new' => strtolower($v), 'rule' => "email_moved_from_{$f}"];
                            $logs[] = ['field' => $f, 'old' => $v, 'new' => '', 'rule' => "email_moved_from_{$f}"];
                            $lead->email = strtolower($v);
                            $lead->{$f} = '';
                        } else {
                            $logs[] = ['field' => $f, 'old' => $v, 'new' => $v, 'rule' => 'conflict_skipped'];
                            $conflicts++;
                        }
                    }
                }

                // Rule 5: phone-shaped value (10+ digits after strip) in non-phone field → move to phone1
                foreach (['email', 'city', 'zip', 'resort_location', 'owner_name'] as $f) {
                    $v = $updates[$f] ?? $lead->{$f};
                    if (!is_string($v) || $v === '') continue;
                    $digits = preg_replace('/[^0-9]/', '', $v);
                    if (strlen($digits) >= 10 && strlen($digits) <= 15 && !preg_match(self::EMAIL_RE, $v)) {
                        $target = ($updates['phone1'] ?? $lead->phone1) === '' || ($updates['phone1'] ?? $lead->phone1) === null ? 'phone1'
                            : ((($updates['phone2'] ?? $lead->phone2) === '' || ($updates['phone2'] ?? $lead->phone2) === null) ? 'phone2' : null);
                        if ($target) {
                            $updates[$target] = $v;
                            $updates[$f] = '';
                            $logs[] = ['field' => $target, 'old' => null, 'new' => $v, 'rule' => "phone_moved_from_{$f}"];
                            $logs[] = ['field' => $f, 'old' => $v, 'new' => '', 'rule' => "phone_moved_from_{$f}"];
                            $lead->{$target} = $v;
                            $lead->{$f} = '';
                        } else {
                            $logs[] = ['field' => $f, 'old' => $v, 'new' => $v, 'rule' => 'conflict_skipped'];
                            $conflicts++;
                        }
                    }
                }

                // Rule 6: 2-letter state code in city with empty st → move to st
                $city = $updates['city'] ?? $lead->city;
                $st = $updates['st'] ?? $lead->st;
                if (is_string($city) && preg_match('/^[A-Za-z]{2}$/', $city) && (!$st || $st === '')) {
                    $updates['st'] = strtoupper($city);
                    $updates['city'] = '';
                    $logs[] = ['field' => 'st', 'old' => null, 'new' => strtoupper($city), 'rule' => 'state_moved_from_city'];
                    $logs[] = ['field' => 'city', 'old' => $city, 'new' => '', 'rule' => 'state_moved_from_city'];
                    $lead->st = strtoupper($city);
                    $lead->city = '';
                }

                if (empty($updates)) continue;

                $changed++;
                $this->line("  #{$lead->id} — " . count(array_filter($logs, fn($l) => $l['rule'] !== 'conflict_skipped')) . " change(s)" . ($dry ? ' [dry]' : ''));

                if ($dry) continue;

                DB::transaction(function () use ($lead, $updates, $logs) {
                    Lead::where('id', $lead->id)->update($updates);
                    foreach ($logs as $l) {
                        LeadSweepLog::create([
                            'lead_id' => $lead->id,
                            'field_name' => $l['field'],
                            'old_value' => $l['old'] ?? null,
                            'new_value' => $l['new'] ?? null,
                            'rule' => $l['rule'],
                            'created_at' => now(),
                        ]);
                    }
                });
            }
        });

        $verb = $dry ? 'Would change' : 'Changed';
        $this->info("Scanned {$scanned} leads. {$verb} {$changed}. Conflicts logged: {$conflicts}.");
        return self::SUCCESS;
    }

    private static function smartTitleCase(string $s): string
    {
        // Preserve short particles that should stay lowercase (de, la, etc.)
        $lowercase = ['de', 'la', 'el', 'del', 'y', 'van', 'von', 'da', 'di'];
        $parts = preg_split('/(\s+|-)/', strtolower($s), -1, PREG_SPLIT_DELIM_CAPTURE);
        $first = true;
        foreach ($parts as $i => $p) {
            if ($p === '' || preg_match('/^\s+|-$/', $p)) continue;
            if (!$first && in_array($p, $lowercase, true)) {
                // leave lowercase
            } else {
                $parts[$i] = mb_convert_case($p, MB_CASE_TITLE, 'UTF-8');
            }
            $first = false;
        }
        return implode('', $parts);
    }
}
