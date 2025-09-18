<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Страница приветственного сообщения Codex.
 */
final class HelloController extends AbstractController
{
    /**
     * Формирует и возвращает оформленную приветственную страницу Codex.
     */
    #[Route('/', name: 'app_home')]
    public function __invoke(): Response
    {
        return $this->render('homepage.html.twig', [
            'greeting' => 'Hello codex!',
            'pageTitle' => 'Codex приветствует вас',
            'introText' => 'Создайте свой первый проект, экспериментируйте с компонентами Symfony и украшайте интерфейсы при помощи Tailwind.',
            'documentationUrl' => 'https://symfony.com/doc/current/index.html',
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
}
