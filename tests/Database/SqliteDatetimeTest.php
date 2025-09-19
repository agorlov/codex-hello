<?php

namespace App\Tests\Database;

use App\Database\SqliteConnection;
use App\Database\SqliteDatetime;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет объект времени, считывающий текущую дату из SQLite.
 */
class SqliteDatetimeTest extends TestCase
{
    /**
     * Убеждается, что объект возвращает актуальную метку времени SQLite.
     */
    public function testCurrentDateTimeReturnsRecentTimestamp(): void
    {
        $databaseDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'codex-sqlite-' . uniqid('', true);
        $databasePath = $databaseDirectory . DIRECTORY_SEPARATOR . 'time.db';

        $sqliteConnection = new SqliteConnection($databasePath);
        $sqliteDatetime = new SqliteDatetime($sqliteConnection);
        $dateTime = $sqliteDatetime->currentDateTime();

        $this->assertLessThan(5, abs($dateTime->getTimestamp() - time()));

        if (file_exists($databasePath)) {
            unlink($databasePath);
        }

        if (is_dir($databaseDirectory)) {
            rmdir($databaseDirectory);
        }
    }
}
