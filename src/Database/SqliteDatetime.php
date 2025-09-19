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
    /**
     * Инициализирует источник времени объектом подключения SQLite.
     */
    public function __construct(
        private readonly SqliteDB $sqliteConnection,
    ) {
    }

    /**
     * Возвращает актуальную дату и время из SQLite.
     */
    public function currentDateTime(): DateTimeImmutable
    {
        $connection = $this->sqliteConnection->db();

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

}
