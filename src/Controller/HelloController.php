<?php

namespace App\Controller;

use App\Database\SqliteDatetime;
use App\Greeting\RandomCodexGreeting;
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

    /**
     * Инициализирует контроллер источником времени SQLite.
     */
    public function __construct(
        private readonly SqliteDatetime $sqliteDatetime,
    ) {
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

        return $this->render('homepage.html.twig', [
            'greeting' => $randomGreeting->greet(),
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
