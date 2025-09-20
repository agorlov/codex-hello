<?php

/**
 * Продвинутый тестовый скрипт для проверки режима стриминга ответа LLM
 * с правильной обработкой Server-Sent Events (SSE)
 */

class LLMStreamTester
{
    private string $llmApiKey;
    private string $endpoint;
    private string $model;
    
    public function __construct()
    {
        $this->llmApiKey = getenv('LLM_KEY') ?: '123456789'; // Тестовый ключ
        $this->endpoint = 'http://193.104.69.173/v1/chat/completions';
        $this->model = 'qwen/qwen3-30b-a3b-2507';
    }
    
    public function testStreaming(): void
    {
        echo "=== Тест режима стриминга LLM ===\n\n";
        
        // Параметры запроса
        $requestPayload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'Always answer in rhymes.'],
                ['role' => 'user', 'content' => 'Introduce yourself.']
            ],
            'temperature' => 0.7,
            'max_tokens' => -1,
            'stream' => true // Включаем режим стриминга
        ];
        
        // Преобразуем в JSON
        $requestBody = json_encode($requestPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($requestBody === false) {
            die("Ошибка: Не удалось закодировать JSON\n");
        }
        
        echo "Отправляем запрос к: {$this->endpoint}\n";
        echo "Модель: {$this->model}\n";
        echo "Режим стриминга: ВКЛЮЧЕН\n\n";
        
        // Формируем заголовки
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->llmApiKey,
        ];
        
        echo "Заголовки:\n";
        foreach ($headers as $header) {
            echo "  $header\n";
        }
        echo "\n";
        
        // Создаем контекст для запроса
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $requestBody,
                'ignore_errors' => true,
                'timeout' => 60,
            ],
        ]);
        
        // Отправляем запрос и получаем потоковый ответ
        echo "Отправка запроса...\n";
        echo str_repeat("-", 50) . "\n";
        
        $response = fopen($this->endpoint, 'r', false, $context);
        if ($response === false) {
            die("Ошибка: Не удалось получить ответ от сервера\n");
        }
        
        // Обрабатываем потоковый ответ
        $this->processStream($response);
        
        echo "\n" . str_repeat("-", 50) . "\n";
        echo "Стриминг завершен.\n";
        
        fclose($response);
    }
    
    private function processStream($response): void
    {
        $buffer = '';
        $fullContent = '';
        
        while (!feof($response)) {
            $chunk = fread($response, 8192); // Читаем по 8КБ
            if ($chunk === false) {
                break;
            }
            
            $buffer .= $chunk;
            
            // Обрабатываем буфер построчно
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                
                // Обрабатываем Server-Sent Event
                if (str_starts_with($line, 'data: ')) {
                    $data = substr($line, 6); // Убираем префикс "data: "
                    
                    // Проверяем, является ли это концом потока
                    if ($data === '[DONE]') {
                        echo "\n\n=== КОНЕЦ ПОТОКА ===\n";
                        return;
                    }
                    
                    // Пытаемся декодировать JSON
                    $jsonData = json_decode($data, true);
                    if (is_array($jsonData) && isset($jsonData['choices'][0]['delta']['content'])) {
                        $content = $jsonData['choices'][0]['delta']['content'];
                        echo $content;
                        $fullContent .= $content;
                        
                        // Принудительно выводим в браузер/консоль
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                }
            }
        }
        
        // Выводим оставшийся буфер
        if (!empty($buffer)) {
            echo $buffer;
        }
    }
}

// Запускаем тест
$tester = new LLMStreamTester();
$tester->testStreaming();
