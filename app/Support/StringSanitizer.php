<?php

namespace App\Support;

class StringSanitizer
{
    /**
     * Fix common encoding artifacts from Windows-1252 / ANSI CSV files
     * that were read as UTF-8.
     *
     * Handles:
     *  - U+FFFD (replacement character) leftover from failed decoding
     *  - Double-encoded UTF-8 sequences (Ã®, Â®, ï¿½, etc.)
     *  - Stray control characters
     *  - Trailing/leading whitespace and multiple spaces
     */
    public static function clean(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // 1. If bytes aren't valid UTF-8, try to interpret as Windows-1252
        if (!mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
            if ($converted !== false) {
                $value = $converted;
            }
        }

        // 2. Fix double-encoded UTF-8 (e.g. "AzomaxÂ®" → "Azomax®")
        if (preg_match('/[ÃÂ][\x80-\xBF]/u', $value)) {
            $fixed = @mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
            if ($fixed !== false && mb_check_encoding($fixed, 'UTF-8')) {
                $value = $fixed;
            }
        }

        // 3. Remove U+FFFD replacement character
        $value = str_replace("\xEF\xBF\xBD", '', $value);

        // 4. Strip any other stray control characters (except \t \n \r)
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value) ?? $value;

        // 5. Collapse multiple spaces and trim
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * Same as clean() but keeps common symbols like ® ™ ° + / , intact.
     * That's the default behavior anyway; this exists as a semantic alias.
     */
    public static function cleanName(?string $value): string
    {
        return self::clean($value);
    }
}