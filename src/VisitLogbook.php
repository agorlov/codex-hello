<?php

namespace App;

use App\Database\SqliteConnection;
use PDOException;
use Symfony\Component\HttpFoundation\Request;
use RuntimeException;

/**
 * Журнал посещений приветственной страницы, сохраняющий обращения в SQLite.
 */
final class VisitLogbook
{
    /**
     * Принимает подключение к SQLite и готовит журнал посещений для записей.
     */
    public function __construct(
        private readonly SqliteConnection $sqliteConnection,
    ) {
    }

    /**
     * Регистрирует посещение страницы в журнале SQLite.
     */
    public function recordVisit(string $route, string $language, Request $request): void
    {
        $connection = $this->sqliteConnection->db();

        try {
            $statement = $connection->prepare(
                <<<'SQL'
INSERT INTO visit_log (visited_at, route, language, path, query, referer, ip_address, user_agent)
VALUES (datetime('now'), :route, :language, :path, :query, :referer, :ip_address, :user_agent)
SQL
            );
        } catch (PDOException $exception) {
            throw new RuntimeException('Не удалось подготовить запрос для записи журнала посещений.', 0, $exception);
        }

        $queryString = $request->getQueryString();
        $normalizedQuery = ($queryString === null || $queryString === '') ? null : $queryString;

        try {
            $statement->execute([
                ':route' => $route,
                ':language' => $language,
                ':path' => $request->getPathInfo(),
                ':query' => $normalizedQuery,
                ':referer' => $request->headers->get('referer'),
                ':ip_address' => $request->getClientIp(),
                ':user_agent' => $request->headers->get('user-agent'),
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Не удалось записать посещение страницы в журнал.', 0, $exception);
        }
    }

}
