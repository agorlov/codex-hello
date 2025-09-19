<?php

namespace App\Database;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Представляет источник актуальной даты и времени, вычисляемой SQLite.
 */
final class SqliteDatetime
{
    private ?PDO $connection = null;

    /**
     * Запоминает путь к SQLite-файлу для получения времени.
     */
    public function __construct(
        private readonly string $databasePath,
    ) {
    }

    /**
     * Возвращает актуальную дату и время из SQLite.
     */
    public function currentDateTime(): DateTimeImmutable
    {
        $connection = $this->connection ??= $this->createConnection();

        try {
            $statement = $connection->query("SELECT datetime('now') AS current_datetime");
        } catch (PDOException $exception) {
            throw new RuntimeException('Не удалось выполнить запрос времени в SQLite.', 0, $exception);
        }

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        if ($result === false || !isset($result['current_datetime'])) {
            throw new RuntimeException('SQLite не вернул значение текущей даты и времени.');
        }

        try {
            return new DateTimeImmutable($result['current_datetime']);
        } catch (Throwable $exception) {
            throw new RuntimeException('Полученное из SQLite значение времени имеет неверный формат.', 0, $exception);
        }
    }

    /**
     * Создаёт и настраивает PDO-подключение к файлу SQLite.
     */
    private function createConnection(): PDO
    {
        $directory = dirname($this->databasePath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Не удалось создать каталог "%s" для файла SQLite.', $directory));
            }
        }

        try {
            $connection = new PDO('sqlite:' . $this->databasePath);
        } catch (PDOException $exception) {
            throw new RuntimeException(sprintf('Не удалось открыть SQLite-файл "%s".', $this->databasePath), 0, $exception);
        }

        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $connection;
    }
}
