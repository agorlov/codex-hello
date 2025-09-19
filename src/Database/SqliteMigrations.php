<?php

namespace App\Database;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Управляет миграциями SQLite, выполняя SQL-скрипты и фиксируя их применение.
 */
final class SqliteMigrations
{
    /**
     * Запоминает поставщика SQLite-подключения для выполнения миграций.
     */
    public function __construct(
        private readonly SqliteConnection $sqliteConnection,
    ) {
    }

    /**
     * Применяет все миграции из каталога, которые ещё не отмечены в журнале.
     */
    public function applyMigrations(string $directory): void
    {
        $connection = $this->sqliteConnection->db();

        $this->ensureJournalExists($connection);

        $applied = $this->loadAppliedMigrations($connection);
        $migrations = $this->collectMigrationFiles($directory);

        foreach ($migrations as $filename => $path) {
            if (isset($applied[$filename])) {
                continue;
            }

            $this->applyMigration($connection, $filename, $path);
        }
    }

    /**
     * Создаёт таблицу учёта миграций, если её ещё нет.
     */
    private function ensureJournalExists(PDO $connection): void
    {
        try {
            $connection->exec(
                <<<'SQL'
CREATE TABLE IF NOT EXISTS schema_migrations (
    filename TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL
)
SQL
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Не удалось создать служебную таблицу миграций.', 0, $exception);
        }
    }

    /**
     * Возвращает список уже применённых миграций.
     *
     * @return array<string, true> Массив вида ['001_init.sql' => true] для каждого выполненного файла.
     */
    private function loadAppliedMigrations(PDO $connection): array
    {
        try {
            $statement = $connection->query('SELECT filename FROM schema_migrations');
        } catch (PDOException $exception) {
            throw new RuntimeException('Не удалось прочитать список применённых миграций.', 0, $exception);
        }

        $applied = [];

        foreach ($statement as $row) {
            if (!isset($row['filename'])) {
                continue;
            }

            $applied[$row['filename']] = true;
        }

        return $applied;
    }

    /**
     * Собирает миграции из каталога и сортирует их по имени файла.
     *
     * @return array<string, string> Массив вида ['001_init.sql' => '/path/001_init.sql'].
     */
    private function collectMigrationFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf('Каталог миграций "%s" не найден.', $directory));
        }

        $entries = scandir($directory);

        if ($entries === false) {
            throw new RuntimeException(sprintf('Не удалось прочитать каталог миграций "%s".', $directory));
        }

        $files = [];

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (!str_ends_with($entry, '.sql')) {
                continue;
            }

            $files[$entry] = $directory . DIRECTORY_SEPARATOR . $entry;
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /**
     * Выполняет SQL-миграцию внутри транзакции и отмечает её в журнале.
     */
    private function applyMigration(PDO $connection, string $filename, string $path): void
    {
        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException(sprintf('Не удалось прочитать миграцию "%s".', $filename));
        }

        try {
            $connection->beginTransaction();
            $connection->exec($sql);

            $statement = $connection->prepare(
                'INSERT INTO schema_migrations (filename, applied_at) VALUES (:filename, :applied_at)'
            );
            $statement->execute([
                ':filename' => $filename,
                ':applied_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
            $connection->commit();
        } catch (PDOException $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw new RuntimeException(sprintf('Не удалось применить миграцию "%s".', $filename), 0, $exception);
        }
    }
}
