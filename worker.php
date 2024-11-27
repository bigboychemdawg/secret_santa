<?php

require 'vendor/autoload.php';

use TelegramBot\Api\BotApi;

// Установка часового пояса
$timezone = getenv('TZ') ?: 'UTC';
date_default_timezone_set($timezone);

$telegram = new BotApi(getenv('BOT_API_KEY'));
$db = new SQLite3(__DIR__ . '/data/bot.db');

// Проверка подключения к базе данных
if (!$db) {
    exit("Ошибка подключения к базе данных: " . $db->lastErrorMsg() . "\n");
}

echo "Worker запущен. Текущий часовой пояс: $timezone\n";

while (true) {
    processEventTables($db, $telegram);
    sleep(10);
}

// Обрабокта таблиц событий
function processEventTables($db, $telegram)
{
    $tables = getEventTables($db);
    if (empty($tables)) {
        echo "Нет таблиц для обработки. Продолжаем цикл.\n";
        return;
    }

    foreach ($tables as $tableName) {
        $dateTime = extractDateTimeFromTableName($tableName);
        if (!$dateTime) {
            echo "Таблица $tableName имеет неверный формат времени.\n";
            continue;
        }

        $now = new DateTime();
        echo "Сейчас: " . $now->format('Y-m-d H:i:s') . "\n";
        echo "Время события: " . $dateTime->format('Y-m-d H:i:s') . "\n";

        if ($now >= $dateTime) {
            echo "Время события достигнуто. Запускается жеребьевка для таблицы $tableName\n";
            runSecretSanta($db, $telegram, $tableName);
        } else {
            echo "Событие $tableName пока не наступило. Ожидаем времени.\n";
        }
    }
}

// Получение списка таблиц событий
function getEventTables($db)
{
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'event_%'");
    $tables = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tables[] = $row['name'];
    }
    return $tables;
}

// Извлечение даты и времени из имени таблицы
function extractDateTimeFromTableName($tableName)
{
    if (preg_match('/^event_(\d{2}_\d{2}_\d{4}_\d{2}_\d{2})/', $tableName, $matches)) {
        $dateTimeString = str_replace('_', '.', $matches[1]);
        $dateTimeString = preg_replace('/(\d{2})\.(\d{2})\.(\d{4})\.(\d{2})\.(\d{2})/', '$1.$2.$3 $4:$5', $dateTimeString);
        return DateTime::createFromFormat('d.m.Y H:i', $dateTimeString);
    }
    return null;
}

// Выполнение жеребьевки и отправка результатов
function runSecretSanta($db, $telegram, $tableName)
{
    $participants = getParticipants($db, $tableName);
    if (count($participants) < 2) {
        echo "Недостаточно участников для жеребьевки в таблице $tableName\n";
        return;
    }

    // Таймер обратного отсчета
    for ($i = 10; $i > 0; $i--) {
        foreach ($participants as $participant) {
            $telegram->sendMessage($participant['tg_id'], "$i...");
        }
        sleep(1);
    }

    // Выполнение жеребьевки
    echo "Жеребьевка для таблицы $tableName началась.\n";
    shuffle($participants);
    sendSecretSantaResults($telegram, $participants);

    // Переименование таблицы
    renameTable($db, $tableName);
}

// Получение списка участников
function getParticipants($db, $tableName)
{
    $query = $db->query("SELECT tg_id, tg_name FROM [$tableName]");
    $participants = [];
    while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
        $participants[] = $row;
    }
    return $participants;
}

// Отправление результатов жеребьевки
function sendSecretSantaResults($telegram, $participants)
{
    $count = count($participants);
    for ($i = 0; $i < $count; $i++) {
        $giver = $participants[$i];
        $receiver = $participants[($i + 1) % $count];
        $telegram->sendMessage(
            $giver['tg_id'],
            "Ты санта для @{$receiver['tg_name']} 🌟🎄\n\n"
            . "Правила:\n"
            . "💰 Бюджет 200 RON\n"
            . "❌ Деньги не дарить\n"
            . "🤫 Никому не рассказывать о результатах жеребьевки"
        );
    }
}

// Переименовывание таблицы после завершения жеребьевки
function renameTable($db, $tableName)
{
    $newTableName = str_replace('event_', 'done_', $tableName);
    if ($db->exec("ALTER TABLE $tableName RENAME TO $newTableName")) {
        echo "Таблица $tableName переименована в $newTableName после жеребьевки.\n";
    } else {
        echo "Ошибка переименования таблицы $tableName: " . $db->lastErrorMsg() . "\n";
    }
}
