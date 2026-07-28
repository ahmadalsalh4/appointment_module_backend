<?php

namespace App\Support;

class SearchHelper
{
    /**
     * Escape character for LIKE patterns. Must be a single character that
     * has no special meaning in SQL string literals on the target drivers.
     * Exclamation mark is safe on MySQL, PostgreSQL, and SQLite.
     *
     * IMPORTANT: We don't use PHP's addcslashes() because it always uses
     * backslash as the escape prefix — and SQLite does not process
     * backslash escapes in string literals, so '\' would be a 2-char
     * escape expression. We do our own replace using ESCAPE_CHAR.
     */
    public const ESCAPE_CHAR = '!';

    /**
     * Escape SQL LIKE wildcards (% and _) in the user-supplied search term,
     * plus the escape character itself.
     */
    public static function likeEscape(string $value): string
    {
        return strtr($value, [
            self::ESCAPE_CHAR => self::ESCAPE_CHAR . self::ESCAPE_CHAR,
            '%' => self::ESCAPE_CHAR . '%',
            '_' => self::ESCAPE_CHAR . '_',
        ]);
    }

    /**
     * Build a substring LIKE pattern with wildcards safely escaped.
     * Pairs with the ESCAPE clause so the escape character is honoured.
     */
    public static function likeContains(string $value): string
    {
        return '%' . self::likeEscape($value) . '%';
    }

    /**
     * The ESCAPE clause that pairs with the patterns produced by likeContains.
     * Always use this together with the pattern.
     */
    public const ESCAPE_CLAUSE = "ESCAPE '!'";
}
