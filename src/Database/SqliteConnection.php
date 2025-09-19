<?php

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Представляет поставщика PDO-подключения к файлу SQLite.
 */
final class SqliteConnection
{
    private ?PDO $connection = null;

    /**
     * Запоминает путь к SQLite-файлу для последующих подключений.
     */
    public function __construct(
        private readonly string $databasePath,
    ) {
    }

    /**
     * Возвращает подготовленный объект PDO для работы с SQLite.
     */
    public function db(): PDO
    {
        return $this->connection ??= $this->createConnection();
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
