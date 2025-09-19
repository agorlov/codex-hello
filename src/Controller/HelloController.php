<?php

namespace App\Controller;

use App\Database\SqliteDB;
use App\Database\SqliteDatetime;
use App\Database\SqliteMigrations;
use App\Greeting\RandomCodexGreeting;
use App\VisitLogbook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Страница приветственного сообщения Codex.
 */
final class HelloController extends AbstractController
{
    private const DEFAULT_GREETING_LANGUAGE = 'ru';
    private const DATABASE_PATH = __DIR__ . '/../../app.db';
    private const MIGRATIONS_DIRECTORY = __DIR__ . '/../../db-data/migrations';

    private readonly SqliteDatetime $sqliteDatetime;

    private readonly VisitLogbook $visitLogbook;

    /**
     * Инициализирует контроллер источником времени SQLite и журналом посещений.
     */
    public function __construct()
    {
        $sqliteConnection = new SqliteDB(self::DATABASE_PATH);
        $sqliteMigrations = new SqliteMigrations($sqliteConnection);

        $this->sqliteDatetime = new SqliteDatetime($sqliteConnection);
        $this->visitLogbook = new VisitLogbook(
            $sqliteConnection,
            $sqliteMigrations,
            self::MIGRATIONS_DIRECTORY,
        );
    }

    /**
     * Формирует и возвращает оформленную приветственную страницу Codex.
     */
    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
        $language = $this->resolveLanguage($request);
        $randomGreeting = new RandomCodexGreeting($language);
        $currentDateTime = $this->sqliteDatetime->currentDateTime();
        $greeting = $randomGreeting->greet();

        $this->visitLogbook->recordVisit('app_home', $language, $request);

        return $this->render('homepage.html.twig', [
            'greeting' => $greeting,
            'pageTitle' => 'Codex приветствует вас',
            'introText' => 'Создайте свой первый проект, экспериментируйте с компонентами Symfony и украшайте интерфейсы при помощи Tailwind.',
            'documentationUrl' => 'https://symfony.com/doc/current/index.html',
            'currentSqliteDateTime' => $currentDateTime,
            'highlights' => [
                [
                    'title' => 'Symfony',
                    'description' => 'Современный PHP-фреймворк, который помогает строить мощные и устойчивые приложения.',
                ],
                [
                    'title' => 'Tailwind CSS',
                    'description' => 'Утилитарный CSS-фреймворк, позволяющий быстро создавать аккуратные интерфейсы без лишнего кода.',
                ],
                [
                    'title' => 'Быстрый старт',
                    'description' => 'Используйте примеры, чтобы ускорить знакомство с фреймворком и его экосистемой.',
                ],
                [
                    'title' => 'Комьюнити',
                    'description' => 'Делитесь находками и задавайте вопросы сообществу Codex и Symfony.',
                ],
            ],
        ]);
    }

    /**
     * Определяет язык приветствий на основе параметра запроса или умолчания.
     */
    private function resolveLanguage(Request $request): string
    {
        $languageParameter = $request->query->getAlpha('language');

        if ($languageParameter === null || $languageParameter === '') {
            return self::DEFAULT_GREETING_LANGUAGE;
        }

        $language = strtolower($languageParameter);

        if (RandomCodexGreeting::isLanguageSupported($language)) {
            return $language;
        }

        return self::DEFAULT_GREETING_LANGUAGE;
    }

}
