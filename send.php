<?php
// ============================================
// ОТКЛЮЧАЕМ ВЫВОД ОШИБОК В ОТВЕТ
// ============================================
ini_set('display_errors', '0');
error_reporting(E_ALL);

// ============================================
// CORS — САМОЕ ПЕРВОЕ, ЧТО ОТПРАВЛЯЕТСЯ
// ============================================
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit(0);
}

// ============================================
// НАСТРОЙКИ БД
// ============================================
const DB_HOST = 'localhost';
const DB_NAME = 'u0678442_magnetar_requests';
const DB_USER = 'u0678442_admin';
const DB_PASS = 'admin975863142';

// ============================================
// НАСТРОЙКИ VK
// ============================================
const VK_TOKEN = 'vk1.a.zpz0icvQ_Z2XBG1xEvjc7CF62Tf4FhKEy_jwJFYtxOKFiYEaLR9RW4NATo1NbW42gZ9vkb6HiAdiqB09tOG5nGNftQ5GcuaP1x_8FJTMcIYBxoJ80U-j27p3jn6vgzx35cLaq06YWQ4bM2tTY_aO4Yn_sWXVRHVHwnqGCXjLr92uyiQUKgbk-rloK_fkD60kJAQYLAV50RY36NgSV0I5WA';
const VK_RECIPIENT_ID = '151696090';
const VK_API_VERSION = '5.199';

// ============================================
// ПРИЁМ ДАННЫХ
// ============================================
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Некорректные данные']);
    exit;
}

$name    = trim($input['name']    ?? '');
$phone   = trim($input['phone']   ?? '');
$email   = trim($input['email']   ?? '');
$message = trim($input['message'] ?? '');

// Способ связи (phone / email / vk / tg / whatsapp)
$contactMethod = trim($input['contact_method'] ?? 'phone');
$allowedMethods = ['phone', 'email', 'vk', 'tg', 'whatsapp'];
if (!in_array($contactMethod, $allowedMethods, true)) {
    $contactMethod = 'phone';
}

// Определяем тип контакта (по какому полю заполнено)
$contactType = !empty($phone) ? 'phone' : 'email';

// Валидация
if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Укажите имя']);
    exit;
}
if ($contactType === 'phone' && empty($phone)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Укажите телефон']);
    exit;
}
if ($contactType === 'email' && (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Укажите корректный email']);
    exit;
}

// IP клиента
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
if ($ip && strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}

// ============================================
// ПОДКЛЮЧЕНИЕ К БД
// ============================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Ошибка сервера. Позвоните нам: 8 (351) 211-27-70',
    ]);
    exit;
}

// ============================================
// СОХРАНЕНИЕ В БД
// ============================================
$requestId = null;
try {
    $stmt = $pdo->prepare("
        INSERT INTO requests (name, phone, email, message, contact_type, contact_method, ip)
        VALUES (:name, :phone, :email, :message, :contact_type, :contact_method, :ip)
    ");
    $stmt->execute([
        ':name'           => $name,
        ':phone'          => $phone ?: null,
        ':email'          => $email ?: null,
        ':message'        => $message ?: null,
        ':contact_type'   => $contactType,
        ':contact_method' => $contactMethod,
        ':ip'             => $ip,
    ]);
    $requestId = (int)$pdo->lastInsertId();
} catch (PDOException $e) {
    error_log('DB insert failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Не удалось сохранить заявку. Позвоните нам: 8 (351) 211-27-70',
    ]);
    exit;
}

// ============================================
// ФОРМИРУЕМ ТЕКСТ ДЛЯ УВЕДОМЛЕНИЯ
// ============================================
$text  = "🔔 Новая заявка #{$requestId}\n\n";
$text .= "👤 Имя: {$name}\n";
if ($phone)   $text .= "📞 Телефон: {$phone}\n";
if ($email)   $text .= "✉️ Email: {$email}\n";

// Способ связи для уведомления
$methodLabels = [
    'phone'    => 'Телефон',
    'email'    => 'Email',
    'vk'       => 'VK',
    'tg'       => 'Telegram',
    'whatsapp' => 'WhatsApp',
];
$text .= "📬 Способ связи: " . ($methodLabels[$contactMethod] ?? $contactMethod) . "\n";

if ($message) $text .= "💬 Комментарий: {$message}\n";
$text .= "\n📅 " . date('d.m.Y H:i');

// ============================================
// ОТПРАВКА В VK (не критично, если упадёт)
// ============================================
$vkSent  = false;
$vkError = null;

$postFields = http_build_query([
    'access_token' => VK_TOKEN,
    'v'            => VK_API_VERSION,
    'peer_id'      => VK_RECIPIENT_ID,
    'message'      => $text,
    'random_id'    => random_int(1, PHP_INT_MAX),
]);

$ch = curl_init('https://api.vk.com/method/messages.send');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS     => $postFields,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
]);

$vkResponse = curl_exec($ch);
$vkCurlErr  = curl_error($ch);
$vkHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// curl_close() не нужен в PHP 8+

if ($vkCurlErr) {
    $vkError = 'cURL: ' . $vkCurlErr;
    error_log('VK cURL error: ' . $vkCurlErr);
} else {
    $vkResult = json_decode($vkResponse, true);
    if (isset($vkResult['response'])) {
        $vkSent = true;
    } elseif (isset($vkResult['error'])) {
        $vkError = 'VK ' . ($vkResult['error']['error_code'] ?? '?')
                 . ': ' . ($vkResult['error']['error_msg'] ?? 'unknown');
        error_log('VK error: ' . $vkError);
    } else {
        $vkError = 'Unexpected VK response (HTTP ' . $vkHttpCode . ')';
        error_log('VK unexpected: ' . substr((string)$vkResponse, 0, 200));
    }
}

// ============================================
// ОБНОВЛЯЕМ ФЛАГ notified (если ушло)
// ============================================
if ($vkSent) {
    try {
        $pdo->prepare("UPDATE requests SET notified = 1 WHERE id = ?")
            ->execute([$requestId]);
    } catch (PDOException $e) {
        error_log('DB update notified failed: ' . $e->getMessage());
    }
}

// ============================================
// ОТВЕТ КЛИЕНТУ
// ============================================
echo json_encode([
    'success'    => true,
    'request_id' => $requestId,
    'vk_sent'    => $vkSent,
    'vk_error'   => $vkError,
]);