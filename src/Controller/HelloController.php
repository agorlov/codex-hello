<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Контроллер, возвращающий приветственное сообщение на корневом маршруте.
 */
final class HelloController
{
    /**
     * Отвечает текстом «Hello codex!» на запросы к главной странице.
     */
    #[Route('/', name: 'app_home')]
    public function __invoke(): Response
    {
        return new Response('Hello codex!');
    }
}
