<?php

/**
 * Тестовый скрипт для проверки режима стриминга ответа LLM
 */

// Загружаем переменные окружения
$llmApiKey = getenv('LLM_KEY') ?: '123456789'; // Тестовый ключ
$endpoint = 'http://193.104.69.173/v1/chat/completions';
$model = 'qwen/qwen3-30b-a3b-2507';

// Параметры запроса
$requestPayload = [
    'model' => $model,
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

echo "Отправляем запрос к: $endpoint\n";
echo "Тело запроса: $requestBody\n\n";

// Формируем заголовки
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $llmApiKey,
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
echo "Отправка запроса с режимом стриминга...\n";
echo "Ответ:\n";
echo str_repeat("-", 50) . "\n";

$response = fopen($endpoint, 'r', false, $context);
if ($response === false) {
    die("Ошибка: Не удалось получить ответ от сервера\n");
}

// Читаем и выводим ответ по частям
while (!feof($response)) {
    $chunk = fread($response, 1024); // Читаем по 1КБ
    if ($chunk !== false) {
        echo $chunk;
        flush(); // Принудительно выводим в браузер/консоль
    }
}

echo "\n" . str_repeat("-", 50) . "\n";
echo "Стриминг завершен.\n";

fclose($response);