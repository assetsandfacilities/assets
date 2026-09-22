<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Database-agnostic date-grouping SQL. This system has run on three different
 * engines across development: SQLite (quick local testing), MySQL (general
 * portability / XAMPP), and PostgreSQL (the actual production host, which is
 * what this project's Dockerfile/entrypoint.sh are built for). Each has a
 * different SQL function for "give me the month/year-month of this date
 * column", so hard-coding one breaks silently on the others -- this class
 * picks the right one for whichever database is actually connected.
 *
 * Use the *Select() variant in ->selectRaw(...) and the matching *GroupBy()
 * variant in ->groupByRaw(...). Postgres requires grouping by the full
 * expression rather than the SELECT alias, so groupByRaw is used everywhere
 * for consistency across all three drivers rather than relying on
 * alias-grouping being lenient on the others.
 */
class DateSql
{
    public static function yearMonthSelect(string $column): string
    {
        return match (self::driver()) {
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')",
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "strftime('%Y-%m', {$column})",
        };
    }

    public static function yearMonthGroupBy(string $column): string
    {
        return self::yearMonthSelect($column);
    }

    public static function monthNumSelect(string $column): string
    {
        return match (self::driver()) {
            'pgsql' => "EXTRACT(MONTH FROM {$column})",
            'mysql', 'mariadb' => "DATE_FORMAT({$column}, '%m')",
            default => "strftime('%m', {$column})",
        };
    }

    public static function monthNumGroupBy(string $column): string
    {
        return self::monthNumSelect($column);
    }

    private static function driver(): string
    {
        return DB::connection()->getDriverName();
    }
}
