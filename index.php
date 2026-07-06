<?php

declare(strict_types=1);

session_start();

const APP_VERSION = 'v0.1.6';
const DATA_DIR = __DIR__ . '/data';
const CACHE_DIR = __DIR__ . '/cache';
const UPLOAD_DIR = __DIR__ . '/uploads';
const DB_CONFIG_FILE = DATA_DIR . '/config.php';
const DEFAULT_DB_FILE = DATA_DIR . '/blog.sqlite';
const INSTALL_LOCK_FILE = DATA_DIR . '/install.lock';
const SETTINGS_CACHE_FILE = CACHE_DIR . '/settings.php';

function db_file_path(): string
{
    if (is_file(DB_CONFIG_FILE)) {
        $config = include DB_CONFIG_FILE;
        $name = is_array($config) ? basename((string)($config['db_file'] ?? '')) : '';
        if ($name !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*\.sqlite$/', $name)) {
            return DATA_DIR . '/' . $name;
        }
    }

    return DEFAULT_DB_FILE;
}

define('DB_FILE', db_file_path());

function ensure_runtime_dirs(): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }

    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0755, true);
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
}

function redirect_to(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function h(string|int|float|bool|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function x(string|int|float|bool|null $value): string
{
    return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function str_len_u(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function str_sub_u(string $text, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($text, $start, null, 'UTF-8') : mb_substr($text, $start, $length, 'UTF-8');
    }

    return $length === null ? substr($text, $start) : substr($text, $start, $length);
}

function str_lower_u(string $text): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function table_columns(PDO $pdo, string $table): array
{
    $table = preg_replace('/[^a-z_]/i', '', $table) ?: $table;
    $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
    $columns = [];

    foreach ($rows as $row) {
        $columns[(string)$row['name']] = true;
    }

    return $columns;
}

function ensure_schema(PDO $pdo): void
{
    static $done = false;

    if ($done) {
        return;
    }

    $done = true;

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS settings(
            name TEXT PRIMARY KEY,
            value TEXT NOT NULL DEFAULT ''
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            created_at INTEGER NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS posts(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            category_id INTEGER,
            slug TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            excerpt TEXT NOT NULL DEFAULT '',
            content TEXT NOT NULL,
            kind TEXT NOT NULL DEFAULT 'post',
            tags TEXT NOT NULL DEFAULT '[]',
            views INTEGER NOT NULL DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'draft',
            published_at INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS categories(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            description TEXT NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        )"
    );

    $columns = table_columns($pdo, 'posts');

    if (!isset($columns['category_id'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN category_id INTEGER");
    }

    if (!isset($columns['kind'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN kind TEXT NOT NULL DEFAULT 'post'");
    }

    if (!isset($columns['tags'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN tags TEXT NOT NULL DEFAULT '[]'");
    }

    if (!isset($columns['views'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN views INTEGER NOT NULL DEFAULT 0");
    }

    $pdo->exec("UPDATE posts SET kind = 'post' WHERE kind IS NULL OR trim(kind) = ''");
    $pdo->exec("UPDATE posts SET tags = '[]' WHERE tags IS NULL OR trim(tags) = ''");
    $pdo->exec("UPDATE posts SET views = 0 WHERE views IS NULL");
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_public ON posts(kind, status, published_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_kind_updated ON posts(kind, updated_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category_id, kind, status, published_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_categories_sort ON categories(sort_order ASC, id DESC)');
}

function db(): PDO
{
    static $db;

    if ($db instanceof PDO) {
        return $db;
    }

    ensure_runtime_dirs();

    $db = new PDO('sqlite:' . DB_FILE, null, null, [
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

    ensure_schema($db);

    return $db;
}

function q(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row ?: null;
}

function all_rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = []): mixed
{
    return q($sql, $params)->fetchColumn();
}

function default_settings(): array
{
    return [
        'site_name' => 'Simple PHP Blog',
        'author_name' => 'Admin',
        'site_url' => '',
        'site_tagline' => 'A small PHP blog running on one main entry file.',
        'site_description' => 'A simple PHP + SQLite blog inspired by Hugo Paper.',
        'home_intro' => '安静地写点东西，保留足够留白，让文章本身站到前面。',
        'site_footer' => '',
        'logo_url' => './logo.png',
        'footer_beian' => '',
        'posts_per_page' => '6',
        'pretty_url' => '0',
    ];
}

function settings_cache(bool $refresh = false): array
{
    static $settings = null;

    if (!$refresh && is_array($settings)) {
        return $settings;
    }

    $settings = default_settings();

    if (!$refresh && is_file(SETTINGS_CACHE_FILE)) {
        $cached = include SETTINGS_CACHE_FILE;
        if (is_array($cached)) {
            return $settings = array_merge($settings, $cached);
        }
    }

    try {
        foreach (all_rows('SELECT name, value FROM settings') as $row) {
            $settings[(string)$row['name']] = (string)$row['value'];
        }

        ensure_runtime_dirs();
        file_put_contents(SETTINGS_CACHE_FILE, "<?php\nreturn " . var_export($settings, true) . ";\n", LOCK_EX);
    } catch (Throwable) {
    }

    return $settings;
}

function setting(string $key, string $default = ''): string
{
    $settings = settings_cache();
    return (string)($settings[$key] ?? $default);
}

function save_settings(array $values): void
{
    $statement = db()->prepare('INSERT OR REPLACE INTO settings(name, value) VALUES(?, ?)');

    foreach ($values as $name => $value) {
        $statement->execute([(string)$name, (string)$value]);
    }

    settings_cache(true);
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

    if ($sessionToken === '' || !hash_equals($sessionToken, $token)) {
        simple_error_page('请求已失效', '请刷新页面后重试。', 422);
    }
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ensure_upload_year_dir(): array
{
    ensure_runtime_dirs();

    $year = date('Y');
    $dir = UPLOAD_DIR . '/' . $year;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $htaccess = UPLOAD_DIR . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents(
            $htaccess,
            "Options -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .cgi .pl .py .rb .asp .aspx .jsp\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8|phar|cgi|pl|py|rb|asp|aspx|jsp)$\">\n  Require all denied\n</FilesMatch>\n",
            LOCK_EX
        );
    }

    return [$year, $dir];
}

function upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => '文件超过服务器允许的大小。',
        UPLOAD_ERR_PARTIAL => '文件只上传了一部分。',
        UPLOAD_ERR_NO_FILE => '没有选择文件。',
        UPLOAD_ERR_NO_TMP_DIR => '服务器缺少临时目录。',
        UPLOAD_ERR_CANT_WRITE => '服务器无法写入文件。',
        UPLOAD_ERR_EXTENSION => '上传被服务器扩展拦截。',
        default => '上传失败。',
    };
}

function handle_attachment_upload(): void
{
    require_admin();
    verify_csrf();

    $files = $_FILES['attachments'] ?? null;
    if (!is_array($files) || !isset($files['name'], $files['tmp_name'], $files['error'], $files['size'])) {
        json_response(['ok' => false, 'error' => '没有收到附件。'], 400);
    }

    [$year, $dir] = ensure_upload_year_dir();
    $maxSize = 30 * 1024 * 1024;
    $blockedExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phar', 'cgi', 'pl', 'py', 'rb', 'asp', 'aspx', 'jsp', 'html', 'htm', 'js', 'mjs', 'sh', 'bat', 'cmd', 'exe', 'dll'];
    $names = is_array($files['name']) ? $files['name'] : [$files['name']];
    $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
    $errors = is_array($files['error']) ? $files['error'] : [$files['error']];
    $sizes = is_array($files['size']) ? $files['size'] : [$files['size']];
    $uploaded = [];
    $failed = [];

    foreach ($names as $i => $originalName) {
        $originalName = (string)$originalName;
        $error = (int)($errors[$i] ?? UPLOAD_ERR_NO_FILE);
        $tmpName = (string)($tmpNames[$i] ?? '');
        $size = (int)($sizes[$i] ?? 0);

        if ($error !== UPLOAD_ERR_OK) {
            $failed[] = ['name' => $originalName, 'error' => upload_error_message($error)];
            continue;
        }

        if ($size < 1 || $size > $maxSize) {
            $failed[] = ['name' => $originalName, 'error' => '每个附件最大 30M。'];
            continue;
        }

        if (!is_uploaded_file($tmpName)) {
            $failed[] = ['name' => $originalName, 'error' => '临时文件无效。'];
            continue;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = 'bin';
        }

        if (in_array($extension, $blockedExtensions, true)) {
            $failed[] = ['name' => $originalName, 'error' => '不允许上传脚本或可执行文件。'];
            continue;
        }

        $safeExtension = preg_replace('/[^a-z0-9]+/i', '', $extension) ?: 'bin';
        $timestamp = str_replace('.', '', sprintf('%.6F', microtime(true)));
        $filename = $timestamp . '-' . bin2hex(random_bytes(3)) . '.' . $safeExtension;
        $target = $dir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $target)) {
            $failed[] = ['name' => $originalName, 'error' => '保存附件失败。'];
            continue;
        }

        $isImage = @getimagesize($target) !== false;
        $url = asset_url('uploads/' . $year . '/' . $filename);
        $label = trim(pathinfo($originalName, PATHINFO_FILENAME)) ?: $filename;
        $markdown = $isImage ? '![' . $label . '](' . $url . ')' : '[' . $label . '](' . $url . ')';

        $uploaded[] = [
            'name' => $originalName,
            'url' => $url,
            'markdown' => $markdown,
            'is_image' => $isImage,
            'size' => $size,
        ];
    }

    json_response([
        'ok' => $uploaded !== [],
        'files' => $uploaded,
        'errors' => $failed,
    ], $uploaded !== [] ? 200 : 400);
}

function current_admin(): ?array
{
    static $loaded = false;
    static $admin = null;

    if ($loaded) {
        return $admin;
    }

    $loaded = true;
    $id = (int)($_SESSION['admin_id'] ?? 0);

    if ($id < 1) {
        return $admin = null;
    }

    return $admin = one('SELECT id, username, created_at FROM users WHERE id = ?', [$id]);
}

function is_admin(): bool
{
    return current_admin() !== null;
}

function require_admin(): void
{
    if (!is_admin()) {
        set_flash('error', '请先登录后台。');
        redirect_to(url_for('login'));
    }
}

function set_route_params(array $params): void
{
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $_GET[$key] = (string)$value;
        $_REQUEST[$key] = (string)$value;
    }
}

function mark_route_not_found(): void
{
    $_GET['__route_not_found'] = '1';
    $_REQUEST['__route_not_found'] = '1';
}

function apply_pretty_route(): void
{
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    $path = parse_url($uri, PHP_URL_PATH);

    if (!is_string($path) || $path === '') {
        return;
    }

    $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $base = rtrim(str_replace('\\', '/', dirname($script)), '/');

    if ($script !== '' && $script !== '/' && ($path === $script || str_starts_with($path, $script . '/'))) {
        $path = substr($path, strlen($script)) ?: '/';
    } elseif ($base !== '' && $base !== '/' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = substr($path, strlen($base)) ?: '/';
    }

    $path = '/' . trim(rawurldecode($path), '/');

    if ($path === '/' || $path === '/index.php') {
        return;
    }

    if (preg_match('#^/rss\.xml$#i', $path)) {
        set_route_params(['a' => 'rss']);
        return;
    }

    if (preg_match('#^/page/(\d+)/?$#i', $path, $matches)) {
        set_route_params(['a' => 'home', 'p' => $matches[1]]);
        return;
    }

    if (preg_match('#^/tags/?$#i', $path)) {
        set_route_params(['a' => 'tags']);
        return;
    }

    if (preg_match('#^/tag/(.+)$#u', $path, $matches)) {
        set_route_params(['a' => 'tag', 'slug' => trim($matches[1], '/')]);
        return;
    }

    if (preg_match('#^/pages/(.+)$#u', $path, $matches)) {
        set_route_params(['a' => 'page', 'slug' => trim($matches[1], '/')]);
        return;
    }

    if (preg_match('#^/archives/?$#i', $path)) {
        set_route_params(['a' => 'archives']);
        return;
    }

    if (preg_match('#^/admin/(posts|categories|settings)/?$#i', $path, $matches)) {
        set_route_params(['a' => 'admin_' . str_lower_u($matches[1])]);
        return;
    }

    if (preg_match('#^/(login|logout|admin|write)/?$#i', $path, $matches)) {
        set_route_params(['a' => str_lower_u($matches[1])]);
        return;
    }

    if (preg_match('#^/edit/(\d+)/?$#', $path, $matches)) {
        set_route_params(['a' => 'edit', 'id' => $matches[1]]);
        return;
    }

    if (preg_match('#^/post/(.+)$#u', $path, $matches)) {
        set_route_params(['a' => 'post', 'slug' => trim($matches[1], '/')]);
        return;
    }

    if (preg_match('#^/([^/]+)/?$#u', $path, $matches)) {
        set_route_params(['a' => 'page', 'slug' => trim($matches[1], '/')]);
        return;
    }

    mark_route_not_found();
}

function app_base_path(): string
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    return $dir === '' || $dir === '.' ? '' : $dir;
}

function app_path(string $path = '/'): string
{
    $base = app_base_path();
    $path = '/' . ltrim($path, '/');
    return ($base !== '' ? $base : '') . ($path === '/' ? '/' : $path);
}

function script_url(): string
{
    return app_path('/index.php');
}

function install_url(): string
{
    return app_path('/install.php');
}

function asset_url(string $path): string
{
    return app_path('/' . ltrim($path, '/'));
}

function use_pretty_url(): bool
{
    return setting('pretty_url', '0') === '1';
}

function url_for(string $route, array $params = []): string
{
    $pretty = use_pretty_url();

    return match ($route) {
        'home' => $pretty ? app_path('/') : script_url(),
        'rss' => $pretty ? app_path('/rss.xml') : script_url() . '?a=rss',
        'archives' => $pretty ? app_path('/archives') : script_url() . '?a=archives',
        'tags' => $pretty ? app_path('/tags') : script_url() . '?a=tags',
        'tag' => $pretty ? app_path('/tag/' . rawurlencode((string)($params['slug'] ?? ''))) : script_url() . '?a=tag&slug=' . rawurlencode((string)($params['slug'] ?? '')),
        'page' => $pretty ? app_path('/' . rawurlencode((string)($params['slug'] ?? ''))) : script_url() . '?a=page&slug=' . rawurlencode((string)($params['slug'] ?? '')),
        'login' => $pretty ? app_path('/login') : script_url() . '?a=login',
        'logout' => $pretty ? app_path('/logout') : script_url() . '?a=logout',
        'admin' => $pretty ? app_path('/admin') : script_url() . '?a=admin',
        'admin_posts' => $pretty ? app_path('/admin/posts') : script_url() . '?a=admin_posts',
        'admin_categories' => $pretty ? app_path('/admin/categories') : script_url() . '?a=admin_categories',
        'admin_settings' => $pretty ? app_path('/admin/settings') : script_url() . '?a=admin_settings',
        'write' => $pretty ? app_path('/write') : script_url() . '?a=write',
        'edit' => $pretty ? app_path('/edit/' . (int)($params['id'] ?? 0)) : script_url() . '?a=edit&id=' . (int)($params['id'] ?? 0),
        'post' => $pretty ? app_path('/post/' . rawurlencode((string)($params['slug'] ?? ''))) : script_url() . '?a=post&slug=' . rawurlencode((string)($params['slug'] ?? '')),
        'save_settings' => script_url() . '?a=save_settings',
        'save_category' => script_url() . '?a=save_category',
        'delete_category' => script_url() . '?a=delete_category',
        'upload_attachment' => script_url() . '?a=upload_attachment',
        'delete_post' => script_url() . '?a=delete_post',
        'change_status' => script_url() . '?a=change_status',
        default => script_url(),
    };
}

function url_with_query(string $url, array $params): string
{
    return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
}

function home_page_url(int $page): string
{
    if ($page <= 1) {
        return url_for('home');
    }

    return use_pretty_url() ? app_path('/page/' . $page) : script_url() . '?p=' . $page;
}

function site_footer_text(): string
{
    $footer = trim(setting('site_footer'));
    if ($footer === '') {
        $footer = '© 2016 - {year} Theme is Ying';
    }
    return str_replace('{year}', date('Y'), $footer);
}

function pretty_date(int $timestamp, bool $withTime = false): string
{
    return date($withTime ? 'Y-m-d H:i' : 'Y-m-d', $timestamp);
}

function datetime_local_value(int $timestamp): string
{
    return date('Y-m-d\TH:i', $timestamp);
}

function site_root_url(): string
{
    $configured = trim(setting('site_url'));
    if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
        return rtrim($configured, '/');
    }

    $https = ((string)($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    $scheme = $https ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');

    return rtrim($scheme . '://' . $host . app_base_path(), '/');
}

function absolute_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $base = site_root_url();
    $appBase = app_base_path();

    if ($appBase !== '') {
        if ($path === $appBase) {
            $path = '/';
        } elseif (str_starts_with($path, $appBase . '/')) {
            $path = substr($path, strlen($appBase));
        }
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function content_kind(array $row): string
{
    return (string)($row['kind'] ?? 'post') === 'page' ? 'page' : 'post';
}

function content_type_label(array $row): string
{
    return content_kind($row) === 'page' ? '页面' : '文章';
}

function content_permalink(array $row): string
{
    return content_kind($row) === 'page'
        ? url_for('page', ['slug' => (string)$row['slug']])
        : url_for('post', ['slug' => (string)$row['slug']]);
}

function content_public_path(array $row): string
{
    $url = content_permalink($row);
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }

    $path = (string)($parts['path'] ?? '');
    if (isset($parts['query']) && $parts['query'] !== '') {
        $path .= '?' . $parts['query'];
    }

    return $path !== '' ? $path : $url;
}

function parse_tags_input(string $raw): array
{
    $parts = preg_split('/[\n,，]+/u', $raw);
    if (!is_array($parts)) {
        $parts = [$raw];
    }

    $map = [];

    foreach ($parts as $part) {
        $label = trim($part);
        if ($label === '') {
            continue;
        }

        $slug = slugify($label);
        if (!isset($map[$slug])) {
            $map[$slug] = $label;
        }
    }

    return array_values($map);
}

function encode_tags(array $tags): string
{
    return json_encode(array_values($tags), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
}

function post_tags(array $post): array
{
    $raw = (string)($post['tags'] ?? '[]');
    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        $tags = [];
        foreach ($decoded as $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if ($value !== '') {
                $tags[] = $value;
            }
        }
        return parse_tags_input(implode(', ', $tags));
    }

    return parse_tags_input($raw);
}

function tag_descriptors(array $post): array
{
    $tags = [];

    foreach (post_tags($post) as $label) {
        $tags[] = ['label' => $label, 'slug' => slugify($label)];
    }

    return $tags;
}

function slugify(string $text): string
{
    $text = str_lower_u(trim($text));
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'post';
}

function unique_slug(string $seed, ?int $excludeId = null): string
{
    $base = slugify($seed);
    $slug = $base;
    $index = 2;

    while (true) {
        $row = $excludeId
            ? one('SELECT id FROM posts WHERE slug = ? AND id != ?', [$slug, $excludeId])
            : one('SELECT id FROM posts WHERE slug = ?', [$slug]);

        if ($row === null) {
            return $slug;
        }

        $slug = $base . '-' . $index;
        $index++;
    }
}

function markdown_to_plain(string $markdown): string
{
    $text = preg_replace('/```.*?```/su', ' ', $markdown) ?? $markdown;
    $text = preg_replace('/!\[[^\]]*]\([^)]+\)/u', ' ', $text) ?? $text;
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/u', '$1', $text) ?? $text;
    $text = preg_replace('/^[#>\-\*\d\.\s]+/mu', '', $text) ?? $text;
    $text = str_replace(['`', '*', '_', '~'], ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function derive_excerpt(string $content, int $length = 140): string
{
    $text = markdown_to_plain($content);

    if ($text === '') {
        return '';
    }

    if (str_len_u($text) <= $length) {
        return $text;
    }

    return rtrim(str_sub_u($text, 0, $length)) . '…';
}

function safe_link_url(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        return '#';
    }

    if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
        return '#';
    }

    if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
        return $url;
    }

    if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
        return $url;
    }

    return '#';
}

function render_inline(string $text): string
{
    $parts = preg_split('/(`[^`]+`)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        $parts = [$text];
    }

    $html = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        if ($part[0] === '`' && substr($part, -1) === '`') {
            $html .= '<code>' . h(substr($part, 1, -1)) . '</code>';
            continue;
        }

        $escaped = h($part);

        $escaped = preg_replace_callback(
            '/!\[(.*?)]\(([^\s)]+)\)/u',
            static function (array $matches): string {
                $src = safe_link_url($matches[2]);
                if ($src === '#') {
                    return $matches[0];
                }

                return '<img src="' . h($src) . '" alt="' . $matches[1] . '" loading="lazy">';
            },
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace_callback(
            '/(?<!!)\[(.+?)]\(([^\s)]+)\)/u',
            static function (array $matches): string {
                $href = safe_link_url($matches[2]);
                if ($href === '#') {
                    return $matches[0];
                }

                $external = preg_match('#^https?://#i', $href) === 1;
                $attrs = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
                return '<a href="' . h($href) . '"' . $attrs . '>' . $matches[1] . '</a>';
            },
            $escaped
        ) ?? $escaped;

        $escaped = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $escaped) ?? $escaped;
        $escaped = preg_replace('/~~(.+?)~~/u', '<del>$1</del>', $escaped) ?? $escaped;

        $html .= $escaped;
    }

    return $html;
}

function markdown_to_html(string $markdown): string
{
    $markdown = trim(str_replace(["\r\n", "\r"], "\n", $markdown));

    if ($markdown === '') {
        return '<p>暂无内容。</p>';
    }

    $lines = explode("\n", $markdown);
    $html = [];
    $paragraph = [];
    $quoteLines = [];
    $listType = null;
    $listItems = [];
    $inCode = false;
    $codeLang = '';
    $codeLines = [];

    $flushParagraph = static function () use (&$paragraph, &$html): void {
        if ($paragraph === []) {
            return;
        }

        $text = trim(implode(' ', array_map('trim', $paragraph)));
        if ($text !== '') {
            $html[] = '<p>' . render_inline($text) . '</p>';
        }

        $paragraph = [];
    };

    $flushList = static function () use (&$listType, &$listItems, &$html): void {
        if ($listType === null || $listItems === []) {
            $listType = null;
            $listItems = [];
            return;
        }

        $items = [];
        foreach ($listItems as $item) {
            $items[] = '<li>' . render_inline(trim($item)) . '</li>';
        }

        $html[] = '<' . $listType . '>' . implode('', $items) . '</' . $listType . '>';
        $listType = null;
        $listItems = [];
    };

    $flushQuote = static function () use (&$quoteLines, &$html): void {
        if ($quoteLines === []) {
            return;
        }

        $html[] = '<blockquote>' . markdown_to_html(implode("\n", $quoteLines)) . '</blockquote>';
        $quoteLines = [];
    };

    $flushCode = static function () use (&$inCode, &$codeLang, &$codeLines, &$html): void {
        if (!$inCode) {
            return;
        }

        $class = $codeLang !== '' ? ' class="language-' . h($codeLang) . '"' : '';
        $html[] = '<pre><code' . $class . '>' . h(implode("\n", $codeLines)) . '</code></pre>';
        $inCode = false;
        $codeLang = '';
        $codeLines = [];
    };

    foreach ($lines as $line) {
        if (preg_match('/^```([\w-]+)?\s*$/', $line, $matches)) {
            if ($inCode) {
                $flushCode();
            } else {
                $flushParagraph();
                $flushList();
                $flushQuote();
                $inCode = true;
                $codeLang = trim((string)($matches[1] ?? ''));
                $codeLines = [];
            }
            continue;
        }

        if ($inCode) {
            $codeLines[] = $line;
            continue;
        }

        if (preg_match('/^\s*$/', $line)) {
            $flushParagraph();
            $flushList();
            $flushQuote();
            continue;
        }

        if (preg_match('/^>\s?(.*)$/u', $line, $matches)) {
            $flushParagraph();
            $flushList();
            $quoteLines[] = $matches[1];
            continue;
        }

        $flushQuote();

        if (preg_match('/^---{2,}\s*$/', $line)) {
            $flushParagraph();
            $flushList();
            $html[] = '<hr>';
            continue;
        }

        if (preg_match('/^(#{1,3})\s+(.+)$/u', $line, $matches)) {
            $flushParagraph();
            $flushList();
            $level = strlen($matches[1]);
            $html[] = '<h' . $level . '>' . render_inline(trim($matches[2])) . '</h' . $level . '>';
            continue;
        }

        if (preg_match('/^\s*[-*]\s+(.+)$/u', $line, $matches)) {
            $flushParagraph();
            if ($listType !== 'ul') {
                $flushList();
                $listType = 'ul';
            }
            $listItems[] = $matches[1];
            continue;
        }

        if (preg_match('/^\s*\d+\.\s+(.+)$/u', $line, $matches)) {
            $flushParagraph();
            if ($listType !== 'ol') {
                $flushList();
                $listType = 'ol';
            }
            $listItems[] = $matches[1];
            continue;
        }

        $paragraph[] = $line;
    }

    if ($inCode) {
        $flushCode();
    }

    $flushQuote();
    $flushList();
    $flushParagraph();

    return implode("\n", $html);
}

function post_state(array $post): array
{
    if ((string)$post['status'] !== 'published') {
        return ['label' => '草稿', 'class' => 'draft'];
    }

    if ((int)$post['published_at'] > time()) {
        return ['label' => '定时', 'class' => 'scheduled'];
    }

    return ['label' => '已发布', 'class' => 'published'];
}

function is_live_content(array $post): bool
{
    return (string)$post['status'] === 'published' && (int)$post['published_at'] > 0 && (int)$post['published_at'] <= time();
}

function is_live_post(array $post): bool
{
    return content_kind($post) === 'post' && is_live_content($post);
}

function fetch_published_posts(int $limit, int $offset): array
{
    $limit = max(1, $limit);
    $offset = max(0, $offset);

    return all_rows(
        'SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
        ['post', 'published', time()]
    );
}

function count_published_posts(): int
{
    return (int)val('SELECT COUNT(*) FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['post', 'published', time()]);
}

function fetch_content_by_identifier(string $kind, string $slug, bool $allowPreview = false): ?array
{
    if ($slug === '') {
        return null;
    }

    if (ctype_digit($slug)) {
        $row = one('SELECT * FROM posts WHERE id = ? AND kind = ?', [(int)$slug, $kind]);
        if ($row && ($allowPreview || is_live_content($row))) {
            return $row;
        }
    }

    $row = one('SELECT * FROM posts WHERE slug = ? AND kind = ?', [$slug, $kind]);
    if ($row && ($allowPreview || is_live_content($row))) {
        return $row;
    }

    return null;
}

function fetch_post_by_identifier(string $slug, bool $allowPreview = false): ?array
{
    return fetch_content_by_identifier('post', $slug, $allowPreview);
}

function fetch_page_by_identifier(string $slug, bool $allowPreview = false): ?array
{
    return fetch_content_by_identifier('page', $slug, $allowPreview);
}

function fetch_post_by_id(int $id): ?array
{
    return $id > 0 ? one('SELECT * FROM posts WHERE id = ?', [$id]) : null;
}

function increment_content_views(array $post): void
{
    if (is_admin() || !is_live_content($post)) {
        return;
    }

    q('UPDATE posts SET views = views + 1 WHERE id = ?', [(int)$post['id']]);
}

function fetch_categories(): array
{
    return all_rows(
        'SELECT c.*, COUNT(p.id) AS post_count
         FROM categories c
         LEFT JOIN posts p ON p.category_id = c.id AND p.kind = ?
         GROUP BY c.id
         ORDER BY c.sort_order ASC, c.id DESC',
        ['post']
    );
}

function category_options(): array
{
    return all_rows('SELECT id, name FROM categories ORDER BY sort_order ASC, id DESC');
}

function category_name(?int $id): string
{
    if (!$id) {
        return '未分类';
    }

    return (string)(val('SELECT name FROM categories WHERE id = ?', [$id]) ?: '未分类');
}

function unique_category_slug(string $seed, ?int $excludeId = null): string
{
    $base = slugify($seed);
    $slug = $base;
    $i = 2;

    while (true) {
        $existing = $excludeId
            ? one('SELECT id FROM categories WHERE slug = ? AND id <> ?', [$slug, $excludeId])
            : one('SELECT id FROM categories WHERE slug = ?', [$slug]);

        if (!$existing) {
            return $slug;
        }

        $slug = $base . '-' . $i++;
    }
}

function validate_category_input(array $input, ?array $existing = null): array
{
    $name = trim((string)($input['name'] ?? ''));
    $slugInput = trim((string)($input['slug'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $sortOrder = (int)($input['sort_order'] ?? 0);
    $errors = [];

    if ($name === '') {
        $errors[] = '分类名称不能为空。';
    }

    $slug = unique_category_slug($slugInput !== '' ? $slugInput : $name, $existing ? (int)$existing['id'] : null);

    return [[
        'name' => $name,
        'slug' => $slug,
        'description' => $description,
        'sort_order' => $sortOrder,
    ], $errors];
}

function fetch_archive_posts(): array
{
    return all_rows('SELECT id, slug, title, published_at, tags, kind FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC', ['post', 'published', time()]);
}

function archive_groups(): array
{
    $groups = [];

    foreach (fetch_archive_posts() as $post) {
        $label = date('Y 年 m 月', (int)$post['published_at']);
        $groups[$label][] = $post;
    }

    return $groups;
}

function theme_logo_url(): string
{
    if (is_file(__DIR__ . '/logo.png')) {
        return asset_url('logo.png');
    }

    return trim(setting('logo_url', default_settings()['logo_url'])) ?: default_settings()['logo_url'];
}

function public_quote(): string
{
    $quote = trim(setting('home_intro'));

    if ($quote === '') {
        $quote = trim(setting('site_tagline'));
    }

    return $quote !== '' ? $quote : setting('site_name', default_settings()['site_name']);
}

function public_icon(string $name): string
{
    return match ($name) {
        'pen' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25Zm14.71-9.04a1.003 1.003 0 0 0 0-1.42l-2.5-2.5a1.003 1.003 0 0 0-1.42 0l-1.46 1.46 3.75 3.75 1.63-1.29Z" fill="currentColor"/></svg>',
        'tag' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 12-9 9-9-9 9-9 9 9Zm-12.59 0L12 15.59 15.59 12 12 8.41 8.41 12ZM12 10.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z" fill="currentColor"/></svg>',
        'rss' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 18a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm-2-8v3a7 7 0 0 1 7 7h3c0-5.52-4.48-10-10-10Zm0-5v3c6.63 0 12 5.37 12 12h3C19 11.16 12.84 5 5 5H4Z" fill="currentColor"/></svg>',
        'arrow-up' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5.5 5.5 12l1.41 1.41L11 9.33V20h2V9.33l4.09 4.08L18.5 12 12 5.5Z" fill="currentColor"/></svg>',
        default => '',
    };
}

function render_public_post_list(array $posts): string
{
    ob_start();
    ?>
    <?php foreach ($posts as $post): ?>
      <div class="posts">
        <div class="post">
          <div class="time"><?= h(date('F j, Y', (int)$post['published_at'])) ?></div>
          <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?></a>
        </div>
      </div>
    <?php endforeach; ?>
    <?php
    return (string)ob_get_clean();
}

function fetch_admin_posts(): array
{
    return all_rows(
        "SELECT p.*, c.name AS category_name
         FROM posts p
         LEFT JOIN categories c ON c.id = p.category_id
         ORDER BY
            CASE WHEN p.status = 'published' THEN p.published_at ELSE p.updated_at END DESC,
            p.id DESC"
    );
}

function admin_metrics(): array
{
    $now = time();
    $totalPosts = (int)val('SELECT COUNT(*) FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['post', 'published', $now]);
    $publishedPosts = (int)val('SELECT COUNT(*) FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['post', 'published', $now]);
    $totalViews = (int)val('SELECT COALESCE(SUM(views), 0) FROM posts WHERE status = ? AND published_at <= ?', ['published', $now]);

    return [
        'total_posts' => $totalPosts,
        'published' => $publishedPosts,
        'pages' => (int)val('SELECT COUNT(*) FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['page', 'published', $now]),
        'drafts' => (int)val("SELECT COUNT(*) FROM posts WHERE status = 'draft'"),
        'scheduled' => (int)val('SELECT COUNT(*) FROM posts WHERE status = ? AND published_at > ?', ['published', $now]),
        'categories' => (int)val('SELECT COUNT(*) FROM categories'),
        'total_views' => $totalViews,
        'avg_views' => $totalPosts > 0 ? (int)floor($totalViews / $totalPosts) : 0,
        'top_viewed' => all_rows('SELECT id, slug, title, views FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY views DESC, updated_at DESC LIMIT 5', ['post', 'published', $now]),
    ];
}

function post_neighbors(array $post): array
{
    if (!is_live_post($post)) {
        return ['newer' => null, 'older' => null];
    }

    $publishedAt = (int)$post['published_at'];
    $id = (int)$post['id'];

    $newer = one(
        'SELECT id, slug, title FROM posts
         WHERE kind = ? AND status = ? AND published_at <= ? AND (published_at > ? OR (published_at = ? AND id > ?))
         ORDER BY published_at ASC, id ASC LIMIT 1',
        ['post', 'published', time(), $publishedAt, $publishedAt, $id]
    );

    $older = one(
        'SELECT id, slug, title FROM posts
         WHERE kind = ? AND status = ? AND published_at <= ? AND (published_at < ? OR (published_at = ? AND id < ?))
         ORDER BY published_at DESC, id DESC LIMIT 1',
        ['post', 'published', time(), $publishedAt, $publishedAt, $id]
    );

    return ['newer' => $newer, 'older' => $older];
}

function fetch_nav_pages(): array
{
    return all_rows(
        'SELECT id, slug, title, kind, status, published_at, updated_at, created_at FROM posts
         WHERE kind = ? AND status = ? AND published_at <= ?
         ORDER BY published_at ASC, id ASC LIMIT 6',
        ['page', 'published', time()]
    );
}

function fetch_feed_posts(int $limit = 20): array
{
    return all_rows(
        'SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC LIMIT ' . max(1, $limit),
        ['post', 'published', time()]
    );
}

function tag_index_data(): array
{
    $map = [];
    $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC', ['post', 'published', time()]);

    foreach ($posts as $post) {
        foreach (post_tags($post) as $label) {
            $slug = slugify($label);
            if (!isset($map[$slug])) {
                $map[$slug] = ['slug' => $slug, 'label' => $label, 'count' => 0];
            }
            $map[$slug]['count']++;
        }
    }

    $tags = array_values($map);
    usort(
        $tags,
        static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: strcmp((string)$a['label'], (string)$b['label']);
        }
    );

    return $tags;
}

function fetch_posts_by_tag_slug(string $slug): array
{
    $slug = trim($slug);
    if ($slug === '') {
        return [];
    }

    $matches = [];
    $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC', ['post', 'published', time()]);

    foreach ($posts as $post) {
        foreach (tag_descriptors($post) as $tag) {
            if ($tag['slug'] === $slug) {
                $matches[] = $post;
                break;
            }
        }
    }

    return $matches;
}

function tag_label_by_slug(string $slug): ?string
{
    foreach (tag_index_data() as $tag) {
        if ((string)$tag['slug'] === $slug) {
            return (string)$tag['label'];
        }
    }

    return null;
}

function validate_post_input(array $input, ?array $existing = null): array
{
    $title = trim((string)($input['title'] ?? ''));
    $content = trim((string)($input['content'] ?? ''));
    $excerpt = trim((string)($input['excerpt'] ?? ''));
    $kind = (string)($input['kind'] ?? 'post');
    $categoryId = (int)($input['category_id'] ?? 0);
    $tagsInput = trim((string)($input['tags_input'] ?? ''));
    $status = (string)($input['status'] ?? 'draft');
    $publishedInput = trim((string)($input['published_at'] ?? ''));
    $errors = [];

    if ($title === '') {
        $errors[] = '标题不能为空。';
    }

    if ($content === '') {
        $errors[] = '正文不能为空。';
    }

    $kind = $kind === 'page' ? 'page' : 'post';
    $categoryId = $kind === 'post' && $categoryId > 0 && one('SELECT id FROM categories WHERE id = ?', [$categoryId]) ? $categoryId : null;
    $status = $status === 'published' ? 'published' : 'draft';
    $publishedAt = (int)($existing['published_at'] ?? 0);

    if ($publishedInput !== '') {
        $parsed = strtotime(str_replace('T', ' ', $publishedInput));
        if ($parsed === false) {
            $errors[] = '发布时间格式不正确。';
        } else {
            $publishedAt = $parsed;
        }
    }

    if ($status === 'published' && $publishedAt < 1) {
        $publishedAt = time();
    }

    $seed = trim((string)($input['slug'] ?? ''));
    $slug = unique_slug($seed !== '' ? $seed : $title, $existing ? (int)$existing['id'] : null);
    $excerpt = $excerpt !== '' ? $excerpt : derive_excerpt($content);
    $tags = encode_tags(parse_tags_input($tagsInput));

    return [[
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'content' => $content,
        'kind' => $kind,
        'category_id' => $categoryId,
        'tags' => $tags,
        'status' => $status,
        'published_at' => $publishedAt,
    ], $errors];
}

function render_layout(string $title, string $content, array $options = []): void
{
    $siteName = setting('site_name', default_settings()['site_name']);
    $fullTitle = $title === $siteName ? $siteName : $title . ' · ' . $siteName;
    $description = (string)($options['description'] ?? setting('site_description', setting('site_tagline')));
    $active = (string)($options['active'] ?? '');
    $wide = !empty($options['wide']);
    $mode = (string)($options['mode'] ?? 'admin');
    $flash = pull_flash();
    $admin = current_admin();
    $navPages = fetch_nav_pages();
    $status = (int)($options['status'] ?? 200);
    $bodyClass = $mode === 'public' ? 'theme-public' : 'theme-admin';

    if ($mode !== 'public' && !$admin) {
        $bodyClass .= ' theme-admin--guest';
    }

    http_response_code($status);
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= h($description) ?>">
  <title><?= h($fullTitle) ?></title>
  <link rel="stylesheet" href="<?= h(asset_url('index.css')) ?>?v=<?= h(APP_VERSION) ?>">
</head>
<body class="<?= h($bodyClass) ?>">
  <?php if ($mode === 'public'): ?>
    <div class="main">
      <div class="container">
        <div class="header">
          <div class="site-description">
            <div class="site-intro">
              <a class="site-avatar-link" href="<?= h(url_for('home')) ?>" aria-label="<?= h($siteName) ?>">
                <img class="site-avatar" src="<?= h(theme_logo_url()) ?>" width="80" height="80" alt="<?= h($siteName) ?>">
              </a>
              <div class="site-copy">
                <h2><span><?= h(public_quote()) ?></span></h2>
              </div>
            </div>
            <nav class="nav social" aria-label="Quick links">
              <ul class="flat">
                <li>
                  <a class="social-link<?= $active === 'rss' ? ' is-active' : '' ?>" href="<?= h(url_for('rss')) ?>" title="RSS" aria-label="RSS">
                    <?= public_icon('rss') ?>
                  </a>
                </li>
              </ul>
            </nav>
          </div>
          <nav class="nav" aria-label="Primary">
            <ul class="flat">
              <li class="<?= $active === 'home' ? 'active' : '' ?>"><a href="<?= h(url_for('home')) ?>">首页</a></li>
              <li class="<?= $active === 'tags' ? 'active' : '' ?>"><a href="<?= h(url_for('tags')) ?>">标签</a></li>
              <li class="<?= $active === 'archives' ? 'active' : '' ?>"><a href="<?= h(url_for('archives')) ?>">归档</a></li>
              <?php foreach ($navPages as $page): ?>
                <li class="<?= $active === 'page:' . $page['slug'] ? 'active' : '' ?>">
                  <a href="<?= h(content_permalink($page)) ?>"><?= h($page['title']) ?></a>
                </li>
              <?php endforeach; ?>
            </ul>
          </nav>
        </div>

        <?php if ($flash): ?>
          <div class="flash flash--<?= h((string)$flash['type']) ?> flash--public"><?= h((string)$flash['message']) ?></div>
        <?php endif; ?>

        <?= $content ?>

        <div class="footer">
          <nav class="nav">
            <?= h(site_footer_text()) ?><br>
            <?php $beian = trim(setting('footer_beian')); ?>
            <?php if ($beian !== ''): ?>
              <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a><br>
            <?php endif; ?>
            <span>Powered by PHP + SQLite</span>
          </nav>
        </div>
      </div>
    </div>
    <a id="to_top" href="#" class="to_top" aria-label="回到顶部"><?= public_icon('arrow-up') ?></a>
  <?php else: ?>
    <div class="site-frame">
      <header class="site-header">
        <div class="site-header__inner">
          <a class="site-brand" href="<?= h($admin ? url_for('admin') : url_for('home')) ?>">
            <img class="site-brand__logo" src="<?= h(theme_logo_url()) ?>" width="44" height="44" alt="<?= h($siteName) ?>">
            <span class="site-brand__copy">
              <strong class="site-brand__title"><?= h($siteName) ?></strong>
              <span class="site-brand__meta"><?= $admin ? 'Blog Admin' : 'Admin Entry' ?></span>
            </span>
          </a>
          <nav class="site-nav site-nav--admin" aria-label="Primary">
            <?php if ($admin): ?>
              <a class="nav-link<?= $active === 'admin' ? ' is-active' : '' ?>" href="<?= h(url_for('admin')) ?>">管理后台</a>
              <a class="nav-link nav-link--pill<?= in_array($active, ['write', 'edit'], true) ? ' is-active' : '' ?>" href="<?= h(url_for('write')) ?>">撰写文章</a>
              <a class="nav-link" href="<?= h(url_for('logout')) ?>">退出</a>
            <?php else: ?>
              <a class="nav-link" href="<?= h(url_for('home')) ?>">返回首页</a>
              <a class="nav-link<?= $active === 'login' ? ' is-active' : '' ?>" href="<?= h(url_for('login')) ?>">登录</a>
            <?php endif; ?>
          </nav>
        </div>
      </header>

      <main class="main-wrap<?= $wide ? ' main-wrap--wide' : '' ?>">
        <?php if ($flash): ?>
          <div class="flash flash--<?= h((string)$flash['type']) ?>"><?= h((string)$flash['message']) ?></div>
        <?php endif; ?>
        <?= $content ?>
      </main>

      <footer class="site-footer">
        <div class="site-footer__inner">
          <span><?= h(site_footer_text()) ?></span>
          <span class="site-footer__meta">PHP · SQLite · <?= h(APP_VERSION) ?></span>
        </div>
      </footer>
    </div>
  <?php endif; ?>
  <script src="<?= h(asset_url('index.js')) ?>?v=<?= h(APP_VERSION) ?>"></script>
</body>
</html>
<?php
    exit;
}

function admin_icon(string $name): string
{
    $attrs = 'class="admin-side__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';

    $paths = match ($name) {
        'overview' => '<rect x="3" y="3" width="7" height="9" rx="1"></rect><rect x="14" y="3" width="7" height="5" rx="1"></rect><rect x="14" y="12" width="7" height="9" rx="1"></rect><rect x="3" y="16" width="7" height="5" rx="1"></rect>',
        'home' => '<path d="M3 10.5 12 3l9 7.5"></path><path d="M5 10v10h14V10"></path><path d="M9 20v-6h6v6"></path>',
        'write' => '<path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"></path>',
        'posts' => '<path d="M8 6h13"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M3 6h.01"></path><path d="M3 12h.01"></path><path d="M3 18h.01"></path>',
        'categories' => '<path d="M3 6h7l2 2h9v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"></path>',
        'settings' => '<path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5z"></path><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1A2 2 0 1 1 4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9L4.2 7A2 2 0 1 1 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5h.1a1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 1 1 19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"></path>',
        default => '<circle cx="12" cy="12" r="8"></circle>',
    };

    return '<svg ' . $attrs . '>' . $paths . '</svg>';
}

function render_admin_sidebar(string $active, array $summary = []): string
{
    $siteName = setting('site_name', default_settings()['site_name']);
    $admin = current_admin();
    $adminName = (string)($admin['username'] ?? 'Admin');
    $adminInitial = str_sub_u($adminName, 0, 1);
    $links = [
        [
            'label' => '博客概览',
            'icon' => 'overview',
            'note' => '浏览与统计',
            'href' => url_for('admin'),
            'active' => $active === 'admin',
        ],
        [
            'label' => '撰写文章',
            'icon' => 'write',
            'note' => '发布文章或页面',
            'href' => url_for('write'),
            'active' => in_array($active, ['write', 'edit'], true),
        ],
        [
            'label' => '文章管理',
            'icon' => 'posts',
            'note' => '列表与发布',
            'href' => url_for('admin_posts'),
            'active' => $active === 'posts',
        ],
        [
            'label' => '分类管理',
            'icon' => 'categories',
            'note' => '分类与排序',
            'href' => url_for('admin_categories'),
            'active' => $active === 'categories',
        ],
        [
            'label' => '站点设置',
            'icon' => 'settings',
            'note' => '基础配置',
            'href' => url_for('admin_settings'),
            'active' => $active === 'settings',
        ],
    ];

    ob_start();
    ?>
    <aside class="admin-side admin-animate admin-animate--1">
      <a class="admin-side__brand" href="<?= h(url_for('admin')) ?>" title="<?= h($siteName) ?>" aria-label="<?= h($siteName) ?>">
        <?= admin_icon('home') ?>
        <span class="admin-side__brand-text"><?= h($siteName) ?></span>
      </a>

      <section class="admin-side__panel admin-side__panel--nav">
        <p class="admin-side__eyebrow">管理导航</p>
        <nav class="admin-side__nav" aria-label="Admin">
          <?php foreach ($links as $link): ?>
            <a class="admin-side__link<?= $link['active'] ? ' is-active' : '' ?>" href="<?= h((string)$link['href']) ?>" title="<?= h((string)$link['label']) ?>" aria-label="<?= h((string)$link['label']) ?>"<?= $link['active'] ? ' aria-current="page"' : '' ?>>
              <?= admin_icon((string)$link['icon']) ?>
              <strong><?= h((string)$link['label']) ?></strong>
              <span><?= h((string)$link['note']) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </section>

      <?php if ($summary !== []): ?>
        <section class="admin-side__panel admin-side__panel--subtle">
          <p class="admin-side__eyebrow"><?= h((string)($summary['title'] ?? '说明')) ?></p>

          <?php if (!empty($summary['stats']) && is_array($summary['stats'])): ?>
            <dl class="admin-side__stats">
              <?php foreach ($summary['stats'] as $item): ?>
                <?php if (!is_array($item)) { continue; } ?>
                <div>
                  <dt><?= h((string)($item['label'] ?? '')) ?></dt>
                  <dd><?= h((string)($item['value'] ?? '')) ?></dd>
                </div>
              <?php endforeach; ?>
            </dl>
          <?php elseif (!empty($summary['items']) && is_array($summary['items'])): ?>
            <ul class="admin-side__list">
              <?php foreach ($summary['items'] as $item): ?>
                <li><?= h((string)$item) ?></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <div class="admin-side__footer">
        <span class="admin-side__avatar"><?= h($adminInitial) ?></span>
        <span class="admin-side__footer-text"><?= h($adminName) ?></span>
        <a class="admin-side__logout" href="<?= h(url_for('logout')) ?>">退出登录</a>
      </div>
    </aside>
    <?php

    return (string)ob_get_clean();
}

function render_admin_topbar(string $title, string $actionLabel = '', string $actionUrl = ''): string
{
    ob_start();
    ?>
    <div class="admin-topbar">
      <div class="admin-crumb">控制台 / <b><?= h($title) ?></b></div>
      <div class="admin-topbar__actions">
        <?php if ($actionLabel !== '' && $actionUrl !== ''): ?>
          <a class="button" href="<?= h($actionUrl) ?>"><?= h($actionLabel) ?></a>
        <?php endif; ?>
      </div>
    </div>
    <?php
    return (string)ob_get_clean();
}

function simple_error_page(string $title, string $message, int $status = 400): void
{
    ob_start();
    ?>
    <article class="post">
      <h1 class="post-title"><?= h($title) ?></h1>
      <div class="post-content">
        <p><?= h($message) ?></p>
        <p><a href="<?= h(url_for('home')) ?>">回到首页</a></p>
      </div>
    </article>
    <?php
    $content = (string)ob_get_clean();

    render_layout($title, $content, [
        'mode' => 'public',
        'status' => $status,
        'description' => $message,
    ]);
}

function render_tag_chips(array $post, string $class = 'tag-list'): string
{
    $tags = tag_descriptors($post);
    if ($tags === []) {
        return '';
    }

    ob_start();
    ?>
    <div class="<?= h($class) ?>">
      <?php foreach ($tags as $tag): ?>
        <a class="tag-chip" href="<?= h(url_for('tag', ['slug' => $tag['slug']])) ?>"><?= h($tag['label']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php
    return (string)ob_get_clean();
}

function render_home(int $page): void
{
    $page = max(1, $page);
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));

    if ($page > $totalPages && $total > 0) {
        simple_error_page('页面不存在', '分页超出了范围。', 404);
    }

    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);
    $siteName = setting('site_name', default_settings()['site_name']);
    $tagline = setting('site_tagline', '');

    ob_start();
    ?>
    <?php if ($posts): ?>
      <article>
        <div class="recent-posts section">
          <h2 class="section-header">
            随笔<?= public_icon('pen') ?>
          </h2>
          <?= render_public_post_list($posts) ?>
        </div>
      </article>

      <?php if ($totalPages > 1): ?>
        <ul class="pagination">
          <li class="page-item page-previous">
            <?php if ($page > 1): ?>
              <a href="<?= h(home_page_url($page - 1)) ?>">上一页</a>
            <?php endif; ?>
          </li>
          <li class="page-item page-next">
            <?php if ($page < $totalPages): ?>
              <a href="<?= h(home_page_url($page + 1)) ?>">下一页</a>
            <?php endif; ?>
          </li>
        </ul>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-notice">
        <p>还没有已发布的文章。</p>
        <?php if (is_admin()): ?>
          <p><a href="<?= h(url_for('write')) ?>">写第一篇文章</a></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php
    $content = (string)ob_get_clean();

    render_layout($siteName, $content, [
        'active' => 'home',
        'mode' => 'public',
        'description' => setting('site_description', $tagline),
    ]);
}

function render_archives(): void
{
    $groups = archive_groups();

    ob_start();
    ?>
    <h1 class="post-title" itemprop="name headline">归档</h1>
    <?php if ($groups): ?>
      <div class="post-content" itemprop="articleBody">
        <ul>
          <?php foreach ($groups as $label => $posts): ?>
            <li class="archives-item">
              <div class="archives-item-content">
                <h3 class="archives-item-title"><?= h((string)$label) ?></h3>
                <?php foreach ($posts as $post): ?>
                  <p>
                    <span class="archives-time"><?= h(date('m-d', (int)$post['published_at'])) ?></span>
                    <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?= h((string)$post['title']) ?></a>
                  </p>
                <?php endforeach; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php else: ?>
      <div class="empty-notice">
        <p>归档还是空的。</p>
      </div>
    <?php endif; ?>
    <?php
    $content = (string)ob_get_clean();

    render_layout('归档', $content, [
        'active' => 'archives',
        'mode' => 'public',
        'description' => '已发布文章归档',
    ]);
}

function render_post_page(array $post): void
{
    increment_content_views($post);

    $neighbors = post_neighbors($post);
    $state = post_state($post);
    $author = setting('author_name', 'Admin');
    $displayTime = (int)($post['published_at'] ?: $post['updated_at'] ?: $post['created_at']);
    $tagsMarkup = render_tag_chips($post);

    ob_start();
    ?>
    <article>
      <h1 class="post-title" itemprop="name headline"><?= h($post['title']) ?></h1>
      <div class="meta">
        <span><?= h(date('F j, Y', $displayTime)) ?></span>
        <span>作者: <?= h($author) ?></span>
        <?php if (!is_live_post($post) && is_admin()): ?>
          <span><?= h($state['label']) ?>预览</span>
        <?php endif; ?>
      </div>
      <div class="post-content" itemprop="articleBody">
        <?= markdown_to_html((string)$post['content']) ?>
      </div>
      <?php if ($tagsMarkup !== ''): ?>
        <div class="post-tags">
          <nav class="nav tags">
            <?= $tagsMarkup ?>
          </nav>
        </div>
      <?php endif; ?>
    </article>

    <?php if ($neighbors['newer'] || $neighbors['older']): ?>
      <ul class="pagination">
        <li class="page-item page-previous">
          <?php if ($neighbors['newer']): ?>
            <a href="<?= h(url_for('post', ['slug' => (string)$neighbors['newer']['slug']])) ?>">上一篇</a>
          <?php endif; ?>
        </li>
        <li class="page-item page-next">
          <?php if ($neighbors['older']): ?>
            <a href="<?= h(url_for('post', ['slug' => (string)$neighbors['older']['slug']])) ?>">下一篇</a>
          <?php endif; ?>
        </li>
      </ul>
    <?php endif; ?>
    <?php
    $content = (string)ob_get_clean();

    render_layout((string)$post['title'], $content, [
        'active' => 'home',
        'mode' => 'public',
        'description' => trim((string)$post['excerpt']) !== '' ? (string)$post['excerpt'] : derive_excerpt((string)$post['content']),
    ]);
}

function render_page_view(array $page): void
{
    increment_content_views($page);

    $displayTime = (int)($page['published_at'] ?: $page['updated_at'] ?: $page['created_at']);

    ob_start();
    ?>
    <article>
      <h1 class="post-title" itemprop="name headline"><?= h($page['title']) ?></h1>
      <div class="meta">
        <span>独立页面</span>
        <span>更新于 <?= h(date('F j, Y', $displayTime)) ?></span>
        <?php if (!is_live_content($page) && is_admin()): ?>
          <?php $state = post_state($page); ?>
          <span><?= h($state['label']) ?>预览</span>
        <?php endif; ?>
      </div>
      <div class="post-content" itemprop="articleBody">
        <?= markdown_to_html((string)$page['content']) ?>
      </div>
    </article>
    <?php
    $content = (string)ob_get_clean();

    render_layout((string)$page['title'], $content, [
        'active' => 'page:' . (string)$page['slug'],
        'mode' => 'public',
        'description' => trim((string)$page['excerpt']) !== '' ? (string)$page['excerpt'] : derive_excerpt((string)$page['content']),
    ]);
}

function render_tags_index(): void
{
    $tags = tag_index_data();

    ob_start();
    ?>
    <h1 class="post-title" itemprop="name headline">标签</h1>

    <?php if ($tags): ?>
      <div class="post-content">
        <div class="tag-cloud">
          <?php foreach ($tags as $tag): ?>
            <a class="tag-chip tag-chip--count" href="<?= h(url_for('tag', ['slug' => $tag['slug']])) ?>">
              <span>#<?= h($tag['label']) ?></span>
              <strong><?= h((string)$tag['count']) ?></strong>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php else: ?>
      <div class="empty-notice">
        <p>还没有标签。</p>
      </div>
    <?php endif; ?>
    <?php
    $content = (string)ob_get_clean();

    render_layout('标签', $content, [
        'active' => 'tags',
        'mode' => 'public',
        'description' => '标签索引',
    ]);
}

function render_tag_page(string $slug): void
{
    $label = tag_label_by_slug($slug);
    $posts = fetch_posts_by_tag_slug($slug);

    if ($label === null && $posts === []) {
        simple_error_page('标签不存在', '没有找到这个标签下的文章。', 404);
    }

    $label = $label ?? $slug;

    ob_start();
    ?>
    <h1 class="post-title" itemprop="name headline">#<?= h($label) ?></h1>
    <div class="meta"><?= h((string)count($posts)) ?> 篇文章</div>

    <?php if ($posts): ?>
      <article>
        <div class="recent-posts section">
          <h2 class="section-header">文章</h2>
          <?= render_public_post_list($posts) ?>
        </div>
      </article>
    <?php else: ?>
      <div class="empty-notice">
        <p>这个标签下还没有文章。</p>
      </div>
    <?php endif; ?>
    <?php
    $content = (string)ob_get_clean();

    render_layout('#' . $label, $content, [
        'active' => 'tags',
        'mode' => 'public',
        'description' => '标签 ' . $label . ' 下的文章',
    ]);
}

function render_rss_feed(): void
{
    $siteName = setting('site_name', default_settings()['site_name']);
    $description = setting('site_description', setting('site_tagline', ''));
    $home = absolute_url(url_for('home'));
    $items = fetch_feed_posts(20);

    header('Content-Type: application/rss+xml; charset=UTF-8');
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    ?>
<rss version="2.0">
  <channel>
    <title><?= x($siteName) ?></title>
    <link><?= x($home) ?></link>
    <description><?= x($description) ?></description>
    <language>zh-cn</language>
    <lastBuildDate><?= x(date(DATE_RSS)) ?></lastBuildDate>
    <?php foreach ($items as $item): ?>
      <?php $link = absolute_url(content_permalink($item)); ?>
      <item>
        <title><?= x($item['title']) ?></title>
        <link><?= x($link) ?></link>
        <guid><?= x($link) ?></guid>
        <pubDate><?= x(date(DATE_RSS, (int)$item['published_at'])) ?></pubDate>
        <description><?= x(trim((string)$item['excerpt']) !== '' ? (string)$item['excerpt'] : derive_excerpt((string)$item['content'])) ?></description>
      </item>
    <?php endforeach; ?>
  </channel>
</rss>
<?php
    exit;
}

function render_login_page(string $error = '', array $form = []): void
{
    ob_start();
    ?>
    <div class="auth-layout">
      <section class="hero hero--compact auth-intro admin-animate admin-animate--1">
        <p class="hero__eyebrow">Admin</p>
        <h1 class="hero__title">登录后台</h1>
        <p class="hero__lead">保留最小管理面，专注写作、编辑、发布和站点设置。</p>
        <ul class="auth-points">
          <li>登录后可管理文章、独立页面和站点信息。</li>
          <li>支持草稿、定时发布和伪静态设置。</li>
        </ul>
      </section>

      <section class="panel auth-panel admin-animate admin-animate--2">
        <div class="panel__header">
          <h2>账号登录</h2>
          <p class="panel__meta">使用安装时创建的管理员账号。</p>
        </div>
        <div class="panel__body">
          <?php if ($error !== ''): ?>
            <div class="flash flash--error"><?= h($error) ?></div>
          <?php endif; ?>

          <form class="form-stack" method="post" action="<?= h(url_for('login')) ?>">
            <?= csrf_field() ?>
            <div class="field">
              <label for="username">用户名</label>
              <input id="username" name="username" type="text" value="<?= h((string)($form['username'] ?? '')) ?>" autocomplete="username" required>
            </div>
            <div class="field">
              <label for="password">密码</label>
              <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <div class="action-row action-row--start">
              <button class="button" type="submit">登录</button>
            </div>
          </form>
        </div>
      </section>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout('登录', $content, [
        'active' => 'login',
        'description' => '博客后台登录',
    ]);
}

function render_admin_page(): void
{
    require_admin();

    $metrics = admin_metrics();
    $sidebar = render_admin_sidebar('admin');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar('博客数据预览') ?>

        <div class="admin-grid">
          <section class="panel admin-list-panel admin-animate admin-animate--3">
            <div class="panel__header">
              <h2>博客概览</h2>
              <p class="panel__meta">只显示访问和内容统计数据。</p>
            </div>
            <div class="panel__body">
              <div class="metric-grid">
                <div class="metric-card">
                  <span class="metric-card__label">总浏览量</span>
                  <strong class="metric-card__value"><?= h((string)$metrics['total_views']) ?></strong>
                  <span class="metric-card__trend">公开文章与页面累计</span>
                </div>
                <div class="metric-card">
                  <span class="metric-card__label">已发布文章</span>
                  <strong class="metric-card__value"><?= h((string)$metrics['published']) ?></strong>
                  <span class="metric-card__trend">前台可访问内容</span>
                </div>
                <div class="metric-card">
                  <span class="metric-card__label">分类数</span>
                  <strong class="metric-card__value"><?= h((string)$metrics['categories']) ?></strong>
                  <span class="metric-card__trend">文章分类总数</span>
                </div>
                <div class="metric-card">
                  <span class="metric-card__label">平均浏览</span>
                  <strong class="metric-card__value"><?= h((string)$metrics['avg_views']) ?></strong>
                  <span class="metric-card__trend">按文章数粗略计算</span>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout('后台概览', $content, [
        'active' => 'admin',
        'wide' => true,
        'description' => '博客后台概览',
    ]);
}

function render_admin_posts_page(): void
{
    require_admin();

    $posts = fetch_admin_posts();
    $sidebar = render_admin_sidebar('posts');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar('文章管理') ?>

        <section class="panel admin-list-panel admin-animate admin-animate--2">
          <div class="panel__header">
            <div class="admin-head">
              <div class="admin-head-left">
                <h2>文章管理</h2>
                <p class="panel__meta">管理文章、独立页面、分类、状态和浏览量。</p>
              </div>
            </div>
          </div>
          <div class="panel__body panel__body--flush">
            <?php if ($posts): ?>
              <div class="table-wrap">
                <table class="admin-table">
                  <thead>
                  <tr>
                    <th>类型</th>
                    <th>标题</th>
                    <th>状态</th>
                    <th>更新时间</th>
                    <th>操作</th>
                  </tr>
                  </thead>
                  <tbody>
                  <?php foreach ($posts as $post): ?>
                    <?php $state = post_state($post); ?>
                    <tr>
                      <td><span class="content-kind content-kind--<?= h(content_kind($post)) ?>"><?= h(content_type_label($post)) ?></span></td>
                      <td>
                        <div class="table-title">
                          <strong><a href="<?= h(url_for('edit', ['id' => $post['id']])) ?>"><?= h($post['title']) ?></a></strong>
                        </div>
                      </td>
                      <td><span class="status-badge status-badge--<?= h($state['class']) ?>"><?= h($state['label']) ?></span></td>
                      <td><time datetime="<?= h(date(DATE_ATOM, (int)$post['updated_at'])) ?>"><?= h(pretty_date((int)$post['updated_at'], true)) ?></time></td>
                      <td>
                        <div class="table-actions">
                          <a class="button button--ghost" href="<?= h(content_permalink($post)) ?>">查看</a>
                          <a class="button button--ghost" href="<?= h(url_for('edit', ['id' => $post['id']])) ?>">编辑</a>
                          <form method="post" action="<?= h(url_for('change_status')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= h($post['id']) ?>">
                            <input type="hidden" name="status" value="<?= h((string)$post['status'] === 'published' ? 'draft' : 'published') ?>">
                            <button class="button button--ghost" type="submit"><?= (string)$post['status'] === 'published' ? '转草稿' : '发布' ?></button>
                          </form>
                          <form method="post" action="<?= h(url_for('delete_post')) ?>" onsubmit="return confirm('确定删除这篇文章吗？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= h($post['id']) ?>">
                            <button class="button button--danger" type="submit">删除</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="empty-state empty-state--inside">
                <p>还没有文章。</p>
                <a class="button" href="<?= h(url_for('write')) ?>">开始写作</a>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout('文章管理', $content, [
        'active' => 'posts',
        'wide' => true,
        'description' => '博客文章管理',
    ]);
}

function render_admin_categories_page(array $form = [], array $errors = []): void
{
    require_admin();

    $categories = fetch_categories();
    $editing = null;
    $editId = (int)($_GET['id'] ?? $form['id'] ?? 0);
    if ($editId > 0) {
        $editing = one('SELECT * FROM categories WHERE id = ?', [$editId]);
    }

    $values = array_merge([
        'id' => (string)($editing['id'] ?? ''),
        'name' => (string)($editing['name'] ?? ''),
        'slug' => (string)($editing['slug'] ?? ''),
        'description' => (string)($editing['description'] ?? ''),
        'sort_order' => (string)($editing['sort_order'] ?? '0'),
    ], $form);
    $sidebar = render_admin_sidebar('categories');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar('分类管理') ?>

        <div class="admin-grid admin-grid--split">
          <section class="panel admin-list-panel admin-animate admin-animate--2">
            <div class="panel__header">
              <h2>分类列表</h2>
              <p class="panel__meta">分类用于组织文章，不影响独立页面。</p>
            </div>
            <div class="panel__body panel__body--flush">
              <?php if ($categories): ?>
                <div class="table-wrap">
                  <table class="admin-table">
                    <thead>
                    <tr>
                      <th>分类</th>
                      <th>Slug</th>
                      <th>文章数</th>
                      <th>排序</th>
                      <th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $category): ?>
                      <tr>
                        <td>
                          <div class="table-title">
                            <strong><?= h((string)$category['name']) ?></strong>
                            <span><?= h((string)$category['description']) ?></span>
                          </div>
                        </td>
                        <td><?= h((string)$category['slug']) ?></td>
                        <td><?= h((string)$category['post_count']) ?></td>
                        <td><?= h((string)$category['sort_order']) ?></td>
                        <td>
                          <div class="table-actions">
                            <a class="button button--ghost" href="<?= h(url_with_query(url_for('admin_categories'), ['id' => (int)$category['id']])) ?>">编辑</a>
                            <form method="post" action="<?= h(url_for('delete_category')) ?>" onsubmit="return confirm('确定删除这个分类吗？分类下文章会变为未分类。');">
                              <?= csrf_field() ?>
                              <input type="hidden" name="id" value="<?= h($category['id']) ?>">
                              <button class="button button--danger" type="submit">删除</button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <div class="empty-state empty-state--inside">
                  <p>还没有分类。</p>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <section class="panel admin-list-panel admin-animate admin-animate--3">
            <div class="panel__header">
              <h2><?= $editing ? '编辑分类' : '新建分类' ?></h2>
              <p class="panel__meta">名称、URL 标识和排序。</p>
            </div>
            <div class="panel__body">
              <?php if ($errors): ?>
                <div class="flash flash--error"><?= h(implode(' ', $errors)) ?></div>
              <?php endif; ?>

              <form class="form-stack" method="post" action="<?= h(url_for('save_category')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= h((string)$values['id']) ?>">
                <div class="field">
                  <label for="category_name">分类名称</label>
                  <input id="category_name" name="name" type="text" value="<?= h((string)$values['name']) ?>" required>
                </div>
                <div class="field-grid">
                  <div class="field">
                    <label for="category_slug">Slug</label>
                    <input id="category_slug" name="slug" type="text" value="<?= h((string)$values['slug']) ?>" placeholder="留空自动生成">
                  </div>
                  <div class="field">
                    <label for="category_sort">排序权重</label>
                    <input id="category_sort" name="sort_order" type="number" value="<?= h((string)$values['sort_order']) ?>">
                  </div>
                </div>
                <div class="field">
                  <label for="category_description">分类描述</label>
                  <textarea id="category_description" name="description" rows="4"><?= h((string)$values['description']) ?></textarea>
                </div>
                <div class="action-row">
                  <?php if ($editing): ?>
                    <a class="button button--secondary" href="<?= h(url_for('admin_categories')) ?>">取消编辑</a>
                  <?php endif; ?>
                  <button class="button" type="submit"><?= $editing ? '保存分类' : '创建分类' ?></button>
                </div>
              </form>
            </div>
          </section>
        </div>
      </div>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout('分类管理', $content, [
        'active' => 'categories',
        'wide' => true,
        'description' => '博客分类管理',
    ]);
}

function render_admin_settings_page(): void
{
    require_admin();

    $siteName = setting('site_name', default_settings()['site_name']);
    $sidebar = render_admin_sidebar('settings');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar('站点设置') ?>

        <section class="panel admin-list-panel admin-animate admin-animate--2">
          <div class="panel__header">
            <h2>站点设置</h2>
            <p class="panel__meta">名称、地址、首页展示与伪静态配置。</p>
          </div>
          <div class="panel__body">
            <form class="form-stack" method="post" action="<?= h(url_for('save_settings')) ?>">
              <?= csrf_field() ?>
              <div class="field-grid">
                <div class="field">
                  <label for="site_name">站点名称</label>
                  <input id="site_name" name="site_name" type="text" value="<?= h(setting('site_name')) ?>" required>
                </div>
                <div class="field">
                  <label for="author_name">作者名</label>
                  <input id="author_name" name="author_name" type="text" value="<?= h(setting('author_name')) ?>" required>
                </div>
              </div>
              <div class="field">
                <label for="site_url">站点地址</label>
                <input id="site_url" name="site_url" type="url" value="<?= h(setting('site_url')) ?>" placeholder="https://example.com/blog">
                <p class="field-hint">RSS 会优先使用这里的绝对地址，子目录部署时请带上完整路径。</p>
              </div>
              <div class="field">
                <label>顶部 Logo</label>
                <div class="settings-logo">
                  <img class="settings-logo__preview" src="<?= h(theme_logo_url()) ?>" width="56" height="56" alt="<?= h($siteName) ?>">
                  <div class="settings-logo__copy">
                    <strong>logo.png</strong>
                    <p class="field-hint">后台和前台顶部统一读取项目根目录下的本地文件 `logo.png`。</p>
                  </div>
                </div>
              </div>
              <div class="field">
                <label for="footer_beian">备案号</label>
                <input id="footer_beian" name="footer_beian" type="text" value="<?= h(setting('footer_beian')) ?>" placeholder="京 ICP 备 12345678 号">
              </div>
              <div class="field-grid">
                <div class="field">
                  <label for="posts_per_page">首页每页文章数</label>
                  <input id="posts_per_page" name="posts_per_page" type="number" min="1" max="24" value="<?= h(setting('posts_per_page', '6')) ?>">
                </div>
                <div class="field">
                  <label for="pretty_url">伪静态 URL</label>
                  <select id="pretty_url" name="pretty_url">
                    <option value="0"<?= setting('pretty_url', '0') === '0' ? ' selected' : '' ?>>关闭</option>
                    <option value="1"<?= setting('pretty_url', '0') === '1' ? ' selected' : '' ?>>开启</option>
                  </select>
                  <p class="field-hint">开启后链接会变成 `/post/slug` 这类路径，需要服务器 rewrite 支持。</p>
                </div>
              </div>
              <div class="field">
                <label for="site_tagline">首页副标题</label>
                <input id="site_tagline" name="site_tagline" type="text" value="<?= h(setting('site_tagline')) ?>">
              </div>
              <div class="field">
                <label for="site_description">站点描述</label>
                <textarea id="site_description" name="site_description" rows="3"><?= h(setting('site_description')) ?></textarea>
              </div>
              <div class="field">
                <label for="home_intro">头部一句话</label>
                <textarea id="home_intro" name="home_intro" rows="4"><?= h(setting('home_intro')) ?></textarea>
              </div>
              <div class="field">
                <label for="site_footer">页脚文案</label>
                <input id="site_footer" name="site_footer" type="text" value="<?= h(setting('site_footer')) ?>" placeholder="支持 {year} 占位符">
              </div>
              <div class="action-row">
                <button class="button" type="submit">保存设置</button>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout('站点设置', $content, [
        'active' => 'settings',
        'wide' => true,
        'description' => '博客站点设置',
    ]);
}

function render_editor_page(?array $existing = null, array $form = [], array $errors = []): void
{
    require_admin();

    $defaults = [
        'kind' => (string)($existing['kind'] ?? 'post'),
        'category_id' => (string)($existing['category_id'] ?? ''),
        'title' => (string)($existing['title'] ?? ''),
        'slug' => (string)($existing['slug'] ?? ''),
        'tags_input' => implode(', ', post_tags($existing ?? [])),
        'excerpt' => (string)($existing['excerpt'] ?? ''),
        'content' => (string)($existing['content'] ?? ''),
        'status' => (string)($existing['status'] ?? 'draft'),
        'published_at' => $existing ? datetime_local_value((int)($existing['published_at'] ?: time())) : datetime_local_value(time()),
    ];

    $values = array_merge($defaults, $form);
    $isEdit = $existing !== null;
    $siteName = setting('site_name', default_settings()['site_name']);
    $categories = category_options();
    $sidebar = render_admin_sidebar($isEdit ? 'edit' : 'write', [
        'title' => '写作提示',
        'items' => [
            'Slug 留空会按标题自动生成。',
            '发布时间晚于当前时间会按定时发布处理。',
            '独立页面可以不填标签。',
        ],
    ]);

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar($isEdit ? '编辑内容' : '撰写文章') ?>

        <section class="panel admin-masthead admin-masthead--compact admin-animate admin-animate--2">
          <div class="panel__body admin-masthead__body">
            <div class="admin-masthead__intro">
              <img class="admin-masthead__logo" src="<?= h(theme_logo_url()) ?>" width="72" height="72" alt="<?= h($siteName) ?>">
              <div class="admin-masthead__copy">
                <p class="admin-masthead__eyebrow"><?= $isEdit ? 'Edit' : 'Write' ?></p>
                <h1 class="admin-masthead__title"><?= $isEdit ? '编辑内容' : '撰写文章' ?></h1>
                <p class="admin-masthead__lead">支持基础 Markdown，可创建文章或独立页面。</p>
              </div>
            </div>
            <div class="admin-masthead__actions">
              <a class="button button--secondary" href="<?= h(url_for('admin')) ?>">返回后台</a>
            </div>
          </div>
        </section>

        <section class="panel editor-panel admin-animate admin-animate--3">
          <div class="panel__body">
            <?php if ($errors): ?>
              <div class="flash flash--error">
                <?= h(implode(' ', $errors)) ?>
              </div>
            <?php endif; ?>

            <form class="form-stack" method="post" action="<?= h($isEdit ? url_for('edit', ['id' => $existing['id']]) : url_for('write')) ?>">
              <?= csrf_field() ?>
              <div class="field">
                <label for="title">标题</label>
                <input id="title" name="title" type="text" value="<?= h((string)$values['title']) ?>" required>
              </div>

              <div class="field-grid field-grid--quad">
                <div class="field">
                  <label for="kind">内容类型</label>
                  <select id="kind" name="kind">
                    <option value="post"<?= (string)$values['kind'] === 'post' ? ' selected' : '' ?>>文章</option>
                    <option value="page"<?= (string)$values['kind'] === 'page' ? ' selected' : '' ?>>独立页面</option>
                  </select>
                </div>
                <div class="field">
                  <label for="slug">Slug</label>
                  <input id="slug" name="slug" type="text" value="<?= h((string)$values['slug']) ?>" placeholder="留空将自动生成">
                </div>
                <div class="field">
                  <label for="category_id">分类</label>
                  <select id="category_id" name="category_id">
                    <option value="">未分类</option>
                    <?php foreach ($categories as $category): ?>
                      <option value="<?= h($category['id']) ?>"<?= (string)$values['category_id'] === (string)$category['id'] ? ' selected' : '' ?>><?= h($category['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="field">
                  <label for="published_at">发布时间</label>
                  <input id="published_at" name="published_at" type="datetime-local" value="<?= h((string)$values['published_at']) ?>">
                </div>
              </div>

              <div class="field">
                <label for="tags_input">标签</label>
                <input id="tags_input" name="tags_input" type="text" value="<?= h((string)$values['tags_input']) ?>" placeholder="用逗号分隔，例如 PHP, SQLite, 随笔">
                <p class="field-hint">独立页面可以留空，文章会用这些标签生成聚合页。</p>
              </div>

              <div class="field">
                <label for="excerpt">摘要</label>
                <textarea id="excerpt" name="excerpt" rows="3" placeholder="留空将自动从正文截取"><?= h((string)$values['excerpt']) ?></textarea>
              </div>

              <div class="field">
                <label for="status">状态</label>
                <select id="status" name="status">
                  <option value="draft"<?= (string)$values['status'] === 'draft' ? ' selected' : '' ?>>草稿</option>
                  <option value="published"<?= (string)$values['status'] === 'published' ? ' selected' : '' ?>>发布</option>
                </select>
                <p class="field-hint">如果发布时间晚于当前时间，前台会按定时发布处理。</p>
              </div>

              <div class="field">
                <label for="content">正文</label>
                <textarea id="content" class="editor-textarea" name="content" rows="18" required><?= h((string)$values['content']) ?></textarea>
              </div>

              <div class="field">
                <label for="attachmentInput">上传附件</label>
                <div class="attachment-uploader" data-upload-url="<?= h(url_for('upload_attachment')) ?>" data-csrf="<?= h(csrf_token()) ?>">
                  <input id="attachmentInput" class="attachment-input" type="file" name="attachments[]" multiple>
                  <label class="attachment-drop" for="attachmentInput">
                    <span class="attachment-drop__title">选择或拖入附件</span>
                    <span class="attachment-drop__hint">可同时上传多个附件，每个最大 30M；图片上传完成后显示缩略图并插入 Markdown。</span>
                  </label>
                  <div class="attachment-list" aria-live="polite"></div>
                </div>
              </div>

              <div class="action-row">
                <button class="button" type="submit"><?= $isEdit ? '保存修改' : '创建文章' ?></button>
              </div>
            </form>
          </div>
        </section>
      </div>
    </div>
    <?php
    $content = (string)ob_get_clean();

    render_layout($isEdit ? '编辑文章' : '写新文章', $content, [
        'active' => $isEdit ? 'edit' : 'write',
        'wide' => true,
        'description' => '博客文章编辑器',
    ]);
}

if (!is_file(INSTALL_LOCK_FILE)) {
    redirect_to(install_url());
}

apply_pretty_route();

if (($_GET['__route_not_found'] ?? '') === '1') {
    simple_error_page('页面不存在', '你访问的地址没有匹配到任何页面。', 404);
}

$action = (string)($_GET['a'] ?? 'home');

switch ($action) {
    case 'home':
        render_home((int)($_GET['p'] ?? 1));
        break;

    case 'rss':
        render_rss_feed();
        break;

    case 'archives':
        render_archives();
        break;

    case 'tags':
        render_tags_index();
        break;

    case 'tag':
        render_tag_page(trim((string)($_GET['slug'] ?? '')));
        break;

    case 'post':
        $slug = trim((string)($_GET['slug'] ?? $_GET['id'] ?? ''));
        $post = fetch_post_by_identifier($slug, is_admin());
        if (!$post) {
            simple_error_page('文章不存在', '可能还未发布，或者链接已经失效。', 404);
        }
        render_post_page($post);
        break;

    case 'page':
        $slug = trim((string)($_GET['slug'] ?? $_GET['id'] ?? ''));
        $page = fetch_page_by_identifier($slug, is_admin());
        if (!$page) {
            simple_error_page('页面不存在', '可能还未发布，或者链接已经失效。', 404);
        }
        render_page_view($page);
        break;

    case 'login':
        if (is_admin()) {
            redirect_to(url_for('admin'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $user = one('SELECT * FROM users WHERE username = ?', [$username]);

            if (!$user || !password_verify($password, (string)$user['password_hash'])) {
                render_login_page('用户名或密码不正确。', ['username' => $username]);
            }

            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$user['id'];
            set_flash('success', '已登录后台。');
            redirect_to(url_for('admin'));
        }

        render_login_page();
        break;

    case 'logout':
        unset($_SESSION['admin_id']);
        set_flash('success', '已退出后台。');
        redirect_to(url_for('home'));
        break;

    case 'admin':
        render_admin_page();
        break;

    case 'admin_posts':
        render_admin_posts_page();
        break;

    case 'admin_categories':
        render_admin_categories_page();
        break;

    case 'admin_settings':
        render_admin_settings_page();
        break;

    case 'save_settings':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect_to(url_for('admin_settings'));
        }
        verify_csrf();
        $siteName = trim((string)($_POST['site_name'] ?? ''));
        $authorName = trim((string)($_POST['author_name'] ?? ''));
        $postsPerPage = max(1, min(24, (int)($_POST['posts_per_page'] ?? (int)default_settings()['posts_per_page'])));
        $prettyUrl = (string)($_POST['pretty_url'] ?? '0') === '1' ? '1' : '0';
        save_settings([
            'site_name' => $siteName !== '' ? $siteName : default_settings()['site_name'],
            'author_name' => $authorName !== '' ? $authorName : default_settings()['author_name'],
            'site_url' => trim((string)($_POST['site_url'] ?? '')),
            'logo_url' => default_settings()['logo_url'],
            'footer_beian' => trim((string)($_POST['footer_beian'] ?? '')),
            'posts_per_page' => (string)$postsPerPage,
            'pretty_url' => $prettyUrl,
            'site_tagline' => trim((string)($_POST['site_tagline'] ?? '')),
            'site_description' => trim((string)($_POST['site_description'] ?? '')),
            'home_intro' => trim((string)($_POST['home_intro'] ?? '')),
            'site_footer' => trim((string)($_POST['site_footer'] ?? '')),
        ]);
        set_flash('success', '站点设置已更新。');
        redirect_to(url_for('admin_settings'));
        break;

    case 'save_category':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect_to(url_for('admin_categories'));
        }
        verify_csrf();
        $id = (int)($_POST['id'] ?? 0);
        $existing = $id > 0 ? one('SELECT * FROM categories WHERE id = ?', [$id]) : null;
        [$data, $errors] = validate_category_input($_POST, $existing);
        if ($errors) {
            render_admin_categories_page([
                'id' => (string)$id,
                'name' => (string)($_POST['name'] ?? ''),
                'slug' => (string)($_POST['slug'] ?? ''),
                'description' => (string)($_POST['description'] ?? ''),
                'sort_order' => (string)($_POST['sort_order'] ?? '0'),
            ], $errors);
        }
        if ($existing) {
            q(
                'UPDATE categories SET name = ?, slug = ?, description = ?, sort_order = ?, updated_at = ? WHERE id = ?',
                [$data['name'], $data['slug'], $data['description'], $data['sort_order'], time(), $id]
            );
            set_flash('success', '分类已保存。');
        } else {
            $now = time();
            q(
                'INSERT INTO categories(name, slug, description, sort_order, created_at, updated_at) VALUES(?,?,?,?,?,?)',
                [$data['name'], $data['slug'], $data['description'], $data['sort_order'], $now, $now]
            );
            set_flash('success', '分类已创建。');
        }
        redirect_to(url_for('admin_categories'));
        break;

    case 'delete_category':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect_to(url_for('admin_categories'));
        }
        verify_csrf();
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            q('UPDATE posts SET category_id = NULL WHERE category_id = ?', [$id]);
            q('DELETE FROM categories WHERE id = ?', [$id]);
        }
        set_flash('success', '分类已删除。');
        redirect_to(url_for('admin_categories'));
        break;

    case 'upload_attachment':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            json_response(['ok' => false, 'error' => '仅支持 POST 上传。'], 405);
        }
        handle_attachment_upload();
        break;

    case 'write':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            [$data, $errors] = validate_post_input($_POST);
            if (!$errors) {
                $now = time();
                q(
                    'INSERT INTO posts(kind, category_id, slug, title, tags, excerpt, content, status, published_at, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        $data['kind'],
                        $data['category_id'],
                        $data['slug'],
                        $data['title'],
                        $data['tags'],
                        $data['excerpt'],
                        $data['content'],
                        $data['status'],
                        $data['published_at'],
                        $now,
                        $now,
                    ]
                );
                $id = (int)db()->lastInsertId();
                set_flash('success', '文章已创建。');
                redirect_to(url_for('edit', ['id' => $id]));
            }
            render_editor_page(null, [
                'kind' => (string)($_POST['kind'] ?? 'post'),
                'category_id' => (string)($_POST['category_id'] ?? ''),
                'title' => (string)($_POST['title'] ?? ''),
                'slug' => (string)($_POST['slug'] ?? ''),
                'tags_input' => (string)($_POST['tags_input'] ?? ''),
                'excerpt' => (string)($_POST['excerpt'] ?? ''),
                'content' => (string)($_POST['content'] ?? ''),
                'status' => (string)($_POST['status'] ?? 'draft'),
                'published_at' => (string)($_POST['published_at'] ?? ''),
            ], $errors);
        }
        render_editor_page();
        break;

    case 'edit':
        require_admin();
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        $post = fetch_post_by_id($id);
        if (!$post) {
            simple_error_page('文章不存在', '找不到需要编辑的文章。', 404);
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            [$data, $errors] = validate_post_input($_POST, $post);
            if (!$errors) {
                q(
                    'UPDATE posts SET kind = ?, category_id = ?, slug = ?, title = ?, tags = ?, excerpt = ?, content = ?, status = ?, published_at = ?, updated_at = ? WHERE id = ?',
                    [
                        $data['kind'],
                        $data['category_id'],
                        $data['slug'],
                        $data['title'],
                        $data['tags'],
                        $data['excerpt'],
                        $data['content'],
                        $data['status'],
                        $data['published_at'],
                        time(),
                        $id,
                    ]
                );
                set_flash('success', '文章已保存。');
                redirect_to(url_for('edit', ['id' => $id]));
            }
            render_editor_page($post, [
                'kind' => (string)($_POST['kind'] ?? 'post'),
                'category_id' => (string)($_POST['category_id'] ?? ''),
                'title' => (string)($_POST['title'] ?? ''),
                'slug' => (string)($_POST['slug'] ?? ''),
                'tags_input' => (string)($_POST['tags_input'] ?? ''),
                'excerpt' => (string)($_POST['excerpt'] ?? ''),
                'content' => (string)($_POST['content'] ?? ''),
                'status' => (string)($_POST['status'] ?? 'draft'),
                'published_at' => (string)($_POST['published_at'] ?? ''),
            ], $errors);
        }
        render_editor_page($post);
        break;

    case 'change_status':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect_to(url_for('admin_posts'));
        }
        verify_csrf();
        $id = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'draft');
        $post = fetch_post_by_id($id);
        if (!$post) {
            simple_error_page('文章不存在', '找不到需要变更状态的文章。', 404);
        }
        $target = $status === 'published' ? 'published' : 'draft';
        $publishedAt = (int)$post['published_at'];
        if ($target === 'published' && $publishedAt < 1) {
            $publishedAt = time();
        }
        q('UPDATE posts SET status = ?, published_at = ?, updated_at = ? WHERE id = ?', [$target, $publishedAt, time(), $id]);
        set_flash('success', $target === 'published' ? '文章已发布。' : '文章已转为草稿。');
        redirect_to(url_for('admin_posts'));
        break;

    case 'delete_post':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect_to(url_for('admin_posts'));
        }
        verify_csrf();
        $id = (int)($_POST['id'] ?? 0);
        q('DELETE FROM posts WHERE id = ?', [$id]);
        set_flash('success', '文章已删除。');
        redirect_to(url_for('admin_posts'));
        break;

    default:
        simple_error_page('页面不存在', '当前操作未定义。', 404);
        break;
}
