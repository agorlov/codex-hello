<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Страница диалога с LLM и API для общения с моделью.
 */
final class ChatController extends AbstractController
{
    private const LLM_ENDPOINT = 'http://193.104.69.173/v1/chat/completions';
    private const LLM_MODEL = 'qwen/qwen3-30b-a3b-2507';

    private readonly string $llmApiKey;

    public function __construct()
    {
        $this->llmApiKey = (string) ($_ENV['LLM_KEY'] ?? $_SERVER['LLM_KEY'] ?? getenv('LLM_KEY') ?? '');
    }

    /**
     * Отрисовывает страницу с интерфейсом чата.
     */
    #[Route('/chat', name: 'app_llm_chat')]
    public function page(): Response
    {
        $isConfigured = $this->llmApiKey !== '';

        return $this->render('llm_chat.html.twig', [
            'pageTitle' => 'LLM-чат',
            'llmConfigured' => $isConfigured,
            'modelName' => self::LLM_MODEL,
            'systemMessage' => 'Вы — дружелюбный ассистент Codex, который помогает с вопросами по проектам и кодированию.',
        ]);
    }

    /**
     * Принимает сообщения чата и возвращает ответ модели.
     */
    #[Route('/chat/send', name: 'app_llm_chat_send', methods: ['POST'])]
    public function send(Request $request): JsonResponse
    {
        if ($this->llmApiKey === '') {
            return new JsonResponse([
                'error' => 'LLM_KEY не настроен. Обратитесь к администратору для указания ключа доступа.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $decoded = json_decode($request->getContent(), true);
        if (!is_array($decoded)) {
            return new JsonResponse([
                'error' => 'Некорректный формат запроса. Ожидался JSON.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $messages = $decoded['messages'] ?? null;
        if (!is_array($messages)) {
            return new JsonResponse([
                'error' => 'Поле messages обязательно и должно быть массивом.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $normalizedMessages = [];
        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $role = isset($message['role']) ? trim((string) $message['role']) : '';
            $content = isset($message['content']) ? trim((string) $message['content']) : '';

            if ($role === '' || $content === '') {
                continue;
            }

            $normalizedMessages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        if ($normalizedMessages === []) {
            return new JsonResponse([
                'error' => 'Не удалось определить сообщения для отправки в модель.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $requestPayload = [
            'model' => self::LLM_MODEL,
            'messages' => $this->formatMessagesForApi($normalizedMessages),
        ];

        $requestBody = json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($requestBody)) {
            return new JsonResponse([
                'error' => 'Не удалось подготовить запрос к модели.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
        $responseBody = @file_get_contents(self::LLM_ENDPOINT, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $statusCode = $this->extractStatusCode($responseHeaders);

        if ($responseBody === false) {
            $lastError = error_get_last();
            $errorMessage = $lastError['message'] ?? 'Не удалось связаться с моделью.';

            return new JsonResponse([
                'error' => $errorMessage,
            ], Response::HTTP_BAD_GATEWAY);
        }

        $responseData = json_decode($responseBody, true);
        if (!is_array($responseData)) {
            return new JsonResponse([
                'error' => 'Ответ модели имеет неизвестный формат.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        if ($statusCode >= 400) {
            $errorText = 'Модель вернула ошибку.';
            if (isset($responseData['error'])) {
                if (is_array($responseData['error']) && isset($responseData['error']['message']) && is_string($responseData['error']['message'])) {
                    $errorText = $responseData['error']['message'];
                } elseif (is_string($responseData['error'])) {
                    $errorText = $responseData['error'];
                }
            }

            return new JsonResponse([
                'error' => $errorText,
            ], Response::HTTP_BAD_GATEWAY);
        }

        $assistantMessage = null;
        if (isset($responseData['choices']) && is_array($responseData['choices'])) {
            foreach ($responseData['choices'] as $choice) {
                if (!is_array($choice) || !isset($choice['message']) || !is_array($choice['message'])) {
                    continue;
                }

                $message = $choice['message'];

                if (isset($message['content']) && is_string($message['content'])) {
                    $assistantMessage = $message['content'];
                    break;
                }

                if (isset($message['content']) && is_array($message['content'])) {
                    $assistantMessage = $this->mergeTextParts($message['content']);

                    if ($assistantMessage !== '') {
                        break;
                    }

                    $assistantMessage = null;
                }
            }
        }

        if ($assistantMessage === null) {
            return new JsonResponse([
                'error' => 'Модель не вернула ответного сообщения.',
            ], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse([
            'message' => $assistantMessage,
        ]);
    }

    /**
     * Извлекает HTTP-статус из заголовков ответа.
     *
     * @param string[] $responseHeaders
     *
     * @return int
     */
    private function extractStatusCode(array $responseHeaders): int
    {
        if ($responseHeaders === []) {
            return 0;
        }

        $statusLine = $responseHeaders[0];
        if (preg_match('/\\s(\\d{3})\\s/', $statusLine, $matches) === 1) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * Преобразует список сообщений чата к формату, поддерживаемому API модели.
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
     * Склеивает текстовые части ответа ассистента в одну строку.
     *
     * @param array<int, mixed> $parts
     *
     * @return string
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

        return trim(implode("\n", $fragments));
    }

}
