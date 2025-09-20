#!/bin/bash

# Скрипт для тестирования режима стриминга ответа LLM через cURL
# Использование: ./testStreaming.sh [API_KEY]

API_KEY=${1:-123456789}  # По умолчанию используем тестовый ключ
ENDPOINT="http://193.104.69.173/v1/chat/completions"
MODEL="qwen/qwen3-30b-a3b-2507"

echo "Отправляем запрос к: $ENDPOINT"
echo "Ключ API: $API_KEY"
echo ""

# Отправляем запрос с режимом стриминга
curl -N -s -H "Content-Type: application/json" \
        -H "Authorization: Bearer $API_KEY" \
        -d "{
            \"model\": \"$MODEL\",
            \"messages\": [
                { \"role\": \"system\", \"content\": \"Always answer in rhymes.\" },
                { \"role\": \"user\", \"content\": \"Introduce yourself.\" }
            ],
            \"temperature\": 0.7,
            \"max_tokens\": -1,
            \"stream\": true
        }" \
        "$ENDPOINT"
        
echo ""
echo ""
echo "Стриминг завершен."