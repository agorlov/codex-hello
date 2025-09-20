# Эксперименты со стримингом ответов LLM

Эта директория содержит скрипты и документацию для экспериментов со стримингом ответов от LLM API.

## Структура директории

- `testStreaming.php` - Простой PHP-скрипт для тестирования стриминга
- `testStreamingAdvanced.php` - Продвинутый PHP-скрипт с обработкой Server-Sent Events
- `testStreaming.sh` - Bash-скрипт с использованием cURL
- `TEST_STREAMING.md` - Документация по тестовым скриптам
- `README.md` - Этот файл

## Форматы стриминга

### Server-Sent Events (SSE)

LLM API использует формат Server-Sent Events для стриминга ответов. Каждое событие имеет следующую структуру:

```
data: {JSON-объект}
```

Где JSON-объект содержит:
- `id` - Идентификатор чата
- `object` - Тип объекта (`chat.completion.chunk`)
- `created` - Временная метка создания
- `model` - Используемая модель
- `system_fingerprint` - Отпечаток системы
- `choices` - Массив вариантов ответа, содержащий:
  - `index` - Индекс варианта
  - `delta` - Дельта изменений, содержащая:
    - `role` - Роль (в первом сообщении)
    - `content` - Часть содержимого ответа
  - `logprobs` - Логарифмические вероятности (null)
  - `finish_reason` - Причина завершения (null или "stop")

Последнее сообщение содержит пустую дельту и `finish_reason: "stop"`.

### Завершение потока

Поток завершается сообщением:
```
data: [DONE]
```

## Выводы по экспериментам

### 1. Работа с потоком данных
- Ответы приходят по частям в режиме реального времени
- Каждая часть содержит небольшой фрагмент текста
- Необходимо правильно обрабатывать формат SSE для сборки полного ответа

### 2. Преимущества стриминга
- Пользователь сразу видит начало ответа, не дожидаясь полной генерации
- Создается эффект "живого" общения
- Уменьшается perceived latency (ощущаемая задержка)

### 3. Особенности реализации
- Необходимо использовать заголовок `Accept: text/event-stream` (для cURL)
- Тело запроса должно содержать параметр `stream: true`
- Важно правильно обрабатывать закрытие соединения

## Интеграция в веб-приложение

### Frontend (JavaScript)

Для интеграции в веб-приложение можно использовать EventSource API:

```javascript
const eventSource = new EventSource('/chat/stream');

eventSource.onmessage = function(event) {
    const data = JSON.parse(event.data);
    // Обработка данных
};

eventSource.onerror = function(event) {
    // Обработка ошибок
    eventSource.close();
};
```

Или fetch API с ReadableStream:

```javascript
fetch('/chat/stream', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        // параметры запроса
    })
}).then(response => {
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    
    function read() {
        reader.read().then(({done, value}) => {
            if (done) {
                // Поток завершен
                return;
            }
            
            const chunk = decoder.decode(value);
            // Обработка chunk
            read();
        });
    }
    
    read();
});
```

### Backend (PHP)

На стороне сервера необходимо:
1. Пересылать запрос к LLM API с параметром `stream: true`
2. Перенаправлять потоковые данные клиенту
3. Установить правильные заголовки для SSE

```php
// Установка заголовков для SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');

// Отправка данных клиенту
echo "data: " . json_encode($data) . "\n\n";
flush();
```

## Рекомендации для интеграции

1. **Используйте продвинутую обработку SSE** - как в `testStreamingAdvanced.php`
2. **Обрабатывайте ошибки соединения** - реализуйте механизм переподключения
3. **Оптимизируйте отображение** - показывайте ответ по мере поступления
4. **Учитывайте разрывы соединения** - добавьте таймауты и повторные попытки
5. **Тестируйте в разных браузерах** - SSE может работать по-разному

## Запуск тестов

Для запуска тестов в Docker:

```bash
# Скопировать файлы в контейнер
docker compose cp streaming-experiments/. app:/app/streaming-experiments/

# Запустить простой тест
docker compose run --rm app php /app/streaming-experiments/testStreaming.php

# Запустить продвинутый тест
docker compose run --rm app php /app/streaming-experiments/testStreamingAdvanced.php

# Запустить bash-скрипт
docker compose run --rm app chmod +x /app/streaming-experiments/testStreaming.sh
docker compose run --rm app /app/streaming-experiments/testStreaming.sh
```