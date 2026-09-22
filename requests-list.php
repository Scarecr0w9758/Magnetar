<?php
// ============================================
// МАГНЕТАР — Список заявок с сайта (только просмотр)
// Файл: requests-list-x7k9p2m4.php
// ============================================

ini_set('display_errors', '0');
error_reporting(E_ALL);

// ============================================
// НАСТРОЙКИ ДОСТУПА
// ============================================
const AUTH_LOGIN    = 'magnetar';
const AUTH_PASSWORD = '9758';

// ============================================
// НАСТРОЙКИ БД
// ============================================
const DB_HOST = 'localhost';
const DB_NAME = 'u0678442_magnetar_requests';
const DB_USER = 'u0678442_admin';
const DB_PASS = 'admin975863142';

// ============================================
// BASIC AUTH
// ============================================
$authUser = $_SERVER['PHP_AUTH_USER'] ?? '';
$authPass = $_SERVER['PHP_AUTH_PW']   ?? '';

if ($authUser !== AUTH_LOGIN || $authPass !== AUTH_PASSWORD) {
    header('WWW-Authenticate: Basic realm="Magnetar Requests"');
    header('HTTP/1.0 401 Unauthorized');
    exit('Требуется авторизация');
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
    http_response_code(500);
    exit('Ошибка подключения к БД');
}

// ============================================
// ФИЛЬТР ПО СТАТУСУ (опционально, для удобства)
// ============================================
$statusFilter = $_GET['status'] ?? 'all';
$allowedStatuses = ['all', 'new', 'processed', 'spam'];
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

// ============================================
// ЭКСПОРТ В CSV
// ============================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="magnetar-requests-' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM для Excel

    fputcsv($out, ['ID', 'Дата', 'Имя', 'Телефон', 'Email', 'Способ связи', 'Комментарий', 'Статус', 'IP', 'VK']);

    $stmt = $pdo->query("SELECT * FROM requests ORDER BY created_at DESC");
    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['id'],
            $row['created_at'],
            $row['name'],
            $row['phone'],
            $row['email'],
            $row['contact_method'] ?? 'phone',
            $row['message'],
            $row['status'],
            $row['ip'],
            $row['notified'] ? 'Да' : 'Нет',
        ]);
    }
    fclose($out);
    exit;
}

// ============================================
// ПОЛУЧАЕМ ЗАЯВКИ
// ============================================
$sql = "SELECT * FROM requests";
$params = [];

if ($statusFilter !== 'all') {
    $sql .= " WHERE status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY created_at DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// ============================================
// СЧЁТЧИКИ
// ============================================
$counts = [
    'all'       => (int)$pdo->query("SELECT COUNT(*) FROM requests")->fetchColumn(),
    'new'       => (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'new'")->fetchColumn(),
    'processed' => (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'processed'")->fetchColumn(),
    'spam'      => (int)$pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'spam'")->fetchColumn(),
];

// Хелпер: человекочитаемый способ связи
function contactMethodLabel(?string $m): string {
    return match($m) {
        'vk'       => 'VK',
        'tg'       => 'Telegram',
        'whatsapp' => 'WhatsApp',
        'email'    => 'Email',
        'phone'    => 'Телефон',
        default    => '—',
    };
}

// Хелпер: иконка способа связи
function contactMethodIcon(?string $m): string {
    return match($m) {
        'vk'       => '🅥',
        'tg'       => '✈',
        'whatsapp' => '💬',
        'email'    => '✉',
        'phone'    => '📞',
        default    => '•',
    };
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title>Магнетар — Заявки с сайта</title>
  <style>
    :root {
      --color-primary: #f75903;
      --color-primary-hover: #d94d02;
      --color-dark: #303030;
      --color-black: #1a1a1a;
      --color-light: #fff;
      --color-gray: #b3b3b3;
      --color-gray-2: #d4d4d4;
      --glass-bg: rgba(255, 255, 255, 0.06);
      --glass-bg-strong: rgba(255, 255, 255, 0.12);
      --glass-border: rgba(255, 255, 255, 0.14);
      --radius-md: 14px;
      --radius-lg: 20px;
      --radius-pill: 999px;
      --font-base: 'PT Sans', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: var(--font-base);
      background: var(--color-dark);
      color: var(--color-light);
      line-height: 1.5;
      padding: 24px;
      min-height: 100vh;
    }

    .container { max-width: 1600px; margin: 0 auto; }

    h1 {
      font-size: 24px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 12px;
    }
    h1 span { color: var(--color-primary); }

    /* Счётчики */
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 12px;
      margin-bottom: 24px;
    }
    .stat-card {
      padding: 16px 20px;
      border-radius: var(--radius-md);
      background: var(--glass-bg);
      border: 1px solid var(--glass-border);
      text-align: center;
    }
    .stat-card__value {
      font-size: 28px;
      font-weight: 700;
      color: var(--color-primary);
    }
    .stat-card__label {
      font-size: 12px;
      color: var(--color-gray);
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    /* Фильтры */
    .filters {
      display: flex;
      gap: 8px;
      margin-bottom: 20px;
      flex-wrap: wrap;
      align-items: center;
    }
    .filter-btn {
      padding: 10px 18px;
      border-radius: var(--radius-pill);
      background: var(--glass-bg);
      border: 1px solid var(--glass-border);
      color: var(--color-gray-2);
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      transition: all 0.2s;
    }
    .filter-btn:hover { background: var(--glass-bg-strong); }
    .filter-btn.is-active {
      background: var(--color-primary);
      color: #fff;
      border-color: var(--color-primary);
    }
    .filter-btn--export {
      margin-left: auto;
      background: transparent;
      color: var(--color-primary);
      border-color: var(--color-primary);
    }
    .filter-btn--export:hover { background: var(--color-primary); color: #fff; }

    /* Таблица */
    .table-wrap {
      background: var(--glass-bg);
      border: 1px solid var(--glass-border);
      border-radius: var(--radius-lg);
      overflow: auto;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      min-width: 1200px;
    }
    thead {
      background: rgba(0, 0, 0, 0.3);
      position: sticky;
      top: 0;
    }
    th {
      padding: 12px 14px;
      text-align: left;
      font-weight: 600;
      color: var(--color-gray);
      text-transform: uppercase;
      font-size: 11px;
      letter-spacing: 1px;
      white-space: nowrap;
    }
    td {
      padding: 12px 14px;
      border-top: 1px solid var(--glass-border);
      vertical-align: top;
    }
    tr:hover { background: rgba(255, 255, 255, 0.03); }

    /* Статусы */
    .status {
      display: inline-block;
      padding: 4px 10px;
      border-radius: var(--radius-pill);
      font-size: 11px;
      font-weight: 600;
      white-space: nowrap;
    }
    .status--new       { background: var(--color-primary); color: #fff; }
    .status--processed { background: #4caf50; color: #fff; }
    .status--spam      { background: #555; color: #ccc; }

    /* Способ связи */
    .method {
      display: inline-block;
      padding: 3px 9px;
      border-radius: var(--radius-pill);
      background: var(--glass-bg-strong);
      border: 1px solid var(--glass-border);
      font-size: 11px;
      font-weight: 600;
      white-space: nowrap;
    }

    /* Пустое состояние */
    .empty {
      padding: 60px 20px;
      text-align: center;
      color: var(--color-gray);
    }

    /* Адаптив */
    @media (max-width: 768px) {
      body { padding: 12px; }
      h1 { font-size: 18px; }
      .stat-card__value { font-size: 22px; }
    }
  </style>
</head>
<body>

<div class="container">
  <h1>🔔 <span>Магнетар</span> — Заявки с сайта</h1>

  <!-- Счётчики -->
  <div class="stats">
    <div class="stat-card">
      <div class="stat-card__value"><?= $counts['all'] ?></div>
      <div class="stat-card__label">Всего</div>
    </div>
    <div class="stat-card">
      <div class="stat-card__value"><?= $counts['new'] ?></div>
      <div class="stat-card__label">Новых</div>
    </div>
    <div class="stat-card">
      <div class="stat-card__value"><?= $counts['processed'] ?></div>
      <div class="stat-card__label">Обработано</div>
    </div>
    <div class="stat-card">
      <div class="stat-card__value"><?= $counts['spam'] ?></div>
      <div class="stat-card__label">Спам</div>
    </div>
  </div>

  <!-- Фильтры -->
  <div class="filters">
    <?php
      $tabs = [
        'all'       => 'Все',
        'new'       => 'Новые',
        'processed' => 'Обработанные',
        'spam'      => 'Спам',
      ];
      foreach ($tabs as $key => $label):
        $isActive = $statusFilter === $key ? 'is-active' : '';
        $count = $counts[$key];
    ?>
      <a href="?status=<?= $key ?>" class="filter-btn <?= $isActive ?>">
        <?= $label ?> (<?= $count ?>)
      </a>
    <?php endforeach; ?>

    <a href="?export=csv&status=<?= $statusFilter ?>" class="filter-btn filter-btn--export">
      ↓ Экспорт CSV
    </a>
  </div>

  <!-- Таблица -->
  <div class="table-wrap">
    <?php if (empty($requests)): ?>
      <div class="empty">Заявок пока нет</div>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Дата</th>
            <th>Имя</th>
            <th>Телефон</th>
            <th>Email</th>
            <th>Способ связи</th>
            <th>Комментарий</th>
            <th>Статус</th>
            <th>IP</th>
            <th>VK</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $r): ?>
            <tr>
              <td>#<?= (int)$r['id'] ?></td>
              <td style="white-space: nowrap;">
                <?= date('d.m.Y', strtotime($r['created_at'])) ?><br>
                <span style="color: var(--color-gray); font-size: 11px;">
                  <?= date('H:i', strtotime($r['created_at'])) ?>
                </span>
              </td>
              <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
              <td>
                <?php if ($r['phone']): ?>
                  <a href="tel:<?= htmlspecialchars($r['phone']) ?>"
                     style="color: var(--color-primary); white-space: nowrap;">
                    <?= htmlspecialchars($r['phone']) ?>
                  </a>
                <?php else: ?>
                  <span style="color: var(--color-gray);">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($r['email']): ?>
                  <a href="mailto:<?= htmlspecialchars($r['email']) ?>"
                     style="color: var(--color-gray-2);">
                    <?= htmlspecialchars($r['email']) ?>
                  </a>
                <?php else: ?>
                  <span style="color: var(--color-gray);">—</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="method">
                  <?= contactMethodIcon($r['contact_method'] ?? 'phone') ?>
                  <?= contactMethodLabel($r['contact_method'] ?? 'phone') ?>
                </span>
              </td>
              <td style="max-width: 320px;">
                <?= nl2br(htmlspecialchars($r['message'] ?? '')) ?>
              </td>
              <td>
                <?php
                  $statusClass = match($r['status']) {
                    'new'       => 'status--new',
                    'processed' => 'status--processed',
                    'spam'      => 'status--spam',
                    default     => 'status--new',
                  };
                  $statusLabel = match($r['status']) {
                    'new'       => 'Новый',
                    'processed' => 'Обработан',
                    'spam'      => 'Спам',
                    default     => $r['status'],
                  };
                ?>
                <span class="status <?= $statusClass ?>"><?= $statusLabel ?></span>
              </td>
              <td style="font-size: 11px; color: var(--color-gray); white-space: nowrap;">
                <?= htmlspecialchars($r['ip'] ?? '—') ?>
              </td>
              <td>
                <?php if ($r['notified']): ?>
                  <span style="color: #4caf50; font-size: 16px;">✓</span>
                <?php else: ?>
                  <span style="color: var(--color-gray); font-size: 16px;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

</body>
</html>