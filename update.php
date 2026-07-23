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
const UPDATE_CACHE_DIR = __DIR__ . '/cache';
const UPDATE_CONFIG_FILE = UPDATE_DATA_DIR . '/config.php';
const UPDATE_LOCK_FILE = UPDATE_DATA_DIR . '/install.lock';
const UPDATE_SETTINGS_CACHE_FILE = UPDATE_CACHE_DIR . '/settings.php';

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
    $db = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('PRAGMA foreign_keys=ON');

    return $db;
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

function update_default_settings(): array
{
    return [
        'site_name' => 'Simple PHP Blog',
        'site_url' => '',
        'site_tagline' => 'A small PHP blog running on one main entry file.',
        'site_description' => 'A simple PHP + SQLite blog inspired by Hugo Paper.',
        'site_keywords' => '',
        'site_footer' => '',
        'custom_head_code' => '',
        'active_theme' => 'default',
        'favicon_url' => 'logo.png',
        'footer_beian' => '',
        'posts_per_page' => '6',
        'pretty_url' => '0',
        'comments_enabled' => '1',
        'comments_require_approval' => '1',
        'comments_notify' => '1',
    ];
}

function update_default_mail_settings(): array
{
    return [
        'smtp_enabled' => '0',
        'smtp_host' => '',
        'smtp_port' => '465',
        'smtp_encryption' => 'ssl',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_from_email' => '',
        'smtp_from_name' => '',
        'smtp_notify_email' => '',
    ];
}

function update_default_s3_settings(): array
{
    return [
        's3_enabled' => '0',
        's3_keep_local' => '1',
        's3_endpoint' => 'https://s3.amazonaws.com',
        's3_region' => 'us-east-1',
        's3_bucket' => '',
        's3_access_key' => '',
        's3_secret_key' => '',
        's3_path_prefix' => 'uploads',
        's3_public_url' => '',
        's3_path_style' => '0',
    ];
}

function update_write_settings_cache(PDO $db): void
{
    if (!is_dir(UPDATE_CACHE_DIR)) {
        mkdir(UPDATE_CACHE_DIR, 0775, true);
    }

    $settings = update_default_settings();
    foreach ($db->query('SELECT name, value FROM settings') as $row) {
        $settings[(string)$row['name']] = (string)$row['value'];
    }

    file_put_contents(UPDATE_SETTINGS_CACHE_FILE, "<?php\nreturn " . var_export($settings, true) . ";\n", LOCK_EX);
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
    header('Location: index.php?a=login');
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
            $db->exec('BEGIN IMMEDIATE');
            $changes = [];
            if (!update_has_column($db, 'posts', 'is_pinned')) {
                $db->exec('ALTER TABLE posts ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0');
                $changes[] = '新增文章置顶字段';
            }
            if (!update_has_column($db, 'posts', 'allow_comments')) {
                $db->exec('ALTER TABLE posts ADD COLUMN allow_comments INTEGER NOT NULL DEFAULT 0');
                $changes[] = '新增独立页评论开关';
            }
            $db->exec('UPDATE posts SET is_pinned = 0 WHERE is_pinned IS NULL');
            $db->exec('UPDATE posts SET allow_comments = 0 WHERE allow_comments IS NULL');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_posts_public_pinned ON posts(kind, status, is_pinned DESC, published_at DESC, id DESC)');
            $commentsExist = (bool)$db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'comments' LIMIT 1")->fetchColumn();
            $db->exec(
                "CREATE TABLE IF NOT EXISTS comments(
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    post_id INTEGER NOT NULL,
                    user_id INTEGER,
                    parent_id INTEGER,
                    reply_to_name TEXT NOT NULL DEFAULT '',
                    author_name TEXT NOT NULL,
                    author_email TEXT NOT NULL,
                    author_url TEXT NOT NULL DEFAULT '',
                    content TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'pending',
                    is_read INTEGER NOT NULL DEFAULT 0,
                    ip_hash TEXT NOT NULL DEFAULT '',
                    ip_address TEXT NOT NULL DEFAULT '',
                    user_agent TEXT NOT NULL DEFAULT '',
                    reply_notified_at INTEGER NOT NULL DEFAULT 0,
                    created_at INTEGER NOT NULL,
                    updated_at INTEGER NOT NULL,
                    FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
                    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
                    FOREIGN KEY(parent_id) REFERENCES comments(id) ON DELETE SET NULL
                )"
            );
            $viewsExist = (bool)$db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'post_views' LIMIT 1")->fetchColumn();
            $db->exec(
                "CREATE TABLE IF NOT EXISTS post_views(
                    post_id INTEGER NOT NULL,
                    ip_hash TEXT NOT NULL,
                    created_at INTEGER NOT NULL,
                    PRIMARY KEY(post_id, ip_hash),
                    FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE
                ) WITHOUT ROWID"
            );
            if (!$viewsExist) {
                $changes[] = '新增文章独立访客计数表';
            }
            $replyFieldsAdded = false;
            if (!update_has_column($db, 'comments', 'parent_id')) {
                $db->exec('ALTER TABLE comments ADD COLUMN parent_id INTEGER REFERENCES comments(id) ON DELETE SET NULL');
                $replyFieldsAdded = true;
            }
            if (!update_has_column($db, 'comments', 'reply_to_name')) {
                $db->exec("ALTER TABLE comments ADD COLUMN reply_to_name TEXT NOT NULL DEFAULT ''");
                $replyFieldsAdded = true;
            }
            $userFieldAdded = false;
            if (!update_has_column($db, 'comments', 'user_id')) {
                $db->exec('ALTER TABLE comments ADD COLUMN user_id INTEGER REFERENCES users(id) ON DELETE SET NULL');
                $userFieldAdded = true;
            }
            if (!update_has_column($db, 'comments', 'ip_address')) {
                $db->exec("ALTER TABLE comments ADD COLUMN ip_address TEXT NOT NULL DEFAULT ''");
                $changes[] = '新增评论 IP 地址字段';
            }
            if (!update_has_column($db, 'comments', 'reply_notified_at')) {
                $db->exec('ALTER TABLE comments ADD COLUMN reply_notified_at INTEGER NOT NULL DEFAULT 0');
                $changes[] = '新增评论回复通知状态';
            }
            $emailApprovalIndexExists = (bool)$db->query("SELECT 1 FROM sqlite_master WHERE type = 'index' AND name = 'idx_comments_visitor_email_approval' LIMIT 1")->fetchColumn();
            $db->exec('CREATE INDEX IF NOT EXISTS idx_comments_post_public ON comments(post_id, status, created_at, id)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_comments_moderation ON comments(status, created_at DESC, id DESC)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_comments_unread ON comments(is_read, created_at DESC, id DESC)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_comments_ip_recent ON comments(ip_hash, created_at DESC)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_comments_parent ON comments(parent_id, created_at, id)');
            $db->exec('CREATE INDEX IF NOT EXISTS idx_comments_user_recent ON comments(user_id, created_at DESC)');
            $db->exec("CREATE INDEX IF NOT EXISTS idx_comments_visitor_email_approval ON comments(author_email COLLATE NOCASE, status) WHERE user_id IS NULL");
            if (!$commentsExist) {
                $changes[] = '新增评论数据表和查询索引';
            } elseif ($replyFieldsAdded) {
                $changes[] = '新增评论回复字段和查询索引';
            }
            if ($userFieldAdded) {
                $changes[] = '新增登录用户评论关联字段和查询索引';
            }
            if ($commentsExist && !$emailApprovalIndexExists) {
                $changes[] = '新增评论邮箱审核查询索引';
            }

            $db->exec(
                "CREATE TABLE IF NOT EXISTS ai_settings(
                    name TEXT PRIMARY KEY,
                    value TEXT NOT NULL DEFAULT ''
                )"
            );
            $legacyAiSettings = $db->query("SELECT name, value FROM settings WHERE name LIKE 'ai\\_%' ESCAPE '\\'")->fetchAll();
            if ($legacyAiSettings) {
                $statement = $db->prepare('INSERT OR REPLACE INTO ai_settings(name, value) VALUES(?, ?)');
                foreach ($legacyAiSettings as $row) {
                    $statement->execute([(string)$row['name'], (string)$row['value']]);
                }
                $db->exec("DELETE FROM settings WHERE name LIKE 'ai\\_%' ESCAPE '\\'");
                $changes[] = '迁移 AI 设置到独立数据表';
            }

            $mailSettingsExist = (bool)$db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'mail_settings' LIMIT 1")->fetchColumn();
            $db->exec(
                "CREATE TABLE IF NOT EXISTS mail_settings(
                    name TEXT PRIMARY KEY,
                    value TEXT NOT NULL DEFAULT ''
                )"
            );
            $mailStatement = $db->prepare('INSERT OR IGNORE INTO mail_settings(name, value) VALUES(?, ?)');
            foreach (update_default_mail_settings() as $name => $value) {
                $mailStatement->execute([$name, $value]);
            }
            if (!$mailSettingsExist) {
                $changes[] = '新增邮件通知设置表';
            }

            $s3SettingsExist = (bool)$db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 's3_settings' LIMIT 1")->fetchColumn();
            $db->exec(
                "CREATE TABLE IF NOT EXISTS s3_settings(
                    name TEXT PRIMARY KEY,
                    value TEXT NOT NULL DEFAULT ''
                )"
            );
            $s3Statement = $db->prepare('INSERT OR IGNORE INTO s3_settings(name, value) VALUES(?, ?)');
            foreach (update_default_s3_settings() as $name => $value) {
                $s3Statement->execute([$name, $value]);
            }
            if (!$s3SettingsExist) {
                $changes[] = '新增 S3 上传设置表';
            }

            $socialFieldsChanged = false;
            foreach (['qq_url', 'wechat_url', 'weibo_url', 'x_url', 'telegram_url', 'mastodon_url', 'bilibili_url', 'instagram_url', 'tiktok_url'] as $column) {
                if (!update_has_column($db, 'users', $column)) {
                    $db->exec("ALTER TABLE users ADD COLUMN {$column} TEXT NOT NULL DEFAULT ''");
                    $socialFieldsChanged = true;
                }
            }
            if (update_has_column($db, 'users', 'social_links')) {
                $db->exec('ALTER TABLE users DROP COLUMN social_links');
                $socialFieldsChanged = true;
            }
            if ($socialFieldsChanged) {
                $changes[] = '更新用户社交平台字段';
            }

            $db->commit();
            update_write_settings_cache($db);
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
        <p>本次升级将补齐文章置顶、评论、通知、存储和用户社交平台所需的数据结构。操作可重复执行，不会覆盖文章内容。</p>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= update_h((string)$_SESSION['csrf_token']) ?>">
          <div class="form-actions">
            <button class="button button--primary" type="submit">开始升级</button>
            <a class="button button--secondary" href="index.php?a=admin">返回后台</a>
          </div>
        </form>
      </div>
    </section>
  </main>
</body>
</html>
