<?php

namespace App\Services;

use App\Models\SafetyKeyword;

/** Flags customer messages that may describe a health problem (spec section 7). It never gives advice. */
class SafetyScanner
{
    /** @return list<string> the keywords found, empty when the message looks ordinary */
    public function scan(string $text): array
    {
        $found = [];

        foreach (SafetyKeyword::query()->where('is_active', true)->pluck('keyword') as $keyword) {
            $keyword = trim((string) $keyword);

            // Match from the start of a word so "sesaknya" also counts for "sesak".
            if ($keyword !== '' && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($keyword, '/').'/iu', $text)) {
                $found[] = $keyword;
            }
        }

        return $found;
    }
}
