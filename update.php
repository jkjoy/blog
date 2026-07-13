<?php

declare(strict_types=1);

$sessionSecure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $sessionSecure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

const UPDATE_DATA_DIR = __DIR__ . '/data';
const UPDATE_CONFIG_FILE = UPDATE_DATA_DIR . '/config.php';
const UPDATE_LOCK_FILE = UPDATE_DATA_DIR . '/install.lock';

function update_h(string|int $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function update_database_file(): string
{
    if (!is_file(UPDATE_LOCK_FILE) || !is_file(UPDATE_CONFIG_FILE)) {
        return '';
    }

    $config = include UPDATE_CONFIG_FILE;
    $name = is_array($config) ? basename((string)($config['db_file'] ?? '')) : '';
    if (!preg_match('/^blog-[a-f0-9]{16}\.sqlite$/', $name)) {
        return '';
    }

    $file = UPDATE_DATA_DIR . '/' . $name;
    return is_file($file) ? $file : '';
}

function update_database(string $file): PDO
{
    return new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function update_has_column(PDO $db, string $table, string $column): bool
{
    foreach ($db->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $row) {
        if ((string)$row['name'] === $column) {
            return true;
        }
    }
    return false;
}

$databaseFile = update_database_file();
if ($databaseFile === '') {
    http_response_code(503);
    exit('博客尚未安装或数据库配置无效。');
}

$db = update_database($databaseFile);
$adminId = (int)($_SESSION['admin_id'] ?? 0);
$admin = $adminId > 0
    ? $db->query('SELECT id FROM users WHERE id = ' . $adminId . ' LIMIT 1')->fetch()
    : false;
if (!$admin) {
    header('Location: index.php?action=login');
    exit;
}

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $token)) {
        $error = '请求已失效，请刷新页面后重试。';
    } else {
        try {
            $db->beginTransaction();
            $changes = [];
            if (!update_has_column($db, 'posts', 'is_pinned')) {
                $db->exec('ALTER TABLE posts ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0');
                $changes[] = '新增文章置顶字段';
            }
            $db->exec('UPDATE posts SET is_pinned = 0 WHERE is_pinned IS NULL');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_posts_public_pinned ON posts(kind, status, is_pinned DESC, published_at DESC, id DESC)');
            $db->commit();
            $message = $changes ? '数据库升级完成：' . implode('、', $changes) . '。' : '数据库已经是最新版本，无需变更。';
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = '升级失败：' . $exception->getMessage();
        }
    }
}

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>数据库升级</title>
  <link rel="stylesheet" href="index.css">
</head>
<body class="theme-admin theme-admin--guest">
  <main class="main-wrap">
    <section class="panel auth-panel" style="margin: 48px auto; max-width: 620px;">
      <div class="panel__header"><h1>数据库升级</h1></div>
      <div class="panel__body">
        <?php if ($message !== ''): ?><div class="flash flash--success"><?= update_h($message) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="flash flash--error"><?= update_h($error) ?></div><?php endif; ?>
        <p>本次升级将为文章增加置顶字段和对应查询索引。操作可重复执行，不会覆盖文章内容。</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= update_h((string)$_SESSION['csrf_token']) ?>">
          <div class="form-actions">
            <button class="button button--primary" type="submit">开始升级</button>
            <a class="button button--secondary" href="index.php?action=admin">返回后台</a>
          </div>
        </form>
      </div>
    </section>
  </main>
</body>
</html>
