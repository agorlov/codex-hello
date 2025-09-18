<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Проверяет, что контроллер приветствия отвечает ожидаемым текстом.
 */
class HelloControllerTest extends WebTestCase
{
    /**
     * Отправляет GET-запрос на корневой маршрут и проверяет ответ.
     */
    public function testHomepageDisplaysGreeting(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Hello codex!', $client->getResponse()->getContent());
    }
}
