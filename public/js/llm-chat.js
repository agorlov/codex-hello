/* Скрипт для интерфейса LLM-чата на фронтенде. */

(function () {
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('llm-chat-form');
        const input = document.getElementById('llm-chat-input');
        const messagesContainer = document.getElementById('llm-chat-messages');
        const statusElement = document.getElementById('llm-chat-status');
        const submitButton = form ? form.querySelector('button[type="submit"]') : null;

        if (!form || !input || !messagesContainer) {
            return;
        }

        const baseStatusClasses = statusElement
            ? statusElement.className
                  .split(/\s+/)
                  .filter((className) => className !== '' && (className === 'text-xs' || !className.startsWith('text-')))
                  .join(' ')
            : '';

        const isConfigured = form.dataset.configured === '1';
        const systemMessage = form.dataset.systemMessage || '';
        const conversation = [];

        if (systemMessage !== '') {
            conversation.push({ role: 'system', content: systemMessage });
        }

        const colors = {
            info: 'text-slate-400',
            success: 'text-sky-300',
            warning: 'text-amber-300',
            error: 'text-rose-300',
        };

        function setStatus(message, type = 'info') {
            if (!statusElement) {
                return;
            }

            const colorClass = colors[type] ?? colors.info;
            const classes = [baseStatusClasses, colorClass].filter(Boolean).join(' ');
            statusElement.className = classes;
            statusElement.textContent = message;
        }

        function appendMessage(role, content) {
            if (!messagesContainer) {
                return;
            }

            const bubble = document.createElement('div');
            bubble.className =
                role === 'user'
                    ? 'self-end max-w-xl rounded-2xl border border-slate-700/80 bg-slate-800/80 px-4 py-3 text-slate-100 shadow'
                    : 'self-start max-w-xl rounded-2xl border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-slate-100 shadow';

            const paragraphs = String(content).split(/\n/);
            paragraphs.forEach((line, index) => {
                if (index > 0) {
                    bubble.appendChild(document.createElement('br'));
                }
                bubble.appendChild(document.createTextNode(line));
            });

            messagesContainer.appendChild(bubble);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function setPending(isPending) {
            const shouldDisable = isPending || !isConfigured;

            if (submitButton) {
                submitButton.disabled = shouldDisable;
                submitButton.setAttribute('aria-busy', isPending ? 'true' : 'false');
            }

            if (input) {
                input.disabled = shouldDisable;
            }

            if (isPending) {
                setStatus('Модель отвечает…', 'info');
            }
        }

        if (!isConfigured) {
            setStatus('Добавьте переменную окружения LLM_KEY, чтобы активировать чат.', 'warning');
            return;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const text = input.value.trim();
            if (text === '') {
                setStatus('Введите сообщение для модели.', 'warning');
                return;
            }

            appendMessage('user', text);
            conversation.push({ role: 'user', content: text });

            input.value = '';
            setPending(true);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ messages: conversation }),
                });

                const payload = await response.json().catch(() => null);

                if (!response.ok) {
                    const errorMessage = payload && typeof payload.error === 'string'
                        ? payload.error
                        : `Ошибка обращения к модели (код ${response.status}).`;
                    setStatus(errorMessage, 'error');
                    return;
                }

                const assistantText = payload && typeof payload.message === 'string' ? payload.message.trim() : '';

                if (assistantText === '') {
                    setStatus('Ответ модели пустой.', 'warning');
                    return;
                }

                appendMessage('assistant', assistantText);
                conversation.push({ role: 'assistant', content: assistantText });
                setStatus('Ответ получен.', 'success');
            } catch (error) {
                setStatus('Не удалось отправить запрос. Проверьте подключение.', 'error');
            } finally {
                setPending(false);
            }
        });
    });
})();
