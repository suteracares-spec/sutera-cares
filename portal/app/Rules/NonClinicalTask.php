<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Refuses care-plan tasks that are nursing, not caregiving.
 *
 * Nursing is regulated under the Nurses Act 1950, and the provider site
 * promises families in writing that caregivers do not give injections,
 * dress wounds, manage IV lines or change catheters. A care plan that
 * quietly lists one of those breaks that promise, so the plan refuses it.
 *
 * Deliberately narrow: "help with dressing" is getting dressed and must
 * pass, and "remind to take medication" is in scope. This catches the
 * obvious cases; it is not a substitute for the coordinator's judgement.
 */
class NonClinicalTask implements ValidationRule
{
    private const PATTERNS = [
        '/\binject/i',
        '/\bwound\s*(care|dressing|cleaning)/i',
        '/\b(dress|clean|pack)\w*\b[^.]{0,30}\bwounds?\b/i',
        '/\bIV\b/',
        '/\bintravenous/i',
        '/\bcatheter/i',
        '/\bcannula/i',
        '/\bsuction/i',
        '/\bnasogastric|\bNG\s*tube|\bPEG\s*(tube|feed)|\btube\s*feed/i',
        '/\btracheostomy/i',
        '/\b(adjust|change)\w*\s+(the\s+)?(dose|dosage)/i',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, (string) $value)) {
                $fail('"' . mb_strimwidth((string) $value, 0, 60, '…') . '" is a clinical task. '
                    . 'Caregivers do not give injections, dress wounds, manage IV lines or catheters, '
                    . 'or adjust doses. Refer this to a licensed home-nursing provider.');

                return;
            }
        }
    }
}
