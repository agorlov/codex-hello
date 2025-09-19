<?php

namespace App\Tests\Functional;

use App\Database\SqliteConnection;
use App\Greeting\RandomCodexGreeting;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Проверяет, что контроллер приветствия возвращает корректные фразы Codex.
 */
class HelloControllerTest extends WebTestCase
{
    private string $databaseDirectory;

    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->databaseDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db-data';
        $this->databasePath = $this->databaseDirectory . DIRECTORY_SEPARATOR . 'app.db';

        $this->removeDatabaseArtifacts();
    }

    protected function tearDown(): void
    {
        $this->removeDatabaseArtifacts();

        if (is_dir($this->databaseDirectory) && $this->isDirectoryEmpty($this->databaseDirectory)) {
            rmdir($this->databaseDirectory);
        }

        parent::tearDown();
    }

    /**
     * Убеждается, что по умолчанию используется русское приветствие Codex.
     */
    public function testHomepageDisplaysRussianGreetingByDefault(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();

        $greeting = $crawler->filter('h1')->text();
        $greetingsByLanguage = $this->getGreetingsByLanguage();

        $this->assertContains($greeting, $greetingsByLanguage['ru']);

        $content = $client->getResponse()->getContent();

        $this->assertNotFalse($content);
        $this->assertStringContainsString('Текущее время по данным SQLite', $content);
        $this->assertMatchesRegularExpression(
            '/Текущее время по данным SQLite:\s*<span[^>]*>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}<\/span>/',
            $content,
        );
    }

    /**
     * Проверяет, что параметр language=en включает английские приветствия Codex.
     */
    public function testHomepageDisplaysEnglishGreetingWhenRequested(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/?language=en');

        $this->assertResponseIsSuccessful();

        $greeting = $crawler->filter('h1')->text();
        $greetingsByLanguage = $this->getGreetingsByLanguage();

        $this->assertContains($greeting, $greetingsByLanguage['en']);
    }

    /**
     * Проверяет, что неподдерживаемый язык откатывается к русскому приветствию.
     */
    public function testHomepageFallsBackToDefaultLanguageWhenUnsupported(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/?language=fr');

        $this->assertResponseIsSuccessful();

        $greeting = $crawler->filter('h1')->text();
        $greetingsByLanguage = $this->getGreetingsByLanguage();

        $this->assertContains($greeting, $greetingsByLanguage['ru']);
    }

    /**
     * Возвращает карту доступных приветствий Codex, сгруппированных по языку.
     *
     * @return array<string, string[]>
     */
    private function getGreetingsByLanguage(): array
    {
        $reflection = new ReflectionClass(RandomCodexGreeting::class);

        /** @var array<string, string[]> $greetings */
        $greetings = (array) $reflection->getConstant('GREETINGS');

        return $greetings;
    }

    /**
     * Проверяет, что обращение к странице записывается в журнал SQLite.
     */
    public function testHomepageRecordsVisitInLogbook(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?language=en', [], [], [
            'HTTP_REFERER' => 'https://codex.so/',
            'HTTP_USER_AGENT' => 'CodexTestAgent/1.0',
            'REMOTE_ADDR' => '203.0.113.10',
        ]);

        $this->assertResponseIsSuccessful();

        $sqliteConnection = new SqliteConnection($this->databasePath);
        $connection = $sqliteConnection->db();

        $countStatement = $connection->query('SELECT COUNT(*) AS aggregate FROM visit_log');
        $countRow = $countStatement->fetch();

        $this->assertNotFalse($countRow);
        $this->assertSame(1, (int) $countRow['aggregate']);

        $logStatement = $connection->query(
            'SELECT visited_at, route, language, path, query, referer, ip_address, user_agent FROM visit_log ORDER BY id DESC LIMIT 1',
        );
        $logRow = $logStatement->fetch();

        $this->assertNotFalse($logRow);
        $this->assertSame('app_home', $logRow['route']);
        $this->assertSame('en', $logRow['language']);
        $this->assertSame('/', $logRow['path']);
        $this->assertSame('language=en', $logRow['query']);
        $this->assertSame('https://codex.so/', $logRow['referer']);
        $this->assertSame('203.0.113.10', $logRow['ip_address']);
        $this->assertSame('CodexTestAgent/1.0', $logRow['user_agent']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            (string) $logRow['visited_at'],
        );
    }

    /**
     * Удаляет файл базы данных, созданный в ходе тестов.
     */
    private function removeDatabaseArtifacts(): void
    {
        if (file_exists($this->databasePath)) {
            unlink($this->databasePath);
        }
    }

    /**
     * Проверяет, что каталог пустой (кроме псевдоссылок).
     */
    private function isDirectoryEmpty(string $directory): bool
    {
        $contents = scandir($directory);

        if ($contents === false) {
            return true;
        }

        return count(array_diff($contents, ['.', '..'])) === 0;
    }
}
