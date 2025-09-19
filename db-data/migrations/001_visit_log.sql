-- Создаёт таблицу visit_log для хранения обращений к приветственной странице.
CREATE TABLE IF NOT EXISTS visit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT, -- Уникальный идентификатор записи журнала.
    visited_at TEXT NOT NULL, -- Момент фиксации визита в формате YYYY-MM-DD HH:MM:SS.
    route TEXT NOT NULL, -- Имя маршрута, на который пришёл запрос.
    language TEXT NOT NULL, -- Код языка приветствия, показанного посетителю.
    path TEXT NOT NULL, -- URI-путь запроса без query string.
    query TEXT NULL, -- Строка запроса, если посетитель передал параметры.
    referer TEXT NULL, -- Адрес страницы-источника перехода, если известен.
    ip_address TEXT NULL, -- IP-адрес клиента, согласно HTTP-заголовкам.
    user_agent TEXT NULL -- Строка User-Agent браузера посетителя.
);
