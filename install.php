<?php

declare(strict_types=1);

const INSTALL_DATA_DIR = __DIR__ . '/data';
const INSTALL_CACHE_DIR = __DIR__ . '/cache';
const INSTALL_DB_CONFIG_FILE = INSTALL_DATA_DIR . '/config.php';
const INSTALL_DEFAULT_DB_FILE = INSTALL_DATA_DIR . '/blog.sqlite';
const INSTALL_LOCK_FILE = INSTALL_DATA_DIR . '/install.lock';
const INSTALL_SETTINGS_CACHE_FILE = INSTALL_CACHE_DIR . '/settings.php';

function i_h(string|int|float|bool|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function i_default_settings(): array
{
    return [
        'site_name' => 'Paper Notes',
        'author_name' => 'Admin',
        'site_url' => '',
        'site_tagline' => 'A small PHP blog running on one main entry file.',
        'site_description' => 'A simple PHP + SQLite blog inspired by Hugo Paper.',
        'home_intro' => '安静地写点东西，保留足够留白，让文章本身站到前面。',
        'site_footer' => '',
        'posts_per_page' => '6',
        'pretty_url' => '0',
    ];
}

function i_db_name(): string
{
    if (is_file(INSTALL_LOCK_FILE) && is_file(INSTALL_DB_CONFIG_FILE)) {
        $config = include INSTALL_DB_CONFIG_FILE;
        $name = is_array($config) ? basename((string)($config['db_file'] ?? '')) : '';
        if ($name !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.sqlite$/', $name)) {
            return $name;
        }
    }

    if (is_file(INSTALL_LOCK_FILE) && is_file(INSTALL_DEFAULT_DB_FILE)) {
        return basename(INSTALL_DEFAULT_DB_FILE);
    }

    return 'blog-' . bin2hex(random_bytes(8)) . '.sqlite';
}

function i_db_file(): string
{
    return INSTALL_DATA_DIR . '/' . i_db_name();
}

function i_ensure_dirs(): void
{
    if (!is_dir(INSTALL_DATA_DIR)) {
        mkdir(INSTALL_DATA_DIR, 0755, true);
    }

    if (!is_dir(INSTALL_CACHE_DIR)) {
        mkdir(INSTALL_CACHE_DIR, 0755, true);
    }
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
  <link rel="stylesheet" href="<?= i_h(i_asset_url('app.css')) ?>?v=v0.1.0">
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
    ob_start();
    ?>
    <section class="hero hero--compact">
      <p class="hero__eyebrow">Install</p>
      <h1 class="hero__title">安装博客</h1>
      <p class="hero__lead">一次性初始化 SQLite、管理员账号和第一篇文章。</p>
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
                <label for="author_name">作者显示名</label>
                <input id="author_name" name="author_name" type="text" value="<?= i_h((string)$form['author_name']) ?>" required>
              </div>
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
              <button class="button" type="submit">开始安装</button>
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
            <li class="archive-item"><span>`settings` / `users` / `posts` 三张表</span></li>
            <li class="archive-item"><span>管理员账号、欢迎文章与默认关于页</span></li>
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

if (is_file(INSTALL_LOCK_FILE)) {
    i_render_locked();
}

$form = [
    'site_name' => 'Paper Notes',
    'site_tagline' => 'A small PHP blog running on one main entry file.',
    'admin_username' => 'admin',
    'author_name' => 'Admin',
    'welcome_title' => '欢迎来到你的新博客',
    'welcome_body' => i_sample_body('Paper Notes'),
    'pretty_url' => '0',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    i_render_form($form);
}

$form = [
    'site_name' => trim((string)($_POST['site_name'] ?? 'Paper Notes')),
    'site_tagline' => trim((string)($_POST['site_tagline'] ?? '')),
    'admin_username' => trim((string)($_POST['admin_username'] ?? 'admin')),
    'author_name' => trim((string)($_POST['author_name'] ?? 'Admin')),
    'welcome_title' => trim((string)($_POST['welcome_title'] ?? '欢迎来到你的新博客')),
    'welcome_body' => trim((string)($_POST['welcome_body'] ?? '')),
    'pretty_url' => (string)($_POST['pretty_url'] ?? '0') === '1' ? '1' : '0',
];

$password = (string)($_POST['admin_password'] ?? '');
$password2 = (string)($_POST['admin_password2'] ?? '');
$errors = [];

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
    'CREATE TABLE IF NOT EXISTS users(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at INTEGER NOT NULL
    )'
);
$db->exec(
    'CREATE TABLE IF NOT EXISTS posts(
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        excerpt TEXT NOT NULL DEFAULT \'\',
        content TEXT NOT NULL,
        kind TEXT NOT NULL DEFAULT \'post\',
        tags TEXT NOT NULL DEFAULT \'[]\',
        status TEXT NOT NULL DEFAULT \'draft\',
        published_at INTEGER NOT NULL DEFAULT 0,
        created_at INTEGER NOT NULL,
        updated_at INTEGER NOT NULL
    )'
);
$db->exec('CREATE INDEX IF NOT EXISTS idx_posts_published ON posts(kind, status, published_at DESC, id DESC)');

$now = time();
$settings = i_default_settings();
$settings['site_name'] = $form['site_name'];
$settings['author_name'] = $form['author_name'];
$settings['site_tagline'] = $form['site_tagline'];
$settings['site_description'] = $form['site_tagline'];
$settings['home_intro'] = $form['site_tagline'];
$settings['pretty_url'] = $form['pretty_url'];

$statement = $db->prepare('INSERT OR REPLACE INTO settings(name, value) VALUES(?, ?)');
foreach ($settings as $name => $value) {
    $statement->execute([$name, $value]);
}

$db->prepare('INSERT INTO users(username, password_hash, created_at) VALUES(?, ?, ?)')
    ->execute([$form['admin_username'], password_hash($password, PASSWORD_DEFAULT), $now]);

$db->prepare(
    'INSERT INTO posts(kind, slug, title, tags, excerpt, content, status, published_at, created_at, updated_at)
     VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    'post',
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
    'INSERT INTO posts(kind, slug, title, tags, excerpt, content, status, published_at, created_at, updated_at)
     VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
)->execute([
    'page',
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
