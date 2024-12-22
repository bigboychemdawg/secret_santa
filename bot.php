<?php

require 'vendor/autoload.php';

use TelegramBot\Api\BotApi;

$telegram = new BotApi(getenv('BOT_API_KEY'));
$db = new SQLite3(__DIR__ . '/data/bot.db');

// Проверка переменных окружения
$botUsername = getenv('BOT_USERNAME');
if (!$botUsername) {
    exit('Ошибка: BOT_USERNAME не задан в переменных окружения.');
}

$webhookUrl = getenv('BOT_WEBHOOK_URL');
$telegram->setWebhook($webhookUrl);

// Создание таблицы для инвайтов
$db->exec("CREATE TABLE IF NOT EXISTS invites (
    table_name TEXT PRIMARY KEY,
    random_text TEXT,
    creator_tg_id INTEGER,
    creator_tg_name TEXT
)");

// Получение данных из вебхука
$input = file_get_contents('php://input');
http_response_code(200);

if (!$input) {
    exit('Некорректные данные вебхука.');
}

$update = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE || !isset($update['message'])) {
    exit('Некорректные данные вебхука.');
}

// Обработка сообщения
$message = $update['message'];
$chatId = $message['chat']['id'];
$text = $message['text'];
$name = $message['from']['username'] ?? 'Гость';

// Обработка команды /start
if (strpos($text, '/start') === 0) {
    handleStartCommand($telegram, $db, $text, $chatId, $name);
} elseif (strpos($text, '/create') === 0) {
    handleCreateCommand($telegram, $db, $text, $chatId, $name, $botUsername);
} else {
    $telegram->sendMessage($chatId, 'Неизвестная команда.');
}

// Обработка команды /start
function handleStartCommand($telegram, $db, $text, $chatId, $name)
{
    if (preg_match('/\/start\s+(\S+)/', $text, $matches)) {
        $randomText = $matches[1];

        // Проверяем, существует ли инвайт
        $stmt = $db->prepare("SELECT table_name FROM invites WHERE random_text = :random_text");
        $stmt->bindValue(':random_text', $randomText, SQLITE3_TEXT);
        $result = $stmt->execute()->fetchArray();

        if ($result) {
            $tableName = $result['table_name'];

            // Извлекаем дату и время из названия таблицы
            $formattedDateTime = extractDateTimeFromTableName($tableName);

            // Проверяем, существует ли пользователь в таблице события
            $checkUserStmt = $db->prepare("SELECT id FROM [$tableName] WHERE tg_name = :tg_name");
            $checkUserStmt->bindValue(':tg_name', $name, SQLITE3_TEXT);
            $userExists = $checkUserStmt->execute()->fetchArray();

            if ($userExists) {
                $telegram->sendMessage($chatId, 'Вы уже зарегистрированы в этом событии.');
                return;
            }

            // Получаем владельца события
            $ownerName = getEventOwner($db, $tableName);

            // Добавляем пользователя в событие
            $query = "INSERT INTO [$tableName] (tg_name, tg_id, status, created_at) VALUES (:tg_name, :tg_id, 'user', datetime('now'))";
            $insertStmt = $db->prepare($query);
            $insertStmt->bindValue(':tg_name', $name, SQLITE3_TEXT);
            $insertStmt->bindValue(':tg_id', $chatId, SQLITE3_INTEGER);

            if ($insertStmt->execute()) {
                $telegram->sendMessage(
                    $chatId,
                    "⚡️ @$ownerName добавил вас в Тайного Санту!\nЖеребьевка произойдет $formattedDateTime."
                );
            } else {
                $telegram->sendMessage($chatId, 'Ошибка: не удалось добавить вас в событие. Попробуйте позже.');
            }
        } else {
            $telegram->sendMessage($chatId, 'Неверная инвайт-ссылка или событие не существует.');
        }
    } else {
        $telegram->sendMessage($chatId, 'Добро пожаловать! Используйте /create дд.мм.гггг_чч:мм для создания события.');
    }
}

// Обработка команды /create
function handleCreateCommand($telegram, $db, $text, $chatId, $name, $botUsername)
{
    if (preg_match('/^\/create\s+(\S+)/', $text, $matches)) {
        $inputText = $matches[1];

        if (preg_match('/^\d{2}\.\d{2}\.\d{4}_\d{2}[:.]\d{2}$/', $inputText)) {
            // Приведение времени к стандартному формату
            $inputText = preg_replace('/(\d{2})[.](\d{2})$/', '$1:$2', $inputText);
            $randomText = bin2hex(random_bytes(5));
            $tableName = 'event_' . str_replace(['.', ':'], '_', $inputText) . '_' . $randomText;

            // Создание таблицы
            $query = "CREATE TABLE IF NOT EXISTS [$tableName] (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tg_name TEXT,
                tg_id INTEGER,
                status TEXT,
                created_at TEXT
            )";
            if ($db->exec($query)) {
                $db->exec("INSERT INTO [$tableName] (tg_name, tg_id, status, created_at) VALUES ('$name', '$chatId', 'owner', datetime('now'))");
                $db->exec("INSERT INTO invites (table_name, random_text, creator_tg_id, creator_tg_name) VALUES ('$tableName', '$randomText', '$chatId', '$name')");
                $telegram->sendMessage($chatId, "Событие создано. Инвайт-ссылка: https://t.me/$botUsername?start=$randomText");
            } else {
                $telegram->sendMessage($chatId, 'Ошибка при создании таблицы. Попробуйте позже.');
            }
        } else {
            $telegram->sendMessage($chatId, 'Ошибка: Неверный формат имени таблицы. Укажите дату и время в формате: дд.мм.гггг_чч:мм');
        }
    } else {
        $telegram->sendMessage($chatId, 'Ошибка: команда должна быть в формате /create дд.мм.гггг_чч:мм');
    }
}

// Извлечение даты и времени из имени таблицы
function extractDateTimeFromTableName($tableName)
{
    if (preg_match('/event_(\d{2}_\d{2}_\d{4}_\d{2}_\d{2})/', $tableName, $matches)) {
        $dateTimeString = str_replace('_', '.', $matches[1]);
        $dateTimeString = preg_replace('/(\d{2})\.(\d{2})\.(\d{4})\.(\d{2})\.(\d{2})/', '$1.$2.$3 $4:$5', $dateTimeString);
        $dateTime = DateTime::createFromFormat('d.m.Y H:i', $dateTimeString);

        return $dateTime ? $dateTime->format('d.m.Y в H:i') : 'неизвестное время';
    }
    return 'неизвестное время';
}

// Получение владельца события
function getEventOwner($db, $tableName)
{
    $ownerQuery = "SELECT tg_name FROM [$tableName] WHERE status = 'owner' LIMIT 1";
    $ownerResult = $db->querySingle($ownerQuery, true);
    return $ownerResult['tg_name'] ?? 'неизвестный владелец';
}
