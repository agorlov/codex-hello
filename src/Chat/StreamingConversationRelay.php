<?php

namespace App\Chat;

/**
 * Организует потоковую передачу сообщений между приложением и LLM.
 */
final class StreamingConversationRelay
{
    private const LLM_ENDPOINT = 'http://193.104.69.173/v1/chat/completions';

    public const MODEL = 'qwen/qwen3-30b-a3b-2507';

    private const EVENT_CONTINUE = 0;
    private const EVENT_DONE = 1;
    private const EVENT_ABORT = 2;

    private readonly string $llmApiKey;

    public function __construct(string $llmApiKey)
    {
        $this->llmApiKey = $llmApiKey;
    }

    /**
     * Проверяет, настроен ли ключ доступа к модели.
     */
    public function isConfigured(): bool
    {
        return $this->llmApiKey !== '';
    }

    /**
     * Выполняет потоковый запрос к модели и передаёт части ответа в колбэки.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param callable(string): void $onTextChunk
     * @param callable(): void $onComplete
     * @param callable(string): void $onError
     */
    public function stream(
        array $messages,
        callable $onTextChunk,
        callable $onComplete,
        callable $onError,
    ): void {
        if (!$this->isConfigured()) {
            $onError('Ключ доступа к модели не настроен.');

            return;
        }

        $requestPayload = [
            'model' => self::MODEL,
            'messages' => $this->formatMessagesForApi($messages),
            'stream' => true,
        ];

        $requestBody = json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($requestBody)) {
            $onError('Не удалось подготовить запрос к модели.');

            return;
        }

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->llmApiKey,
        ];

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $requestBody,
                'ignore_errors' => true,
                'timeout' => 60,
            ],
        ]);

        error_clear_last();
        $stream = @fopen(self::LLM_ENDPOINT, 'r', false, $context);
        $responseHeaders = $http_response_header ?? [];

        if ($stream === false) {
            $lastError = error_get_last();
            $message = is_array($lastError) && isset($lastError['message'])
                ? (string) $lastError['message']
                : 'Не удалось установить соединение с моделью.';

            $onError($message);

            return;
        }

        $statusCode = $this->extractStatusCode($responseHeaders);
        if ($statusCode >= 400) {
            $body = stream_get_contents($stream);
            fclose($stream);

            $onError($this->resolveErrorMessage($statusCode, $body));

            return;
        }

        $this->relayStream($stream, $onTextChunk, $onComplete, $onError);
    }

    /**
     * Запускает цикл чтения потока и пересылки событий клиенту.
     *
     * @param resource $stream
     * @param callable(string): void $onTextChunk
     * @param callable(): void $onComplete
     * @param callable(string): void $onError
     */
    private function relayStream($stream, callable $onTextChunk, callable $onComplete, callable $onError): void
    {
        $buffer = '';
        $isComplete = false;

        while (($line = fgets($stream)) !== false) {
            $buffer .= $line;

            if (trim($line) !== '') {
                continue;
            }

            $eventResult = $this->handleSseEvent($buffer, $onTextChunk, $onComplete, $onError);
            $buffer = '';

            if ($eventResult === self::EVENT_DONE) {
                $isComplete = true;

                break;
            }

            if ($eventResult === self::EVENT_ABORT) {
                fclose($stream);

                return;
            }
        }

        if (!$isComplete && $buffer !== '') {
            $eventResult = $this->handleSseEvent($buffer, $onTextChunk, $onComplete, $onError);

            if ($eventResult === self::EVENT_DONE) {
                $isComplete = true;
            } elseif ($eventResult === self::EVENT_ABORT) {
                fclose($stream);

                return;
            }
        }

        if (!$isComplete) {
            $onComplete();
        }

        fclose($stream);
    }

    /**
     * Обрабатывает отдельное SSE-событие и передаёт его содержимое в колбэки.
     *
     * @param callable(string): void $onTextChunk
     * @param callable(): void $onComplete
     * @param callable(string): void $onError
     */
    private function handleSseEvent(
        string $eventChunk,
        callable $onTextChunk,
        callable $onComplete,
        callable $onError,
    ): int {
        $dataString = $this->extractEventData($eventChunk);
        if ($dataString === null) {
            return self::EVENT_CONTINUE;
        }

        if ($dataString === '[DONE]') {
            $onComplete();

            return self::EVENT_DONE;
        }

        $decoded = json_decode($dataString, true);
        if (!is_array($decoded)) {
            return self::EVENT_CONTINUE;
        }

        if (isset($decoded['error'])) {
            $onError($this->stringifyError($decoded['error']));

            return self::EVENT_ABORT;
        }

        if (!isset($decoded['choices']) || !is_array($decoded['choices'])) {
            return self::EVENT_CONTINUE;
        }

        $shouldComplete = false;

        foreach ($decoded['choices'] as $choice) {
            if (!is_array($choice)) {
                continue;
            }

            $delta = $choice['delta'] ?? null;
            if (is_array($delta) && array_key_exists('content', $delta)) {
                $chunk = $this->stringifyContent($delta['content']);

                if ($chunk !== '') {
                    $onTextChunk($chunk);
                }
            }

            if (isset($choice['finish_reason']) && $choice['finish_reason'] === 'stop') {
                $shouldComplete = true;
            }
        }

        if ($shouldComplete) {
            $onComplete();

            return self::EVENT_DONE;
        }

        return self::EVENT_CONTINUE;
    }

    /**
     * Извлекает полезные данные из SSE-сообщения.
     */
    private function extractEventData(string $eventChunk): ?string
    {
        $lines = preg_split('/\r?\n/', $eventChunk) ?: [];
        $dataLines = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '' || $trimmedLine[0] === ':') {
                continue;
            }

            if (str_starts_with($trimmedLine, 'data:')) {
                $dataLines[] = ltrim(substr($trimmedLine, 5));
            }
        }

        if ($dataLines === []) {
            return null;
        }

        $payload = implode("\n", $dataLines);

        return $payload === '' ? null : $payload;
    }

    /**
     * Преобразует возможные варианты содержимого в текст.
     *
     * @param mixed $content
     */
    private function stringifyContent(mixed $content): string
    {
        if (is_string($content)) {
            return $content;
        }

        if (is_array($content)) {
            return $this->mergeTextParts($content);
        }

        return '';
    }

    /**
     * Формирует текст ошибки из произвольной структуры.
     */
    private function stringifyError(mixed $error): string
    {
        if (is_array($error) && isset($error['message']) && is_string($error['message'])) {
            return $error['message'];
        }

        if (is_string($error)) {
            return $error;
        }

        return 'Модель вернула ошибку.';
    }

    /**
     * Извлекает HTTP-статус из заголовков ответа.
     *
     * @param string[] $responseHeaders
     */
    private function extractStatusCode(array $responseHeaders): int
    {
        if ($responseHeaders === []) {
            return 0;
        }

        $statusLine = $responseHeaders[0];
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Подбирает понятное сообщение об ошибке по HTTP-статусу и телу ответа.
     */
    private function resolveErrorMessage(int $statusCode, string|false $body): string
    {
        if (is_string($body) && $body !== '') {
            $decoded = json_decode($body, true);

            if (is_array($decoded) && isset($decoded['error'])) {
                return $this->stringifyError($decoded['error']);
            }
        }

        return match ($statusCode) {
            401 => 'Ошибка авторизации API. Проверьте правильность ключа API.',
            403 => 'Доступ к модели запрещён. Проверьте права ключа API.',
            default => 'Модель вернула ошибку. Код ответа: ' . $statusCode . '.',
        };
    }

    /**
     * Приводит сообщения диалога к формату, ожидаемому моделью.
     *
     * @param array<int, array{role: string, content: string}> $messages
     *
     * @return array<int, array{role: string, content: array<int, array{type: string, text: string}>}>
     */
    private function formatMessagesForApi(array $messages): array
    {
        $prepared = [];

        foreach ($messages as $message) {
            $prepared[] = [
                'role' => $message['role'],
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $message['content'],
                    ],
                ],
            ];
        }

        return $prepared;
    }

    /**
     * Склеивает текстовые части сложного содержимого в одну строку.
     *
     * @param array<int, mixed> $parts
     */
    private function mergeTextParts(array $parts): string
    {
        $fragments = [];

        foreach ($parts as $part) {
            if (!is_array($part)) {
                continue;
            }

            $type = isset($part['type']) ? (string) $part['type'] : '';
            if ($type !== 'text') {
                continue;
            }

            $text = isset($part['text']) ? (string) $part['text'] : '';
            if ($text === '') {
                continue;
            }

            $fragments[] = $text;
        }

        return implode("\n", $fragments);
    }
}
