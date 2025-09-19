<?php

namespace App\Tests;

use App\Database\SqliteConnection;
use App\Database\SqliteMigrations;
use App\VisitLogbook;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Проверяет журнал посещений, который сохраняет обращения в таблицу SQLite.
 */
class VisitLogbookTest extends TestCase
{
    private string $databaseDirectory;

    private string $databasePath;

    private string $migrationsDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'codex-visit-log-' . uniqid('', true);
        $this->databasePath = $this->databaseDirectory . DIRECTORY_SEPARATOR . 'visit-log.db';
        $this->migrationsDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'db-data' . DIRECTORY_SEPARATOR . 'migrations';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }

        if (is_dir($this->databaseDirectory)) {
            rmdir($this->databaseDirectory);
        }

        parent::tearDown();
    }

    /**
     * Убеждается, что запись посещения создаёт таблицу и сохраняет данные.
     */
    public function testRecordVisitCreatesTableAndStoresEntry(): void
    {
        $sqliteConnection = new SqliteConnection($this->databasePath);
        $sqliteMigrations = new SqliteMigrations($sqliteConnection);
        $visitLogbook = new VisitLogbook($sqliteConnection, $sqliteMigrations, $this->migrationsDirectory);

        $request = Request::create('/', 'GET', ['language' => 'ru'], [], [], [
            'HTTP_REFERER' => 'https://example.com/',
            'HTTP_USER_AGENT' => 'Symfony BrowserKit',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);

        $visitLogbook->recordVisit('app_home', 'ru', $request);

        $connection = $sqliteConnection->db();
        $statement = $connection->query(
            'SELECT visited_at, route, language, path, query, referer, ip_address, user_agent FROM visit_log',
        );

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $row['visited_at']);
        $this->assertSame('app_home', $row['route']);
        $this->assertSame('ru', $row['language']);
        $this->assertSame('/', $row['path']);
        $this->assertSame('language=ru', $row['query']);
        $this->assertSame('https://example.com/', $row['referer']);
        $this->assertSame('127.0.0.1', $row['ip_address']);
        $this->assertSame('Symfony BrowserKit', $row['user_agent']);
    }
}

