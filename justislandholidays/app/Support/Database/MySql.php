<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

/**
 * Runs DDL that only MySQL 8.0.16+ supports (CHECK constraints, FULLTEXT).
 * Skipped on other drivers so the test suite can run on in-memory SQLite.
 */
final class MySql
{
    public static function isMySql(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    public static function statement(string $sql): void
    {
        if (self::isMySql()) {
            DB::statement($sql);
        }
    }
}
