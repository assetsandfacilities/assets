<?php

if (!function_exists('mb_split')) {
    /**
     * Minimal mb_split polyfill for environments where ext-mbstring lacks mb_split.
     * Laravel 12 may call this during command routing / string helpers.
     */
    function mb_split(string $pattern, string $string, int $limit = -1): array|false
    {
        $delimited = '/' . str_replace('/', '\\/', $pattern) . '/u';

        $result = preg_split($delimited, $string, $limit);

        return $result === false ? false : $result;
    }
}
