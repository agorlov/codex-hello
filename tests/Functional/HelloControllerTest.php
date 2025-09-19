<?php

namespace App\Tests\Functional;

use App\Greeting\RandomCodexGreeting;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Проверяет, что контроллер приветствия возвращает корректные фразы Codex.
 */
class HelloControllerTest extends WebTestCase
{
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
}
