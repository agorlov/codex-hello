/* Скрипт фронтенда для стриминговой версии LLM-чата. */

(function () {
    'use strict';

    function parseSseEvent(rawEvent) {
        const lines = rawEvent.split(/\n/);
        const dataParts = [];

        for (let i = 0; i < lines.length; i += 1) {
            const originalLine = lines[i];
            if (!originalLine) {
                continue;
            }

            const line = originalLine.replace(/\r$/, '');
            if (line.startsWith(':')) {
                continue;
            }

            if (line.startsWith('data:')) {
                dataParts.push(line.slice(5).trimStart());
            }
        }

        if (dataParts.length === 0) {
            return null;
        }

        const payload = dataParts.join('\n').trim();
        if (payload === '') {
            return null;
        }

        try {
            return JSON.parse(payload);
        } catch (error) {
            return null;
        }
    }

    async function startStreamingRequest(url, payload, handlers) {
        const onToken = handlers.onToken || function () {};
        const onDone = handlers.onDone || function () {};
        const onError = handlers.onError || function () {};

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'text/event-stream',
            },
            body: JSON.stringify(payload),
        });

        if (!response.ok) {
            let message = `Ошибка обращения к модели (код ${response.status}).`;

            try {
                const data = await response.json();
                if (data && typeof data.error === 'string') {
                    message = data.error;
                }
            } catch (error) {
                // Игнорируем ошибки парсинга ответа.
            }

            onError(message);

            return;
        }

        if (!response.body) {
            onError('Потоковый ответ недоступен.');

            return;
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let shouldStop = false;
        let hasEnded = false;

        const finishWithDone = function () {
            if (hasEnded) {
                return;
            }

            hasEnded = true;
            onDone();
        };

        const finishWithError = function (message) {
            if (hasEnded) {
                return;
            }

            hasEnded = true;
            onError(message);
        };

        const handleRawEvent = function (rawEvent) {
            const parsed = parseSseEvent(rawEvent);
            if (!parsed) {
                return;
            }

            const eventType = typeof parsed.event === 'string' ? parsed.event : 'token';

            if (eventType === 'token') {
                const text = typeof parsed.text === 'string' ? parsed.text : '';
                if (text !== '' && !hasEnded) {
                    onToken(text);
                }

                return;
            }

            if (eventType === 'error') {
                const message = typeof parsed.message === 'string'
                    ? parsed.message
                    : 'Модель завершила поток с ошибкой.';
                finishWithError(message);
                shouldStop = true;

                return;
            }

            if (eventType === 'done') {
                finishWithDone();
                shouldStop = true;
            }
        };

        const processBuffer = function (textBuffer) {
            let workingBuffer = textBuffer;

            while (!shouldStop) {
                const separatorPosition = workingBuffer.indexOf('\n\n');
                if (separatorPosition === -1) {
                    break;
                }

                const rawEvent = workingBuffer.slice(0, separatorPosition);
                workingBuffer = workingBuffer.slice(separatorPosition + 2);

                if (rawEvent.trim() === '') {
                    continue;
                }

                handleRawEvent(rawEvent);
            }

            return workingBuffer;
        };

        try {
            while (!shouldStop) {
                const { value, done } = await reader.read();
                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });
                buffer = processBuffer(buffer);
            }

            buffer += decoder.decode();
            buffer = processBuffer(buffer);

            if (!shouldStop) {
                const remaining = buffer.trim();
                if (remaining !== '') {
                    handleRawEvent(remaining);
                }
            }

            if (!hasEnded) {
                finishWithDone();
            }
        } finally {
            shouldStop = true;

            try {
                await reader.cancel();
            } catch (error) {
                // Проглатываем ошибки при отмене чтения.
            }
        }
    }

    function initializeStreamingChat() {
        const form = document.getElementById('llm-chat-stream-form');
        const input = document.getElementById('llm-chat-stream-input');
        const messagesContainer = document.getElementById('llm-chat-stream-messages');
        const statusElement = document.getElementById('llm-chat-stream-status');

        if (!form || !input || !messagesContainer) {
            return;
        }

        if (form.dataset.enhanced === '1') {
            return;
        }
        form.dataset.enhanced = '1';

        const submitButton = form.querySelector('button[type="submit"]');
        const streamUrl = form.dataset.streamUrl || '';
        const systemMessage = form.dataset.systemMessage || '';
        const isConfigured = form.dataset.configured === '1';

        const baseStatusClasses = statusElement
            ? statusElement.className
                  .split(/\s+/)
                  .filter((className) => className !== '' && (className === 'text-xs' || !className.startsWith('text-')))
                  .join(' ')
            : '';

        const colors = {
            info: 'text-slate-400',
            success: 'text-sky-300',
            warning: 'text-amber-300',
            error: 'text-rose-300',
        };

        const conversation = [];
        if (systemMessage !== '') {
            conversation.push({ role: 'system', content: systemMessage });
        }

        const createBubble = function (role) {
            const bubble = document.createElement('div');
            bubble.className =
                role === 'user'
                    ? 'self-end max-w-xl rounded-2xl border border-slate-700/80 bg-slate-800/80 px-4 py-3 text-slate-100 shadow'
                    : 'self-start max-w-xl rounded-2xl border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-slate-100 shadow';

            return bubble;
        };

        const updateBubbleText = function (bubble, text) {
            while (bubble.firstChild) {
                bubble.removeChild(bubble.firstChild);
            }

            const segments = String(text).split(/\n/);
            segments.forEach((segment, index) => {
                if (index > 0) {
                    bubble.appendChild(document.createElement('br'));
                }

                bubble.appendChild(document.createTextNode(segment));
            });
        };

        const setStatus = function (message, type) {
            if (!statusElement) {
                return;
            }

            const tone = colors[type] || colors.info;
            const classes = [baseStatusClasses, tone].filter(Boolean).join(' ');
            statusElement.className = classes;
            statusElement.textContent = message;
        };

        const setPending = function (isPending) {
            const shouldDisable = isPending || !isConfigured;

            if (submitButton) {
                submitButton.disabled = shouldDisable;
                submitButton.setAttribute('aria-busy', isPending ? 'true' : 'false');
            }

            input.disabled = shouldDisable;

            if (isPending) {
                setStatus('Модель печатает ответ…', 'info');
            }
        };

        if (!isConfigured) {
            setStatus('Добавьте переменную окружения LLM_KEY, чтобы активировать чат.', 'warning');

            return;
        }

        if (streamUrl === '') {
            setStatus('Потоковый endpoint не настроен.', 'error');

            return;
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const text = input.value.trim();
            if (text === '') {
                setStatus('Введите сообщение для модели.', 'warning');

                return;
            }

            const userBubble = createBubble('user');
            updateBubbleText(userBubble, text);
            messagesContainer.appendChild(userBubble);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            conversation.push({ role: 'user', content: text });
            input.value = '';

            const assistantBubble = createBubble('assistant');
            messagesContainer.appendChild(assistantBubble);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            let assistantText = '';
            let streamActive = true;

            setPending(true);
            setStatus('Модель печатает ответ…', 'info');

            startStreamingRequest(
                streamUrl,
                { messages: conversation },
                {
                    onToken: (chunk) => {
                        if (!streamActive || chunk === '') {
                            return;
                        }

                        assistantText += chunk;
                        updateBubbleText(assistantBubble, assistantText);
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    },
                    onDone: () => {
                        if (!streamActive) {
                            return;
                        }

                        streamActive = false;
                        setPending(false);

                        const trimmed = assistantText.trim();
                        if (trimmed === '') {
                            setStatus('Ответ модели пустой.', 'warning');

                            return;
                        }

                        conversation.push({ role: 'assistant', content: trimmed });
                        setStatus('Ответ получен.', 'success');
                    },
                    onError: (message) => {
                        if (!streamActive) {
                            return;
                        }

                        streamActive = false;
                        setPending(false);

                        if (assistantText === '') {
                            assistantBubble.classList.add('border-rose-500/40', 'bg-rose-500/10', 'text-rose-100');
                            updateBubbleText(assistantBubble, 'Ответ не был получен из-за ошибки.');
                        }

                        setStatus(message, 'error');
                    },
                },
            ).catch(() => {
                if (!streamActive) {
                    return;
                }

                streamActive = false;
                setPending(false);

                if (assistantText === '') {
                    assistantBubble.classList.add('border-rose-500/40', 'bg-rose-500/10', 'text-rose-100');
                    updateBubbleText(assistantBubble, 'Не удалось связаться с моделью.');
                }

                setStatus('Не удалось отправить запрос. Проверьте подключение.', 'error');
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeStreamingChat, { once: true });
    } else {
        initializeStreamingChat();
    }
})();
