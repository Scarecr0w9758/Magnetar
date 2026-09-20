<?php
// ============================================
// ТЕСТ ПОДКЛЮЧЕНИЯ К БД
// ВРЕМЕННЫЙ ФАЙЛ — УДАЛИТЬ ПОСЛЕ ПРОВЕРКИ
// ============================================

$dbHost = 'localhost';
$dbName = 'u0678442_magnetar_requests';
$dbUser = 'u0678442_admin';
$dbPass = 'admin975863142';

header('Content-Type: text/plain; charset=utf-8');

try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    echo "✅ Подключение к БД успешно!\n\n";

    // Проверяем таблицу
    $stmt = $pdo->query("SHOW TABLES LIKE 'requests'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Таблица `requests` существует.\n\n";

        $count = $pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn();
        echo "📊 Заявок в таблице: {$count}\n\n";

        echo "Структура таблицы:\n";
        $cols = $pdo->query("DESCRIBE requests")->fetchAll();
        foreach ($cols as $c) {
            echo "  - {$c['Field']} ({$c['Type']})\n";
        }
    } else {
        echo "⚠️ Таблица `requests` НЕ найдена.\n";
        echo "Выполните CREATE TABLE через phpMyAdmin.\n";
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo "❌ Ошибка подключения:\n";
    echo $e->getMessage() . "\n\n";
    echo "Проверьте:\n";
    echo "  - Хост (обычно localhost)\n";
    echo "  - Имя БД: u0678442_magnetar_requests\n";
    echo "  - Пользователь: u0678442_admin\n";
    echo "  - Пароль\n";
}