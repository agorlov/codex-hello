<?php

namespace App\Controller;

use App\Chat\StreamingConversationRelay;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Страница стримингового LLM-чата и API для потокового ответа модели.
 */
final class ChatStreamingController extends AbstractController
{
    private const SYSTEM_MESSAGE = 'Вы — дружелюбный ассистент Codex, который помогает с вопросами по проектам и кодированию.';

    private readonly StreamingConversationRelay $conversationRelay;

    public function __construct()
    {
        $apiKey = (string) ($_ENV['LLM_KEY'] ?? $_SERVER['LLM_KEY'] ?? getenv('LLM_KEY') ?? '');
        $this->conversationRelay = new StreamingConversationRelay($apiKey);
    }

    /**
     * Показывает страницу чата с потоковым отображением ответа модели.
     */
    #[Route('/chat2', name: 'app_llm_chat_stream')]
    public function page(): Response
    {
        $isConfigured = $this->conversationRelay->isConfigured();

        return $this->render('llm_chat_streaming.html.twig', [
            'pageTitle' => 'LLM-чат (стриминг)',
            'llmConfigured' => $isConfigured,
            'modelName' => StreamingConversationRelay::MODEL,
            'systemMessage' => self::SYSTEM_MESSAGE,
            'streamEndpoint' => $this->generateUrl('app_llm_chat_stream_stream'),
        ]);
    }

    /**
     * Принимает историю диалога и возвращает поток SSE с частями ответа модели.
     */
    #[Route('/chat2/stream', name: 'app_llm_chat_stream_stream', methods: ['POST'])]
    public function streamConversation(Request $request): Response
    {
        if (!$this->conversationRelay->isConfigured()) {
            return new JsonResponse([
                'error' => 'LLM_KEY не настроен. Обратитесь к администратору для указания ключа доступа.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $contentType = $request->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'application/json')) {
            return new JsonResponse([
                'error' => 'Неверный тип содержимого. Ожидается application/json.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $content = $request->getContent();
        if ($content === '') {
            return new JsonResponse([
                'error' => 'Пустой запрос. Ожидалось содержимое JSON.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $decoded = json_decode($content, true);
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
            $text = isset($message['content']) ? trim((string) $message['content']) : '';

            if ($role === '' || $text === '') {
                continue;
            }

            $normalizedMessages[] = [
                'role' => $role,
                'content' => $text,
            ];
        }

        if ($normalizedMessages === []) {
            return new JsonResponse([
                'error' => 'Не удалось определить сообщения для отправки в модель.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $response = new StreamedResponse(function () use ($normalizedMessages) {
            $sendEvent = static function (array $payload): void {
                $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (!is_string($json)) {
                    return;
                }

                echo 'data: ' . $json . "\n\n";

                if (function_exists('ob_flush')) {
                    @ob_flush();
                }

                flush();
            };

            $this->conversationRelay->stream(
                $normalizedMessages,
                static function (string $chunk) use ($sendEvent): void {
                    if ($chunk === '') {
                        return;
                    }

                    $sendEvent([
                        'event' => 'token',
                        'text' => $chunk,
                    ]);
                },
                static function () use ($sendEvent): void {
                    $sendEvent([
                        'event' => 'done',
                    ]);
                },
                static function (string $errorMessage) use ($sendEvent): void {
                    $sendEvent([
                        'event' => 'error',
                        'message' => $errorMessage,
                    ]);
                },
            );
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
