<?php

declare(strict_types=1);

const INSTALL_DATA_DIR = __DIR__ . '/data';
const INSTALL_CACHE_DIR = __DIR__ . '/cache';
const INSTALL_DB_CONFIG_FILE = INSTALL_DATA_DIR . '/config.php';
const INSTALL_LOCK_FILE = INSTALL_DATA_DIR . '/install.lock';
const INSTALL_SETTINGS_CACHE_FILE = INSTALL_CACHE_DIR . '/settings.php';

function i_h(string|int|float|bool|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function i_default_settings(): array
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
        'comments_enabled' => '1',
        'comments_require_approval' => '1',
        'comments_notify' => '1',
        'posts_per_page' => '6',
        'pretty_url' => '0',
    ];
}

function i_default_ai_settings(): array
{
    return [
        'ai_api_url' => 'https://api.deepseek.com',
        'ai_api_key' => '',
        'ai_model' => 'deepseek-v4-flash',
        'ai_slug_prompt' => 'Translate the title into a concise English URL slug. Output lowercase ASCII words separated only by hyphens. Output the slug only, without quotes or explanation.',
        'ai_summary_prompt' => '根据文章内容生成不超过100个汉字的中文摘要。只输出摘要正文，不要标题、引号、解释或 Markdown 标记。',
        'ai_polish_prompt' => '你是专业中文编辑。严格执行用户要求，保留有效 Markdown 结构。只输出处理后的完整正文，不要解释处理过程。',
    ];
}

function i_default_mail_settings(): array
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

function i_default_s3_settings(): array
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

function i_db_name(): string
{
    if (is_file(INSTALL_LOCK_FILE) && is_file(INSTALL_DB_CONFIG_FILE)) {
        $config = include INSTALL_DB_CONFIG_FILE;
        $name = is_array($config) ? basename((string)($config['db_file'] ?? '')) : '';
        if ($name !== '' && $name !== 'blog.sqlite' && preg_match('/^blog-[a-f0-9]{16}\.sqlite$/', $name)) {
            return $name;
        }
    }

    return 'blog-' . bin2hex(random_bytes(8)) . '.sqlite';
}

function i_db_file(): string
{
    return INSTALL_DATA_DIR . '/' . i_db_name();
}

function i_is_installed(): bool
{
    if (!is_file(INSTALL_LOCK_FILE) || !is_file(INSTALL_DB_CONFIG_FILE)) { return false; }
    $config = include INSTALL_DB_CONFIG_FILE;
    $name = is_array($config) ? basename((string)($config['db_file'] ?? '')) : '';
    return preg_match('/^blog-[a-f0-9]{16}\.sqlite$/', $name) === 1 && is_file(INSTALL_DATA_DIR . '/' . $name);
}

function i_ensure_dirs(): void
{
    if (!is_dir(INSTALL_DATA_DIR)) {
        mkdir(INSTALL_DATA_DIR, 0755, true);
    }

    if (!is_dir(INSTALL_CACHE_DIR)) {
        mkdir(INSTALL_CACHE_DIR, 0755, true);
    }

    if (!is_dir(__DIR__ . '/uploads')) {
        mkdir(__DIR__ . '/uploads', 0755, true);
    }
}

function i_environment_checks(): array
{
    i_ensure_dirs();
    return [
        ['label' => 'PHP 8.0 或更高版本', 'ok' => version_compare(PHP_VERSION, '8.0.0', '>=')],
        ['label' => 'PDO 扩展', 'ok' => extension_loaded('pdo')],
        ['label' => 'PDO SQLite 驱动', 'ok' => extension_loaded('pdo_sqlite') && in_array('sqlite', PDO::getAvailableDrivers(), true)],
        ['label' => 'cURL 扩展（AI 与 S3 接口）', 'ok' => extension_loaded('curl')],
        ['label' => 'JSON 扩展', 'ok' => extension_loaded('json')],
        ['label' => 'Fileinfo 扩展（安全识别上传文件）', 'ok' => extension_loaded('fileinfo')],
        ['label' => '安全随机数支持', 'ok' => function_exists('random_bytes')],
        ['label' => 'data 目录可写', 'ok' => is_dir(INSTALL_DATA_DIR) && is_writable(INSTALL_DATA_DIR)],
        ['label' => 'cache 目录可写', 'ok' => is_dir(INSTALL_CACHE_DIR) && is_writable(INSTALL_CACHE_DIR)],
        ['label' => 'uploads 目录可写', 'ok' => is_dir(__DIR__ . '/uploads') && is_writable(__DIR__ . '/uploads')],
    ];
}

function i_environment_ready(array $checks): bool
{
    foreach ($checks as $check) { if (empty($check['ok'])) { return false; } }
    return true;
}

function i_save_db_config(string $dbName): void
{
    i_ensure_dirs();
    file_put_contents(INSTALL_DB_CONFIG_FILE, "<?php\nreturn ['db_file' => " . var_export($dbName, true) . "];\n", LOCK_EX);
}

function i_db(): PDO
{
    static $db;

    if ($db instanceof PDO) {
        return $db;
    }

    $file = i_db_file();
    i_ensure_dirs();
    i_save_db_config(basename($file));

    $db = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    foreach (
        [
            'PRAGMA journal_mode=WAL',
            'PRAGMA synchronous=NORMAL',
            'PRAGMA temp_store=MEMORY',
            'PRAGMA busy_timeout=5000',
            'PRAGMA foreign_keys=ON',
        ] as $sql
    ) {
        $db->exec($sql);
    }

    return $db;
}

function i_slugify(string $text): string
{
    $text = function_exists('mb_strtolower') ? mb_strtolower(trim($text), 'UTF-8') : strtolower(trim($text));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'welcome';
}

function i_plain_excerpt(string $content, int $length = 140): string
{
    $text = preg_replace('/\s+/u', ' ', strip_tags($content)) ?? $content;
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    $len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
    if ($len <= $length) {
        return $text;
    }

    return rtrim(function_exists('mb_substr') ? mb_substr($text, 0, $length, 'UTF-8') : substr($text, 0, $length)) . '…';
}

function i_write_settings_cache(PDO $db): void
{
    i_ensure_dirs();
    $settings = i_default_settings();
    foreach ($db->query('SELECT name, value FROM settings') as $row) {
        $settings[(string)$row['name']] = (string)$row['value'];
    }
    file_put_contents(INSTALL_SETTINGS_CACHE_FILE, "<?php\nreturn " . var_export($settings, true) . ";\n", LOCK_EX);
}

function i_sample_body(string $siteName): string
{
    return <<<MD
# 欢迎来到 {$siteName}

这是一个按照单入口 PHP 思路搭出来的轻量博客。

## 你可以先做这几件事

- 进入后台，修改站点名称和首页引言
- 写第一篇正式文章
- 试试草稿、定时发布和归档页

> 这套程序保持很小，方便继续按你的需求改。

```php
echo "Happy writing!";
```
MD;
}

function i_about_body(string $siteName): string
{
    return <<<MD
# 关于 {$siteName}

这是安装时自动生成的独立页面，你可以把这里改成博客简介、作者介绍，或者放联系方式。

## 建议内容

- 你是谁
- 这个博客主要写什么
- 如何联系你

> 这个页面会出现在顶部导航里。
MD;
}

function i_base_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/install.php'));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $dir === '' || $dir === '.' ? '' : $dir;
}

function i_asset_url(string $path): string
{
    return (i_base_path() !== '' ? i_base_path() : '') . '/' . ltrim($path, '/');
}

function i_render_page(string $title, string $body): void
{
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= i_h($title) ?></title>
  <link rel="stylesheet" href="<?= i_h(i_asset_url('index.css')) ?>?v=v1.1.2">
</head>
<body>
  <div class="site-frame">
    <main class="main-wrap main-wrap--wide">
      <?= $body ?>
    </main>
  </div>
</body>
</html>
<?php
    exit;
}

function i_render_form(array $form, array $errors = []): void
{
    $environmentChecks = i_environment_checks();
    $environmentReady = i_environment_ready($environmentChecks);
    ob_start();
    ?>
    <section class="hero hero--compact">
      <p class="hero__eyebrow">Install</p>
      <h1 class="hero__title">安装博客</h1>
      <p class="hero__lead">一次性初始化 SQLite、管理员账号和第一篇文章。</p>
    </section>

    <section class="panel install-environment">
      <div class="panel__header"><h2>安装环境检测</h2><p class="panel__meta"><?= $environmentReady ? '当前环境满足安装要求。' : '请修复未通过项目后再安装。' ?></p></div>
      <div class="panel__body">
        <div class="environment-checks">
          <?php foreach ($environmentChecks as $check): ?>
            <div class="environment-check<?= $check['ok'] ? ' is-ok' : ' is-error' ?>"><strong><?= $check['ok'] ? '通过' : '未通过' ?></strong><span><?= i_h((string)$check['label']) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <div class="admin-grid">
      <section class="panel">
        <div class="panel__header">
          <h2>安装信息</h2>
        </div>
        <div class="panel__body">
          <?php if ($errors): ?>
            <div class="flash flash--error"><?= i_h(implode(' ', $errors)) ?></div>
          <?php endif; ?>

          <form class="form-stack" method="post">
            <div class="field-grid">
              <div class="field">
                <label for="site_name">站点名称</label>
                <input id="site_name" name="site_name" type="text" value="<?= i_h((string)$form['site_name']) ?>" required>
              </div>
              <div class="field">
                <label for="site_tagline">首页副标题</label>
                <input id="site_tagline" name="site_tagline" type="text" value="<?= i_h((string)$form['site_tagline']) ?>" required>
              </div>
            </div>

            <div class="field-grid">
              <div class="field">
                <label for="admin_username">管理员用户名</label>
                <input id="admin_username" name="admin_username" type="text" value="<?= i_h((string)$form['admin_username']) ?>" required>
              </div>
              <div class="field">
                <label for="author_name">管理员昵称</label>
                <input id="author_name" name="author_name" type="text" value="<?= i_h((string)$form['author_name']) ?>" required>
              </div>
            </div>

            <div class="field">
              <label for="admin_email">管理员邮箱</label>
              <input id="admin_email" name="admin_email" type="email" value="<?= i_h((string)$form['admin_email']) ?>" maxlength="160" autocomplete="email" required>
            </div>

            <div class="field">
              <label for="pretty_url">伪静态 URL</label>
              <select id="pretty_url" name="pretty_url">
                <option value="0"<?= (string)($form['pretty_url'] ?? '0') === '0' ? ' selected' : '' ?>>关闭</option>
                <option value="1"<?= (string)($form['pretty_url'] ?? '0') === '1' ? ' selected' : '' ?>>开启</option>
              </select>
            </div>

            <div class="field-grid">
              <div class="field">
                <label for="admin_password">管理员密码</label>
                <input id="admin_password" name="admin_password" type="password" required>
              </div>
              <div class="field">
                <label for="admin_password2">确认密码</label>
                <input id="admin_password2" name="admin_password2" type="password" required>
              </div>
            </div>

            <div class="field">
              <label for="welcome_title">第一篇文章标题</label>
              <input id="welcome_title" name="welcome_title" type="text" value="<?= i_h((string)$form['welcome_title']) ?>" required>
            </div>

            <div class="field">
              <label for="welcome_body">第一篇文章内容</label>
              <textarea id="welcome_body" class="editor-textarea" name="welcome_body" rows="14" required><?= i_h((string)$form['welcome_body']) ?></textarea>
            </div>

            <div class="action-row">
              <button class="button" type="submit"<?= $environmentReady ? '' : ' disabled' ?>>开始安装</button>
            </div>
          </form>
        </div>
      </section>

      <section class="panel">
        <div class="panel__header">
          <h2>将会创建</h2>
        </div>
        <div class="panel__body">
          <ul class="archive-items archive-items--plain">
            <li class="archive-item"><span>随机文件名 SQLite 数据库</span></li>
            <li class="archive-item"><span>站点设置、用户、内容、分类与评论数据表</span></li>
            <li class="archive-item"><span>默认分类、归属该分类的欢迎文章与默认关于页</span></li>
            <li class="archive-item"><span>`data/install.lock` 安装锁</span></li>
            <li class="archive-item"><span>`cache/settings.php` 站点配置缓存</span></li>
          </ul>
        </div>
      </section>
    </div>
    <?php
    i_render_page('安装博客', (string)ob_get_clean());
}

function i_render_locked(): void
{
    ob_start();
    ?>
    <section class="hero hero--compact">
      <p class="hero__eyebrow">Install</p>
      <h1 class="hero__title">安装已锁定</h1>
      <p class="hero__lead">如果你要重新安装，请先删除 `data/install.lock`。</p>
    </section>
    <div class="empty-state">
      <a class="button" href="index.php">进入首页</a>
    </div>
    <?php
    i_render_page('安装已锁定', (string)ob_get_clean());
}

function i_render_success(string $siteName, string $adminUsername, string $dbName): void
{
    ob_start();
    ?>
    <section class="hero hero--compact">
      <p class="hero__eyebrow">Installed</p>
      <h1 class="hero__title">安装完成</h1>
      <p class="hero__lead">博客已经可以直接使用了。</p>
    </section>

    <div class="admin-grid">
      <section class="panel">
        <div class="panel__header">
          <h2>安装结果</h2>
        </div>
        <div class="panel__body">
          <div class="metric-grid">
            <div class="metric-card">
              <span class="metric-card__label">站点名称</span>
              <strong class="metric-card__value metric-card__value--small"><?= i_h($siteName) ?></strong>
            </div>
            <div class="metric-card">
              <span class="metric-card__label">管理员</span>
              <strong class="metric-card__value metric-card__value--small"><?= i_h($adminUsername) ?></strong>
            </div>
            <div class="metric-card">
              <span class="metric-card__label">数据库</span>
              <strong class="metric-card__value metric-card__value--small"><?= i_h($dbName) ?></strong>
            </div>
          </div>
          <div class="action-row action-row--start">
            <a class="button" href="index.php">进入首页</a>
            <a class="button button--secondary" href="index.php?a=login">登录后台</a>
          </div>
        </div>
      </section>
    </div>
    <?php
    i_render_page('安装完成', (string)ob_get_clean());
}

if (i_is_installed()) {
    i_render_locked();
}

$form = [
    'site_name' => 'Simple PHP Blog',
    'site_tagline' => 'A small PHP blog running on one main entry file.',
    'admin_username' => 'admin',
    'author_name' => 'Admin',
    'admin_email' => '',
    'welcome_title' => '欢迎来到你的新博客',
    'welcome_body' => i_sample_body('Simple PHP Blog'),
    'pretty_url' => '0',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    i_render_form($form);
}

$form = [
    'site_name' => trim((string)($_POST['site_name'] ?? 'Simple PHP Blog')),
    'site_tagline' => trim((string)($_POST['site_tagline'] ?? '')),
    'admin_username' => trim((string)($_POST['admin_username'] ?? 'admin')),
    'author_name' => trim((string)($_POST['author_name'] ?? 'Admin')),
    'admin_email' => strtolower(trim((string)($_POST['admin_email'] ?? ''))),
    'welcome_title' => trim((string)($_POST['welcome_title'] ?? '欢迎来到你的新博客')),
    'welcome_body' => trim((string)($_POST['welcome_body'] ?? '')),
    'pretty_url' => (string)($_POST['pretty_url'] ?? '0') === '1' ? '1' : '0',
];

$password = (string)($_POST['admin_password'] ?? '');
$password2 = (string)($_POST['admin_password2'] ?? '');
$errors = [];
$environmentChecks = i_environment_checks();
if (!i_environment_ready($environmentChecks)) {
    $errors[] = '当前服务器环境未满足安装要求。';
}

if ($form['site_name'] === '') {
    $errors[] = '站点名称不能为空。';
}

if ($form['site_tagline'] === '') {
    $errors[] = '首页副标题不能为空。';
}

if ($form['admin_username'] === '') {
    $errors[] = '管理员用户名不能为空。';
}

if ($form['author_name'] === '') {
    $errors[] = '作者显示名不能为空。';
}

if ($form['admin_email'] === '' || strlen($form['admin_email']) > 160 || !filter_var($form['admin_email'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = '请填写有效的管理员邮箱地址。';
}

if ($password === '') {
    $errors[] = '管理员密码不能为空。';
}

if ($password !== $password2) {
    $errors[] = '两次输入的密码不一致。';
}

if ($form['welcome_title'] === '' || $form['welcome_body'] === '') {
    $errors[] = '第一篇文章的标题和正文都不能为空。';
}

if ($errors) {
    i_render_form($form, $errors);
}

$db = i_db();
$db->exec(
    'CREATE TABLE IF NOT EXISTS settings(
        name TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT \'\'
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS ai_settings(
        name TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT \'\'
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS mail_settings(
        name TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT \'\'
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS s3_settings(
        name TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT \'\'
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        nickname TEXT NOT NULL DEFAULT \'\',
        email TEXT NOT NULL DEFAULT \'\',
        avatar_url TEXT NOT NULL DEFAULT \'\',
        website_url TEXT NOT NULL DEFAULT \'\',
        qq_url TEXT NOT NULL DEFAULT \'\',
        wechat_url TEXT NOT NULL DEFAULT \'\',
        weibo_url TEXT NOT NULL DEFAULT \'\',
        x_url TEXT NOT NULL DEFAULT \'\',
        telegram_url TEXT NOT NULL DEFAULT \'\',
        bilibili_url TEXT NOT NULL DEFAULT \'\',
        instagram_url TEXT NOT NULL DEFAULT \'\',
        tiktok_url TEXT NOT NULL DEFAULT \'\',
        signature TEXT NOT NULL DEFAULT \'\',
        created_at INTEGER NOT NULL
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS posts(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        author_id INTEGER,
        category_id INTEGER,
        slug TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        excerpt TEXT NOT NULL DEFAULT \'\',
        content TEXT NOT NULL,
        kind TEXT NOT NULL DEFAULT \'post\',
        tags TEXT NOT NULL DEFAULT \'[]\',
        views INTEGER NOT NULL DEFAULT 0,
        is_pinned INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT \'draft\',
        published_at INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS categories(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        description TEXT NOT NULL DEFAULT \'\',
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS comments(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER NOT NULL,
        user_id INTEGER,
        parent_id INTEGER,
        reply_to_name TEXT NOT NULL DEFAULT \'\',
        author_name TEXT NOT NULL,
        author_email TEXT NOT NULL,
        author_url TEXT NOT NULL DEFAULT \'\',
        content TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT \'pending\',
        is_read INTEGER NOT NULL DEFAULT 0,
        ip_hash TEXT NOT NULL DEFAULT \'\',
        ip_address TEXT NOT NULL DEFAULT \'\',
        user_agent TEXT NOT NULL DEFAULT \'\',
        reply_notified_at INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL,
        FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY(parent_id) REFERENCES comments(id) ON DELETE SET NULL
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS post_views(
        post_id INTEGER NOT NULL,
        ip_hash TEXT NOT NULL,
        created_at INTEGER NOT NULL,
        PRIMARY KEY(post_id, ip_hash),
        FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE
    ) WITHOUT ROWID'
);
$db->exec('CREATE INDEX IF NOT EXISTS idx_posts_published_pinned ON posts(kind, status, is_pinned DESC, published_at DESC, id DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category_id, kind, status, published_at DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_categories_sort ON categories(sort_order ASC, id DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_post_public ON comments(post_id, status, created_at, id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_moderation ON comments(status, created_at DESC, id DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_unread ON comments(is_read, created_at DESC, id DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_ip_recent ON comments(ip_hash, created_at DESC)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_parent ON comments(parent_id, created_at, id)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_comments_user_recent ON comments(user_id, created_at DESC)');
$db->exec("CREATE INDEX IF NOT EXISTS idx_comments_visitor_email_approval ON comments(author_email COLLATE NOCASE, status) WHERE user_id IS NULL");

$now = time();
$settings = i_default_settings();
$settings['site_name'] = $form['site_name'];
$settings['site_tagline'] = $form['site_tagline'];
$settings['site_description'] = $form['site_tagline'];
$settings['pretty_url'] = $form['pretty_url'];

$statement = $db->prepare('INSERT OR REPLACE INTO settings(name, value) VALUES(?, ?)');
foreach ($settings as $name => $value) {
    $statement->execute([$name, $value]);
}

$aiStatement = $db->prepare('INSERT OR REPLACE INTO ai_settings(name, value) VALUES(?, ?)');
foreach (i_default_ai_settings() as $name => $value) {
    $aiStatement->execute([$name, $value]);
}

$mailStatement = $db->prepare('INSERT OR REPLACE INTO mail_settings(name, value) VALUES(?, ?)');
foreach (i_default_mail_settings() as $name => $value) {
    $mailStatement->execute([$name, $value]);
}

$s3Statement = $db->prepare('INSERT OR REPLACE INTO s3_settings(name, value) VALUES(?, ?)');
foreach (i_default_s3_settings() as $name => $value) {
    $s3Statement->execute([$name, $value]);
}

$db->prepare('INSERT INTO users(username, password_hash, nickname, email, avatar_url, website_url, created_at) VALUES(?, ?, ?, ?, ?, ?, ?)')
    ->execute([$form['admin_username'], password_hash($password, PASSWORD_DEFAULT), $form['author_name'], $form['admin_email'], '', '', $now]);
$defaultAuthorId = (int)$db->lastInsertId();

$db->prepare('INSERT INTO categories(name, slug, description, sort_order, created_at, updated_at) VALUES(?, ?, ?, ?, ?, ?)')
    ->execute(['默认分类', 'default', '安装时自动创建的默认文章分类。', 0, $now, $now]);
$defaultCategoryId = (int)$db->lastInsertId();

$db->prepare(
    'INSERT INTO posts(author_id, kind, category_id, slug, title, tags, excerpt, content, status, published_at, created_at, updated_at)
     VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    $defaultAuthorId,
    'post',
    $defaultCategoryId,
    i_slugify($form['welcome_title']),
    $form['welcome_title'],
    json_encode(['欢迎'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    i_plain_excerpt($form['welcome_body']),
    $form['welcome_body'],
    'published',
    $now,
    $now,
    $now,
]);

$db->prepare(
    'INSERT INTO posts(author_id, kind, category_id, slug, title, tags, excerpt, content, status, published_at, created_at, updated_at)
     VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    $defaultAuthorId,
    'page',
    null,
    'about',
    '关于',
    '[]',
    '关于页面',
    i_about_body($form['site_name']),
    'published',
    $now,
    $now,
    $now,
]);

i_write_settings_cache($db);
file_put_contents(INSTALL_LOCK_FILE, (string)$now, LOCK_EX);

i_render_success($form['site_name'], $form['admin_username'], basename(i_db_file()));
