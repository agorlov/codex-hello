<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Проверяет доступность страницы LLM-чата.
 */
final class ChatControllerTest extends WebTestCase
{
    /**
     * Убеждается, что страница LLM-чата открывается и содержит базовые элементы.
     */
    public function testChatPageLoadsSuccessfully(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/chat');

        $this->assertResponseIsSuccessful();
        $this->assertSame('LLM-чат', $crawler->filter('h1')->text());
        $responseContent = $client->getResponse()->getContent();
        $this->assertIsString($responseContent);
        $this->assertStringContainsString('LLM-чат', $responseContent);
    }
}
