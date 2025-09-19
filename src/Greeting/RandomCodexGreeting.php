<?php

namespace App\Greeting;

use InvalidArgumentException;

/**
 * Сущность, выбирающая случайное приветствие Codex для указанного языка.
 */
final class RandomCodexGreeting
{
    private const GREETINGS = [
        'ru' => [
            'Привет, Codex!',
            'Здравствуйте, команда Codex!',
            'Codex говорит: рады знакомству!',
            'Добро пожаловать в Playground Codex!',
            'Вместе с Codex создадим что-то прекрасное!',
        ],
        'en' => [
            'Hello, Codex!',
            'Greetings, Codex team!',
            'Codex says: nice to meet you!',
            'Welcome to the Codex Playground!',
            "Let's build something amazing with Codex!",
        ],
    ];

    /**
     * Создаёт объект случайных приветствий Codex с выбранным языком.
     */
    public function __construct(
        private readonly string $language = 'ru',
    ) {
        if (!self::isLanguageSupported($this->language)) {
            throw new InvalidArgumentException(sprintf('Unsupported language "%s" for Codex greetings.', $this->language));
        }
    }

    /**
     * Определяет, поддерживается ли указанный язык приветствий Codex.
     */
    public static function isLanguageSupported(string $language): bool
    {
        return array_key_exists($language, self::GREETINGS);
    }

    /**
     * Подбирает случайное приветствие Codex для выбранного языка.
     */
    public function greet(): string
    {
        $greetings = self::GREETINGS[$this->language];
        $index = array_rand($greetings);

        return $greetings[$index];
    }
}
