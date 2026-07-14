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

const APP_VERSION = 'v1.1.4';
const DATA_DIR = __DIR__ . '/data';
const CACHE_DIR = __DIR__ . '/cache';
const UPLOAD_DIR = __DIR__ . '/uploads';
const DB_CONFIG_FILE = DATA_DIR . '/config.php';
const INSTALL_LOCK_FILE = DATA_DIR . '/install.lock';
const SETTINGS_CACHE_FILE = CACHE_DIR . '/settings.php';
const UPDATE_REPOSITORY = 'jkjoy/Simple-PHP-Blog';
const UPDATE_CACHE_FILE = CACHE_DIR . '/github-update.json';

function db_file_path(): string
{
    if (is_file(DB_CONFIG_FILE)) {
        $config = include DB_CONFIG_FILE;
        $name = is_array($config) ? basename((string)($config['db_file'] ?? '')) : '';
        if ($name !== '' && $name !== 'blog.sqlite' && preg_match('/^blog-[a-f0-9]{16}\.sqlite$/', $name)) {
            return DATA_DIR . '/' . $name;
        }
    }

    return '';
}

define('DB_FILE', db_file_path());

function is_installed(): bool
{
    return is_file(INSTALL_LOCK_FILE) && DB_FILE !== '' && is_file(DB_FILE);
}

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

function ensure_comment_columns(PDO $pdo): void
{
    $columns = table_columns($pdo, 'comments');
    if (isset($columns['parent_id'], $columns['reply_to_name'], $columns['user_id'])) {
        return;
    }

    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->exec('BEGIN IMMEDIATE');
    }

    try {
        $columns = table_columns($pdo, 'comments');
        if (!isset($columns['parent_id'])) { $pdo->exec('ALTER TABLE comments ADD COLUMN parent_id INTEGER REFERENCES comments(id) ON DELETE SET NULL'); }
        if (!isset($columns['reply_to_name'])) { $pdo->exec("ALTER TABLE comments ADD COLUMN reply_to_name TEXT NOT NULL DEFAULT ''"); }
        if (!isset($columns['user_id'])) { $pdo->exec('ALTER TABLE comments ADD COLUMN user_id INTEGER REFERENCES users(id) ON DELETE SET NULL'); }
        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function ensure_schema(PDO $pdo): void
{
    static $done = false;

    if ($done) {
        return;
    }

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
            nickname TEXT NOT NULL DEFAULT '',
            email TEXT NOT NULL DEFAULT '',
            avatar_url TEXT NOT NULL DEFAULT '',
            website_url TEXT NOT NULL DEFAULT '',
            social_links TEXT NOT NULL DEFAULT '',
            signature TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_resets(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token_hash TEXT NOT NULL UNIQUE,
            expires_at INTEGER NOT NULL,
            used_at INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS posts(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            author_id INTEGER,
            category_id INTEGER,
            slug TEXT NOT NULL UNIQUE,
            title TEXT NOT NULL,
            excerpt TEXT NOT NULL DEFAULT '',
            content TEXT NOT NULL,
            kind TEXT NOT NULL DEFAULT 'post',
            tags TEXT NOT NULL DEFAULT '[]',
            views INTEGER NOT NULL DEFAULT 0,
            is_pinned INTEGER NOT NULL DEFAULT 0,
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
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS links(
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            url TEXT NOT NULL,
            icon_url TEXT NOT NULL DEFAULT '',
            description TEXT NOT NULL DEFAULT '',
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS tag_meta(
            label TEXT NOT NULL UNIQUE,
            slug TEXT NOT NULL UNIQUE,
            updated_at INTEGER NOT NULL
        )"
    );
    $pdo->exec(
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
            user_agent TEXT NOT NULL DEFAULT '',
            created_at INTEGER NOT NULL,
            updated_at INTEGER NOT NULL,
            FOREIGN KEY(post_id) REFERENCES posts(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY(parent_id) REFERENCES comments(id) ON DELETE SET NULL
        )"
    );

    $columns = table_columns($pdo, 'posts');
    $userColumns = table_columns($pdo, 'users');

    foreach (['nickname', 'email', 'avatar_url', 'website_url', 'social_links', 'signature'] as $column) {
        if (!isset($userColumns[$column])) { $pdo->exec("ALTER TABLE users ADD COLUMN {$column} TEXT NOT NULL DEFAULT ''"); }
    }

    $linkColumns = table_columns($pdo, 'links');
    if (!isset($linkColumns['icon_url'])) { $pdo->exec("ALTER TABLE links ADD COLUMN icon_url TEXT NOT NULL DEFAULT ''"); }

    ensure_comment_columns($pdo);

    if (!isset($columns['author_id'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN author_id INTEGER");
    }

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
    if (!isset($columns['is_pinned'])) {
        $pdo->exec("ALTER TABLE posts ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0");
    }

    $pdo->exec("UPDATE posts SET kind = 'post' WHERE kind IS NULL OR trim(kind) = ''");
    $pdo->exec("UPDATE posts SET tags = '[]' WHERE tags IS NULL OR trim(tags) = ''");
    $pdo->exec("UPDATE posts SET views = 0 WHERE views IS NULL");
    $pdo->exec("UPDATE posts SET is_pinned = 0 WHERE is_pinned IS NULL");
    $defaultAuthorId = (int)($pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
    if ($defaultAuthorId > 0) {
        $pdo->prepare('UPDATE posts SET author_id = ? WHERE author_id IS NULL OR author_id NOT IN (SELECT id FROM users)')->execute([$defaultAuthorId]);
    }
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_public_pinned ON posts(kind, status, is_pinned DESC, published_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_kind_updated ON posts(kind, updated_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_posts_category ON posts(category_id, kind, status, published_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_categories_sort ON categories(sort_order ASC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_links_sort ON links(sort_order ASC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_post_public ON comments(post_id, status, created_at, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_moderation ON comments(status, created_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_unread ON comments(is_read, created_at DESC, id DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_ip_recent ON comments(ip_hash, created_at DESC)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_parent ON comments(parent_id, created_at, id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_comments_user_recent ON comments(user_id, created_at DESC)');
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_comments_visitor_email_approval ON comments(author_email COLLATE NOCASE, status) WHERE user_id IS NULL");

    $defaultCategoryId = (int)($pdo->query("SELECT id FROM categories WHERE slug = 'default' ORDER BY id LIMIT 1")->fetchColumn() ?: 0);
    if ($defaultCategoryId < 1) {
        $now = time();
        $statement = $pdo->prepare('INSERT INTO categories(name, slug, description, sort_order, created_at, updated_at) VALUES(?,?,?,?,?,?)');
        $statement->execute(['默认分类', 'default', '系统默认文章分类。', 0, $now, $now]);
        $defaultCategoryId = (int)$pdo->lastInsertId();
    }
    $statement = $pdo->prepare("UPDATE posts SET category_id = ? WHERE kind = 'post' AND (category_id IS NULL OR category_id NOT IN (SELECT id FROM categories))");
    $statement->execute([$defaultCategoryId]);
    $done = true;
}

function db(): PDO
{
    static $db;

    if ($db instanceof PDO) {
        ensure_schema($db);
        return $db;
    }

    if (DB_FILE === '') { throw new RuntimeException('博客尚未安装或数据库配置无效。'); }
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
        'site_url' => '',
        'site_tagline' => 'A small PHP blog running on one main entry file.',
        'site_description' => 'A simple PHP + SQLite blog inspired by Hugo Paper.',
        'site_keywords' => '',
        'ai_api_url' => 'https://api.deepseek.com',
        'ai_api_key' => '',
        'ai_model' => 'deepseek-v4-flash',
        'ai_slug_prompt' => 'Translate the title into a concise English URL slug. Output lowercase ASCII words separated only by hyphens. Output the slug only, without quotes or explanation.',
        'ai_summary_prompt' => '根据文章内容生成不超过100个汉字的中文摘要。只输出摘要正文，不要标题、引号、解释或 Markdown 标记。',
        'ai_polish_prompt' => '你是专业中文编辑。严格执行用户要求，保留有效 Markdown 结构。只输出处理后的完整正文，不要解释处理过程。',
        'site_footer' => '',
        'custom_head_code' => '',
        'favicon_url' => 'logo.png',
        'footer_beian' => '',
        'posts_per_page' => '6',
        'pretty_url' => '0',
        'comments_enabled' => '1',
        'comments_require_approval' => '1',
        'comments_notify' => '1',
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

function normalize_version(string $version): string
{
    return preg_replace('/[^0-9A-Za-z.+-]/', '', ltrim(trim($version), 'vV')) ?: '0';
}

function update_available_for(string $latest, string $current = APP_VERSION): bool
{
    $latest = normalize_version($latest);
    $current = normalize_version($current);
    return $latest !== $current && version_compare($latest, $current, '>');
}

function github_update_info(bool $refresh = false): array
{
    ensure_runtime_dirs();
    if (!$refresh && is_file(UPDATE_CACHE_FILE) && time() - (int)filemtime(UPDATE_CACHE_FILE) < 21600) {
        $cached = json_decode((string)file_get_contents(UPDATE_CACHE_FILE), true);
        if (is_array($cached)) {
            $cached['current'] = APP_VERSION;
            $cached['available'] = update_available_for((string)($cached['latest'] ?? ''));
            return $cached;
        }
    }
    $result = ['available' => false, 'current' => APP_VERSION, 'latest' => '', 'download_url' => '', 'error' => ''];
    if (!function_exists('curl_init')) {
        $result['error'] = '服务器未启用 cURL，无法检查更新。';
        return $result;
    }
    $curl = curl_init('https://api.github.com/repos/' . UPDATE_REPOSITORY . '/releases/latest');
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'Simple-PHP-Blog/' . APP_VERSION, CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json']]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    $release = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($release)) {
        $result['error'] = $curlError !== '' ? $curlError : 'GitHub 暂时无法访问。';
    } else {
        $latest = trim((string)($release['tag_name'] ?? ''));
        $download = (string)($release['zipball_url'] ?? '');
        if ($latest !== '' && filter_var($download, FILTER_VALIDATE_URL)) {
            $result['latest'] = $latest;
            $result['download_url'] = $download;
            $result['available'] = update_available_for($latest);
        }
    }
    file_put_contents(UPDATE_CACHE_FILE, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $result;
}

function install_github_update(array $update): string
{
    if (empty($update['available']) || !filter_var((string)($update['download_url'] ?? ''), FILTER_VALIDATE_URL)) { throw new RuntimeException('当前没有可安装的更新。'); }
    if (!class_exists('ZipArchive')) { throw new RuntimeException('服务器未启用 ZipArchive，无法解压更新包。'); }
    ensure_runtime_dirs();
    $workDir = CACHE_DIR . '/update-' . bin2hex(random_bytes(6));
    $zipFile = $workDir . '/release.zip';
    if (!mkdir($workDir, 0755, true) && !is_dir($workDir)) { throw new RuntimeException('无法创建更新临时目录。'); }
    try {
        $handle = fopen($zipFile, 'wb');
        if ($handle === false) { throw new RuntimeException('无法创建更新包。'); }
        $curl = curl_init((string)$update['download_url']);
        curl_setopt_array($curl, [CURLOPT_FILE => $handle, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 60, CURLOPT_USERAGENT => 'Simple-PHP-Blog/' . APP_VERSION]);
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        fclose($handle);
        if (!$ok || $status !== 200) { throw new RuntimeException('更新包下载失败：' . ($error ?: 'HTTP ' . $status)); }
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true || !$zip->extractTo($workDir . '/source')) { throw new RuntimeException('更新包无法解压。'); }
        $zip->close();
        $roots = glob($workDir . '/source/*', GLOB_ONLYDIR) ?: [];
        $source = (string)($roots[0] ?? '');
        $newIndex = $source . '/index.php';
        if (!is_file($newIndex)) { throw new RuntimeException('更新包结构无效。'); }
        $code = (string)file_get_contents($newIndex);
        if (!preg_match("/const APP_VERSION = '([^']+)'/", $code, $match) || !update_available_for((string)$match[1])) { throw new RuntimeException('更新包版本无效或不高于当前版本。'); }
        $files = ['index.php', 'index.css', 'index.js', 'install.php', 'update.php', 'README.md', 'logo.png', '.htaccess'];
        $backup = CACHE_DIR . '/update-backup-' . date('Ymd-His');
        mkdir($backup, 0755, true);
        $replaced = [];
        try {
            foreach ($files as $file) {
                $from = $source . '/' . $file;
                if (!is_file($from)) { continue; }
                $target = __DIR__ . '/' . $file;
                if (is_file($target) && !copy($target, $backup . '/' . str_replace('/', '_', $file))) { throw new RuntimeException('无法备份 ' . $file); }
                if (!copy($from, $target)) { throw new RuntimeException('无法覆盖 ' . $file); }
                $replaced[] = $file;
            }
        } catch (Throwable $exception) {
            foreach ($replaced as $file) { $saved = $backup . '/' . str_replace('/', '_', $file); if (is_file($saved)) { copy($saved, __DIR__ . '/' . $file); } }
            throw $exception;
        }
        @unlink(UPDATE_CACHE_FILE);
        return (string)$match[1];
    } finally {
        $items = is_dir($workDir) ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) : [];
        foreach ($items as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
        if (is_dir($workDir)) { @rmdir($workDir); }
    }
}

function login_rate_file(): string
{
    $client = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return CACHE_DIR . '/login-' . hash('sha256', $client) . '.json';
}

function login_rate_state(bool $recordFailure = false, bool $clear = false): array
{
    ensure_runtime_dirs();
    $file = login_rate_file();
    $handle = fopen($file, 'c+');
    if ($handle === false) { return ['count' => 0, 'since' => time()]; }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $state = json_decode($raw ?: '', true);
    $now = time();
    if (!is_array($state) || $now - (int)($state['since'] ?? 0) >= 900) {
        $state = ['count' => 0, 'since' => $now];
    }
    if ($recordFailure) { $state['count'] = (int)$state['count'] + 1; }
    if ($clear) { $state = ['count' => 0, 'since' => $now]; }
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $state;
}

function password_reset_rate_file(): string
{
    $client = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return CACHE_DIR . '/password-reset-rate-' . hash('sha256', $client) . '.json';
}

function password_reset_rate_state(bool $recordAttempt = false): array
{
    ensure_runtime_dirs();
    $file = password_reset_rate_file();
    $handle = fopen($file, 'c+');
    if ($handle === false) { return ['count' => 0, 'since' => time()]; }

    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $state = json_decode($raw ?: '', true);
    $now = time();
    if (!is_array($state) || $now - (int)($state['since'] ?? 0) >= 900) {
        $state = ['count' => 0, 'since' => $now];
    }
    if ($recordAttempt) { $state['count'] = (int)$state['count'] + 1; }
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $state;
}

function public_ip_address(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
}

function validated_ai_endpoint(string $baseUrl): ?array
{
    if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) { return null; }
    $parts = parse_url($baseUrl);
    if (!is_array($parts) || str_lower_u((string)($parts['scheme'] ?? '')) !== 'https' || isset($parts['user']) || isset($parts['pass'])) { return null; }
    $host = trim((string)($parts['host'] ?? ''), '[]');
    if ($host === '') { return null; }
    $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_values(array_unique(array_merge(
        gethostbynamel($host) ?: [],
        array_column(dns_get_record($host, DNS_AAAA) ?: [], 'ipv6')
    )));
    foreach ($addresses as $address) {
        if (!is_string($address) || !public_ip_address($address)) { return null; }
    }
    if ($addresses === []) { return null; }
    return ['host' => $host, 'port' => (int)($parts['port'] ?? 443), 'ip' => $addresses[0]];
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
    $htaccessRules = "Options -ExecCGI\nRemoveHandler .php .phtml .php3 .php4 .php5 .php7 .php8 .phar .cgi .pl .py .rb .asp .aspx .jsp\n<IfModule mod_headers.c>\n  Header set X-Content-Type-Options nosniff\n</IfModule>\n<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|php8|phar|cgi|pl|py|rb|asp|aspx|jsp)$\">\n  Require all denied\n</FilesMatch>\n";
    if (!is_file($htaccess) || file_get_contents($htaccess) !== $htaccessRules) {
        file_put_contents($htaccess, $htaccessRules, LOCK_EX);
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
    $allowedTypes = [
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
        'gif' => ['image/gif'], 'webp' => ['image/webp'], 'pdf' => ['application/pdf'],
        'txt' => ['text/plain'], 'md' => ['text/plain'], 'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];
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

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmpName) ?: 'application/octet-stream';
        if (!isset($allowedTypes[$extension]) || !in_array($mime, $allowedTypes[$extension], true)) {
            $failed[] = ['name' => $originalName, 'error' => '文件类型不在允许列表中。'];
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

    return $admin = one('SELECT id, username, nickname, email, avatar_url, website_url, created_at FROM users WHERE id = ?', [$id]);
}

function authenticated_comment_identity(): ?array
{
    $admin = current_admin();
    if ($admin === null) {
        return null;
    }

    $name = trim((string)($admin['nickname'] ?? '')) ?: trim((string)$admin['username']);
    $name = trim((string)(preg_replace('/\s+/u', ' ', $name) ?? $name));
    $name = str_sub_u($name, 0, 50);
    $email = str_lower_u(trim((string)($admin['email'] ?? '')));
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160)) {
        $email = '';
    }

    $url = trim((string)($admin['website_url'] ?? ''));
    $scheme = str_lower_u((string)parse_url($url, PHP_URL_SCHEME));
    if ($url !== '' && (strlen($url) > 300 || !filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true))) {
        $url = '';
    }

    return [
        'user_id' => (int)$admin['id'],
        'author_name' => $name,
        'author_email' => $email,
        'author_url' => $url,
    ];
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

function require_admin_post(string $fallbackUrl): void
{
    require_admin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect_to($fallbackUrl);
    }
    verify_csrf();
}

function positive_int_ids(mixed $values, int $limit = 100): array
{
    $values = is_array($values) ? $values : [$values];
    return array_slice(
        array_values(array_unique(array_filter(array_map('intval', $values), static fn(int $id): bool => $id > 0))),
        0,
        $limit
    );
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

    if (preg_match('#^/sitemap\.xml$#i', $path)) {
        set_route_params(['a' => 'sitemap']);
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

    if (preg_match('#^/links/?$#i', $path)) {
        set_route_params(['a' => 'links']);
        return;
    }

    if (preg_match('#^/tag/(.+)$#u', $path, $matches)) {
        set_route_params(['a' => 'tag', 'slug' => trim($matches[1], '/')]);
        return;
    }

    if (preg_match('#^/category/(.+)$#u', $path, $matches)) {
        set_route_params(['a' => 'category', 'slug' => trim($matches[1], '/')]);
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

    if (preg_match('#^/admin/(posts|comments|categories|tags|links|users|ai|settings)/?$#i', $path, $matches)) {
        set_route_params(['a' => 'admin_' . str_lower_u($matches[1])]);
        return;
    }

    if (preg_match('#^/forgot-password/?$#i', $path)) {
        set_route_params(['a' => 'forgot_password']);
        return;
    }

    if (preg_match('#^/reset-password/?$#i', $path)) {
        set_route_params(['a' => 'reset_password']);
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
        header('Location: ' . app_path('/archive/' . rawurlencode(trim($matches[1], '/'))), true, 301);
        exit;
    }

    if (preg_match('#^/archive/(.+)$#u', $path, $matches)) {
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
        'sitemap' => $pretty ? app_path('/sitemap.xml') : script_url() . '?a=sitemap',
        'archives' => $pretty ? app_path('/archives') : script_url() . '?a=archives',
        'tags' => $pretty ? app_path('/tags') : script_url() . '?a=tags',
        'links' => $pretty ? app_path('/links') : script_url() . '?a=links',
        'tag' => $pretty ? app_path('/tag/' . rawurlencode((string)($params['slug'] ?? ''))) : script_url() . '?a=tag&slug=' . rawurlencode((string)($params['slug'] ?? '')),
        'category' => $pretty ? app_path('/category/' . rawurlencode((string)($params['slug'] ?? ''))) : script_url() . '?a=category&slug=' . rawurlencode((string)($params['slug'] ?? '')),
        'page' => $pretty ? app_path('/' . rawurlencode((string)($params['slug'] ?? ''))) : script_url() . '?a=page&slug=' . rawurlencode((string)($params['slug'] ?? '')),
        'login' => $pretty ? app_path('/login') : script_url() . '?a=login',
        'forgot_password' => $pretty ? app_path('/forgot-password') : script_url() . '?a=forgot_password',
        'reset_password' => $pretty ? app_path('/reset-password') : script_url() . '?a=reset_password',
        'logout' => $pretty ? app_path('/logout') : script_url() . '?a=logout',
        'admin' => $pretty ? app_path('/admin') : script_url() . '?a=admin',
        'admin_posts' => $pretty ? app_path('/admin/posts') : script_url() . '?a=admin_posts',
        'admin_comments' => $pretty ? app_path('/admin/comments') : script_url() . '?a=admin_comments',
        'admin_categories' => $pretty ? app_path('/admin/categories') : script_url() . '?a=admin_categories',
        'admin_tags' => $pretty ? app_path('/admin/tags') : script_url() . '?a=admin_tags',
        'admin_links' => $pretty ? app_path('/admin/links') : script_url() . '?a=admin_links',
        'admin_users' => $pretty ? app_path('/admin/users') : script_url() . '?a=admin_users',
        'admin_ai' => $pretty ? app_path('/admin/ai') : script_url() . '?a=admin_ai',
        'admin_settings' => $pretty ? app_path('/admin/settings') : script_url() . '?a=admin_settings',
        'write' => $pretty ? app_path('/write') : script_url() . '?a=write',
        'edit' => $pretty ? app_path('/edit/' . (int)($params['id'] ?? 0)) : script_url() . '?a=edit&id=' . (int)($params['id'] ?? 0),
        'post' => $pretty ? app_path('/archive/' . rawurlencode((string)($params['slug'] ?? ''))) : script_url() . '?a=post&slug=' . rawurlencode((string)($params['slug'] ?? '')),
        'save_settings' => script_url() . '?a=save_settings',
        'save_ai_settings' => script_url() . '?a=save_ai_settings',
        'ai_generate' => script_url() . '?a=ai_generate',
        'save_category' => script_url() . '?a=save_category',
        'delete_category' => script_url() . '?a=delete_category',
        'save_tag' => script_url() . '?a=save_tag',
        'delete_tag' => script_url() . '?a=delete_tag',
        'save_link' => script_url() . '?a=save_link',
        'delete_link' => script_url() . '?a=delete_link',
        'save_user' => script_url() . '?a=save_user',
        'delete_user' => script_url() . '?a=delete_user',
        'upload_attachment' => script_url() . '?a=upload_attachment',
        'delete_post' => script_url() . '?a=delete_post',
        'change_status' => script_url() . '?a=change_status',
        'submit_comment' => script_url() . '?a=submit_comment',
        'moderate_comments' => script_url() . '?a=moderate_comments',
        'mark_comments_read' => script_url() . '?a=mark_comments_read',
        'install_update' => script_url() . '?a=install_update',
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
        $footer = '© 2026 - {year} Theme by jkjoy.';
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
        $tags[] = ['label' => $label, 'slug' => tag_slug_for_label($label)];
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

function tag_slug_for_label(string $label): string
{
    $stored = val('SELECT slug FROM tag_meta WHERE label = ?', [$label]);
    if (is_string($stored) && $stored !== '') { return $stored; }
    $base = slugify($label);
    $slug = $base;
    $suffix = 2;
    while (one('SELECT label FROM tag_meta WHERE slug = ?', [$slug])) { $slug = $base . '-' . $suffix++; }
    q('INSERT OR IGNORE INTO tag_meta(label, slug, updated_at) VALUES(?,?,?)', [$label, $slug, time()]);
    return (string)(val('SELECT slug FROM tag_meta WHERE label = ?', [$label]) ?: $slug);
}

function split_bare_url_suffix(string $url): array
{
    $suffix = '';
    $pairs = [')' => '(', ']' => '['];

    while ($url !== '') {
        if (preg_match("/[.,!?;:*']+$/", $url, $matches)) {
            $ending = $matches[0];
            $url = substr($url, 0, -strlen($ending));
            $suffix = $ending . $suffix;
            continue;
        }

        if (str_ends_with($url, '~~')) {
            $url = substr($url, 0, -2);
            $suffix = '~~' . $suffix;
            continue;
        }

        $last = substr($url, -1);
        if (isset($pairs[$last]) && substr_count($url, $last) > substr_count($url, $pairs[$last])) {
            $url = substr($url, 0, -1);
            $suffix = $last . $suffix;
            continue;
        }

        break;
    }

    return [$url, $suffix];
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

        // Tokenize bare URLs so existing Markdown links and images cannot be linked twice.
        $marker = "\x1A";
        while (str_contains($part, $marker)) {
            $marker .= "\x1A";
        }

        $bareLinks = [];
        $part = preg_replace_callback(
            '~\[!\[.*?\]\([^\s)]+\)\]\([^\s)]+\)|!\[.*?\]\([^\s)]+\)|(?<!!)\[.+?\]\([^\s)]+\)|(?<![A-Z0-9_])(?<bare_url>https?://[A-Z0-9._:/?#\[\]@!$&\'()*+,;=%\~-]+)~iu',
            static function (array $matches) use (&$bareLinks, $marker): string {
                $matchedUrl = (string)($matches['bare_url'] ?? '');
                if ($matchedUrl === '') {
                    return $matches[0];
                }

                [$url, $suffix] = split_bare_url_suffix($matchedUrl);
                $href = safe_link_url($url);
                if ($href === '#') {
                    return $matches[0];
                }

                $token = $marker . count($bareLinks) . $marker;
                $bareLinks[$token] = '<a href="' . h($href) . '" target="_blank" rel="noopener noreferrer">' . h($url) . '</a>';
                return $token . $suffix;
            },
            $part
        ) ?? $part;

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

        $html .= strtr($escaped, $bareLinks);
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
        'SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
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

function category_by_id(?int $id): ?array
{
    return $id ? one('SELECT * FROM categories WHERE id = ?', [$id]) : null;
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
    return all_rows('SELECT id, slug, title, published_at, tags, kind, is_pinned FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC', ['post', 'published', time()]);
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
    return asset_url('logo.png');
}

function theme_favicon_url(): string
{
    $value = trim(setting('favicon_url', default_settings()['favicon_url']));
    if ($value === '') { $value = 'logo.png'; }
    if (preg_match('#^https?://#i', $value) || str_starts_with($value, '/')) { return $value; }
    return asset_url($value);
}

function public_quote(): string
{
    $quote = trim(setting('site_tagline'));

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
          <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>"><?php if (!empty($post['is_pinned'])): ?><span class="pinned-badge">置顶</span><?php endif; ?><?= h((string)$post['title']) ?></a>
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
    $commentCounts = comment_admin_counts();

    return [
        'total_posts' => $totalPosts,
        'published' => $publishedPosts,
        'pages' => (int)val('SELECT COUNT(*) FROM posts WHERE kind = ? AND status = ? AND published_at <= ?', ['page', 'published', $now]),
        'drafts' => (int)val("SELECT COUNT(*) FROM posts WHERE status = 'draft'"),
        'scheduled' => (int)val('SELECT COUNT(*) FROM posts WHERE status = ? AND published_at > ?', ['published', $now]),
        'categories' => (int)val('SELECT COUNT(*) FROM categories'),
        'total_views' => $totalViews,
        'avg_views' => $totalPosts > 0 ? (int)floor($totalViews / $totalPosts) : 0,
        'comments' => $commentCounts['all'],
        'pending_comments' => $commentCounts['pending'],
        'top_viewed' => all_rows('SELECT id, slug, title, views FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY views DESC, updated_at DESC LIMIT 5', ['post', 'published', $now]),
    ];
}

function comment_status_meta(string $status): array
{
    return match ($status) {
        'approved' => ['label' => '已通过', 'class' => 'approved'],
        'spam' => ['label' => '垃圾评论', 'class' => 'spam'],
        default => ['label' => '待审核', 'class' => 'pending'],
    };
}

function public_comments_for_post(int $postId, int $limit = 100): array
{
    $limit = max(1, min(200, $limit));
    return all_rows(
        "SELECT id, parent_id, reply_to_name, author_name, author_url, content, created_at
         FROM (
             SELECT id, parent_id, reply_to_name, author_name, author_url, content, created_at
             FROM comments
             WHERE post_id = ? AND status = 'approved'
             ORDER BY created_at DESC, id DESC
             LIMIT {$limit}
         )
         ORDER BY created_at ASC, id ASC",
        [$postId]
    );
}

function approved_comment_count(int $postId): int
{
    return (int)val('SELECT COUNT(*) FROM comments WHERE post_id = ? AND status = ?', [$postId, 'approved']);
}

function approved_reply_target(int $postId, int $parentId): ?array
{
    if ($parentId < 1) {
        return null;
    }

    return one(
        'SELECT id, author_name FROM comments WHERE id = ? AND post_id = ? AND status = ?',
        [$parentId, $postId, 'approved']
    );
}

function visitor_email_has_approved_comment(string $email): bool
{
    $email = str_lower_u(trim($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    return val(
        'SELECT 1 FROM comments WHERE user_id IS NULL AND author_email = ? COLLATE NOCASE AND status = ? LIMIT 1',
        [$email, 'approved']
    ) !== false;
}

function comment_admin_counts(): array
{
    static $counts = null;
    if (is_array($counts)) {
        return $counts;
    }

    $row = one(
        "SELECT
            COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END), 0) AS unread,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending,
            COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved,
            COALESCE(SUM(CASE WHEN status = 'spam' THEN 1 ELSE 0 END), 0) AS spam
         FROM comments"
    ) ?? [];

    return $counts = [
        'all' => (int)($row['total'] ?? 0),
        'unread' => (int)($row['unread'] ?? 0),
        'pending' => (int)($row['pending'] ?? 0),
        'approved' => (int)($row['approved'] ?? 0),
        'spam' => (int)($row['spam'] ?? 0),
    ];
}

function unread_comment_count(): int
{
    return comment_admin_counts()['unread'];
}

function recent_comment_notifications(int $limit = 5): array
{
    $limit = max(1, min(20, $limit));
    return all_rows(
        "SELECT c.id, c.author_name, c.reply_to_name, c.content, c.created_at, p.kind AS post_kind, p.slug AS post_slug, p.title AS post_title
         FROM comments c
         INNER JOIN posts p ON p.id = c.post_id
         WHERE c.is_read = 0
         ORDER BY c.created_at DESC, c.id DESC
         LIMIT {$limit}"
    );
}

function admin_comments_url(string $filter = 'all', string $search = '', int $page = 1): string
{
    if (!in_array($filter, ['all', 'unread', 'pending', 'approved', 'spam'], true)) {
        $filter = 'all';
    }
    $search = str_sub_u(trim($search), 0, 100);
    $params = [];
    if ($filter !== 'all') { $params['filter'] = $filter; }
    if ($search !== '') { $params['q'] = $search; }
    if ($page > 1) { $params['p'] = $page; }
    $url = url_for('admin_comments');
    return $params === [] ? $url : url_with_query($url, $params);
}

function fetch_admin_comments(string $filter, string $search, int $page, int $perPage = 20): array
{
    $allowed = ['all', 'unread', 'pending', 'approved', 'spam'];
    $filter = in_array($filter, $allowed, true) ? $filter : 'all';
    $where = [];
    $params = [];

    if ($filter === 'unread') {
        $where[] = 'c.is_read = 0';
    } elseif (in_array($filter, ['pending', 'approved', 'spam'], true)) {
        $where[] = 'c.status = ?';
        $params[] = $filter;
    }

    if ($search !== '') {
        $where[] = '(c.author_name LIKE ? OR c.author_email LIKE ? OR c.reply_to_name LIKE ? OR c.content LIKE ? OR p.title LIKE ?)';
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term, $term);
    }

    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $total = (int)val('SELECT COUNT(*) FROM comments c INNER JOIN posts p ON p.id = c.post_id' . $whereSql, $params);
    $perPage = max(1, min(100, $perPage));
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    $rows = all_rows(
        "SELECT c.*, p.kind AS post_kind, p.slug AS post_slug, p.title AS post_title
         FROM comments c
         INNER JOIN posts p ON p.id = c.post_id
         {$whereSql}
         ORDER BY c.is_read ASC, c.created_at DESC, c.id DESC
         LIMIT {$perPage} OFFSET {$offset}",
        $params
    );

    return [$rows, $total, $page, $totalPages, $filter];
}

function comment_excerpt(string $content, int $length = 100): string
{
    $excerpt = trim((string)(preg_replace('/\s+/u', ' ', $content) ?? $content));
    return str_len_u($excerpt) > $length ? rtrim(str_sub_u($excerpt, 0, $length)) . '…' : $excerpt;
}

function comment_form_started_at(int $postId): int
{
    if (!isset($_SESSION['comment_forms']) || !is_array($_SESSION['comment_forms'])) {
        $_SESSION['comment_forms'] = [];
    }
    $cutoff = time() - 7200;
    foreach ($_SESSION['comment_forms'] as $storedPostId => $timestamps) {
        if (!is_array($timestamps)) {
            unset($_SESSION['comment_forms'][$storedPostId]);
            continue;
        }
        $_SESSION['comment_forms'][$storedPostId] = array_filter(
            $timestamps,
            static fn(mixed $value, int|string $timestamp): bool => (int)$timestamp >= $cutoff,
            ARRAY_FILTER_USE_BOTH
        );
        if ($_SESSION['comment_forms'][$storedPostId] === []) {
            unset($_SESSION['comment_forms'][$storedPostId]);
        }
    }
    $startedAt = time();
    $_SESSION['comment_forms'][$postId][(string)$startedAt] = true;
    return $startedAt;
}

function forget_comment_form(int $postId, int $startedAt): void
{
    unset($_SESSION['comment_forms'][$postId][(string)$startedAt]);
    if (empty($_SESSION['comment_forms'][$postId])) {
        unset($_SESSION['comment_forms'][$postId]);
    }
}

function set_comment_feedback(int $postId, array $form, array $errors): void
{
    $_SESSION['comment_feedback'][$postId] = ['form' => $form, 'errors' => $errors];
}

function pull_comment_feedback(int $postId): array
{
    $feedback = $_SESSION['comment_feedback'][$postId] ?? null;
    unset($_SESSION['comment_feedback'][$postId]);
    if (!is_array($feedback)) {
        return [[], []];
    }
    return [
        is_array($feedback['form'] ?? null) ? $feedback['form'] : [],
        is_array($feedback['errors'] ?? null) ? $feedback['errors'] : [],
    ];
}

function set_comment_notice(int $postId, string $type, string $message): void
{
    $_SESSION['comment_notices'][$postId] = ['type' => $type, 'message' => $message];
}

function pull_comment_notice(int $postId): ?array
{
    $notice = $_SESSION['comment_notices'][$postId] ?? null;
    unset($_SESSION['comment_notices'][$postId]);
    return is_array($notice) ? $notice : null;
}

function comment_ip_hash(): string
{
    $address = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return hash_hmac('sha256', $address, DB_FILE !== '' ? DB_FILE : __FILE__);
}

function comment_rate_file(): string
{
    $address = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return CACHE_DIR . '/comment-' . hash('sha256', $address) . '.json';
}

function comment_rate_guard_file(): string
{
    return CACHE_DIR . '/comment-rate.lock';
}

function prune_comment_rate_files(): void
{
    $guard = @fopen(comment_rate_guard_file(), 'c+');
    if ($guard === false) {
        return;
    }
    if (!flock($guard, LOCK_EX | LOCK_NB)) {
        fclose($guard);
        return;
    }

    $checked = 0;
    $visited = 0;
    $cutoff = time() - 86400;
    rewind($guard);
    $storedCursor = trim((string)stream_get_contents($guard));
    $cursor = ctype_digit($storedCursor) ? (int)$storedCursor : 0;
    $nextCursor = $cursor;

    try {
        $iterator = new DirectoryIterator(CACHE_DIR);
        if ($cursor > 0) {
            $iterator->seek($cursor);
        }

        while ($iterator->valid() && $visited < 64 && $checked < 8) {
            $filename = $iterator->getFilename();
            $isRateFile = $iterator->isFile() && preg_match('/^comment-[a-f0-9]{64}\.json$/', $filename);
            $path = $isRateFile ? $iterator->getPathname() : '';
            $mtime = $isRateFile ? $iterator->getMTime() : 0;
            $iterator->next();
            $nextCursor++;
            $visited++;

            if (!$isRateFile) {
                continue;
            }
            $checked++;
            if ($mtime >= $cutoff) {
                continue;
            }

            $candidate = @fopen($path, 'r');
            if ($candidate === false) {
                continue;
            }
            if (flock($candidate, LOCK_EX | LOCK_NB)) {
                $stat = fstat($candidate);
                if (is_array($stat) && (int)($stat['mtime'] ?? PHP_INT_MAX) < $cutoff) {
                    @unlink($path);
                }
                flock($candidate, LOCK_UN);
            }
            fclose($candidate);
        }

        if (!$iterator->valid()) {
            $nextCursor = 0;
        }
    } catch (Throwable) {
        $nextCursor = 0;
    }

    $encodedCursor = (string)$nextCursor;
    if (ftruncate($guard, 0) && rewind($guard)) {
        $written = fwrite($guard, $encodedCursor);
        if (is_int($written) && $written === strlen($encodedCursor)) {
            fflush($guard);
        }
    }
    flock($guard, LOCK_UN);
    fclose($guard);
}

function record_comment_attempt(): bool
{
    ensure_runtime_dirs();
    prune_comment_rate_files();
    $guard = @fopen(comment_rate_guard_file(), 'c+');
    if ($guard === false) {
        return false;
    }
    if (!flock($guard, LOCK_SH)) {
        fclose($guard);
        return false;
    }

    $handle = @fopen(comment_rate_file(), 'c+');
    if ($handle === false) {
        flock($guard, LOCK_UN);
        fclose($guard);
        return false;
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        flock($guard, LOCK_UN);
        fclose($guard);
        return false;
    }
    $raw = stream_get_contents($handle);
    $state = json_decode($raw ?: '', true);
    $now = time();
    if (!is_array($state) || $now - (int)($state['since'] ?? 0) >= 600) {
        $state = ['count' => 0, 'since' => $now];
    }
    $allowed = (int)$state['count'] < 3;
    if ($allowed) {
        $state['count'] = (int)$state['count'] + 1;
    }
    $encoded = json_encode($state);
    $stored = is_string($encoded) && rewind($handle);
    if ($stored) {
        $length = strlen($encoded);
        $offset = 0;
        while ($offset < $length) {
            $written = fwrite($handle, substr($encoded, $offset));
            if (!is_int($written) || $written < 1) {
                $stored = false;
                break;
            }
            $offset += $written;
        }
        if ($stored) {
            $stored = ftruncate($handle, $length) && fflush($handle);
        }
    }
    flock($handle, LOCK_UN);
    fclose($handle);
    flock($guard, LOCK_UN);
    fclose($guard);
    return $allowed && $stored;
}

function validate_comment_input(array $input, bool $requireEmail = true): array
{
    $name = trim((string)($input['author_name'] ?? ''));
    $name = trim((string)(preg_replace('/\s+/u', ' ', $name) ?? $name));
    $email = str_lower_u(trim((string)($input['author_email'] ?? '')));
    $url = trim((string)($input['author_url'] ?? ''));
    $content = trim(str_replace(["\r\n", "\r"], "\n", (string)($input['content'] ?? '')));
    $errors = [];
    $rawParentId = $input['parent_id'] ?? '';
    $parentText = is_scalar($rawParentId) ? trim((string)$rawParentId) : '';
    $parentId = 0;
    if (!is_scalar($rawParentId) || ($parentText !== '' && $parentText !== '0' && (!ctype_digit($parentText) || (int)$parentText < 1))) {
        $errors[] = '回复目标不存在或当前不可用。';
    } elseif ($parentText !== '' && $parentText !== '0') {
        $parentId = (int)$parentText;
    }

    if ($name === '') { $errors[] = '请填写昵称。'; }
    elseif (str_len_u($name) > 50) { $errors[] = '昵称不能超过 50 个字符。'; }
    if (($requireEmail && $email === '') || ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160))) { $errors[] = '请填写有效的邮箱地址。'; }
    if ($url !== '') {
        $scheme = str_lower_u((string)parse_url($url, PHP_URL_SCHEME));
        if (strlen($url) > 300 || !filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            $errors[] = '网站地址必须是有效的 HTTP 或 HTTPS 链接。';
        }
    }
    if ($content === '') { $errors[] = '请填写评论内容。'; }
    elseif (str_len_u($content) > 3000) { $errors[] = '评论内容不能超过 3000 个字符。'; }

    return [[
        'author_name' => str_sub_u($name, 0, 50),
        'author_email' => str_sub_u($email, 0, 160),
        'author_url' => str_sub_u($url, 0, 300),
        'content' => str_sub_u($content, 0, 3000),
        'parent_id' => (string)$parentId,
    ], $errors];
}

function duplicate_comment_error(int $postId, int $parentId, int $userId, string $email, string $content): string
{
    $identitySql = $userId > 0 ? 'user_id = ?' : 'user_id IS NULL AND author_email = ?';
    $identityValue = $userId > 0 ? $userId : $email;
    if ($parentId > 0) {
        $duplicate = (int)val(
            'SELECT COUNT(*) FROM comments WHERE post_id = ? AND parent_id = ? AND ' . $identitySql . ' AND content = ? AND created_at >= ?',
            [$postId, $parentId, $identityValue, $content, time() - 86400]
        );
    } else {
        $duplicate = (int)val(
            'SELECT COUNT(*) FROM comments WHERE post_id = ? AND parent_id IS NULL AND ' . $identitySql . ' AND content = ? AND created_at >= ?',
            [$postId, $identityValue, $content, time() - 86400]
        );
    }
    return $duplicate > 0 ? '这条评论已经提交过了。' : '';
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

function tag_index_data(bool $publishedOnly = true): array
{
    $map = [];
    $posts = $publishedOnly
        ? all_rows('SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY published_at DESC, id DESC', ['post', 'published', time()])
        : all_rows('SELECT * FROM posts WHERE kind = ? ORDER BY updated_at DESC, id DESC', ['post']);

    foreach ($posts as $post) {
        foreach (post_tags($post) as $label) {
            $slug = tag_slug_for_label($label);
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
    $posts = all_rows('SELECT * FROM posts WHERE kind = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC', ['post', 'published', time()]);

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
    $isPinned = isset($input['is_pinned']) && (string)$input['is_pinned'] === '1' ? 1 : 0;
    $errors = [];

    if ($title === '') {
        $errors[] = '标题不能为空。';
    }

    if ($content === '') {
        $errors[] = '正文不能为空。';
    }

    $kind = $kind === 'page' ? 'page' : 'post';
    $categoryId = $kind === 'post' && $categoryId > 0 && one('SELECT id FROM categories WHERE id = ?', [$categoryId]) ? $categoryId : null;
    if ($kind === 'post' && $categoryId === null) {
        $errors[] = '文章必须选择一个分类。';
    }
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
        'is_pinned' => $kind === 'post' ? $isPinned : 0,
    ], $errors];
}

function post_form_from_request(array $input): array
{
    return [
        'kind' => (string)($input['kind'] ?? 'post'),
        'category_id' => (string)($input['category_id'] ?? ''),
        'title' => (string)($input['title'] ?? ''),
        'slug' => (string)($input['slug'] ?? ''),
        'tags_input' => (string)($input['tags_input'] ?? ''),
        'excerpt' => (string)($input['excerpt'] ?? ''),
        'content' => (string)($input['content'] ?? ''),
        'status' => (string)($input['status'] ?? 'draft'),
        'published_at' => (string)($input['published_at'] ?? ''),
        'is_pinned' => isset($input['is_pinned']) ? '1' : '0',
    ];
}

function render_layout(string $title, string $content, array $options = []): void
{
    $siteName = setting('site_name', default_settings()['site_name']);
    $fullTitle = $title === $siteName ? $siteName : $title . ' · ' . $siteName;
    $description = (string)($options['description'] ?? setting('site_description', setting('site_tagline')));
    $keywords = trim(setting('site_keywords'));
    $active = (string)($options['active'] ?? '');
    $wide = !empty($options['wide']);
    $mode = (string)($options['mode'] ?? 'admin');
    $flash = pull_flash();
    $admin = current_admin();
    $navPages = fetch_nav_pages();
    $status = (int)($options['status'] ?? 200);
    $bodyClass = $mode === 'public' ? 'theme-public' : 'theme-admin';
    $customHeadCode = $mode === 'public' ? trim(setting('custom_head_code')) : '';

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
  <?php if ($keywords !== ''): ?><meta name="keywords" content="<?= h($keywords) ?>"><?php endif; ?>
  <title><?= h($fullTitle) ?></title>
  <link rel="icon" href="<?= h(theme_favicon_url()) ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(asset_url('index.css')) ?>?v=<?= h(APP_VERSION) ?>">
  <?php if ($customHeadCode !== ''): ?>
<?= $customHeadCode . "\n" ?>
  <?php endif; ?>
</head>
<body class="<?= h($bodyClass) ?>">
  <?php if ($mode === 'public'): ?>
    <div class="crt-turn-on" id="turn-on"></div><div class="crt-vignette"></div><div class="scanlines" id="scanlines"></div><div class="crt-flicker"></div>
    <div class="terminal" data-home="<?= h(url_for('home')) ?>" data-tags="<?= h(url_for('tags')) ?>" data-links="<?= h(url_for('links')) ?>" data-archives="<?= h(url_for('archives')) ?>">
      <header class="terminal-header"><div class="window-controls"><span class="dot red"></span><span class="dot yellow"></span><span class="dot green"></span></div><div class="title">visitor@<?= h($siteName) ?>: ~ — devlog-sh 0.9</div><div class="info"><span class="signal"></span><span id="term-info">80×24</span></div></header>
      <main class="output" id="output" aria-live="polite">
        <div class="boot-banner"><b><?= h($siteName) ?> <?= h(APP_VERSION) ?> — <?= h(public_quote()) ?></b><br><span>type "help" to begin · type "ls" to look around</span></div>
        <nav class="terminal-menu" aria-label="主菜单">
          <span class="terminal-menu__label">menu:</span>
          <a class="<?= $active === 'home' ? 'is-active' : '' ?>" href="<?= h(url_for('home')) ?>">[首页]</a>
          <a class="<?= $active === 'tags' ? 'is-active' : '' ?>" href="<?= h(url_for('tags')) ?>">[标签]</a>
          <a class="<?= $active === 'archives' ? 'is-active' : '' ?>" href="<?= h(url_for('archives')) ?>">[归档]</a>
          <a class="<?= $active === 'links' ? 'is-active' : '' ?>" href="<?= h(url_for('links')) ?>">[链接]</a>
          <?php $accountUrl = $admin ? url_for('admin') : url_for('login'); ?>
          <?php $accountLabel = $admin ? '管理' : '登录'; ?>
          <?php $loginLinkRendered = false; ?>
          <?php foreach ($navPages as $page): ?>
            <a class="<?= $active === 'page:' . $page['slug'] ? 'is-active' : '' ?>" href="<?= h(content_permalink($page)) ?>">[<?= h($page['title']) ?>]</a>
            <?php if (!$loginLinkRendered && (strtolower((string)$page['slug']) === 'about' || trim((string)$page['title']) === '关于')): ?>
              <a class="<?= in_array($active, ['login', 'admin'], true) ? 'is-active' : '' ?>" href="<?= h($accountUrl) ?>">[<?= h($accountLabel) ?>]</a>
              <?php $loginLinkRendered = true; ?>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if (!$loginLinkRendered): ?>
            <a class="<?= in_array($active, ['login', 'admin'], true) ? 'is-active' : '' ?>" href="<?= h($accountUrl) ?>">[<?= h($accountLabel) ?>]</a>
          <?php endif; ?>
        </nav>
        <div class="cmd-echo"><span class="prompt-part">visitor@<?= h($siteName) ?></span><span class="path-part">:~</span>$ cat <?= h(strtolower(str_replace(' ', '-', $title))) ?>.md</div>
        <?php if ($flash): ?><div class="line amber"><?= h((string)$flash['message']) ?></div><?php endif; ?>
        <section class="md-content"><?= $content ?></section>
        <div class="line dim">-- EOF --</div>
        <footer class="terminal-footer">
          <span><?= h(site_footer_text()) ?></span>
          <?php $beian = trim(setting('footer_beian')); ?>
          <?php if ($beian !== ''): ?>
            <span class="terminal-footer__separator">·</span>
            <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener noreferrer"><?= h($beian) ?></a>
          <?php endif; ?>
          <span class="terminal-footer__separator">·</span>
          <a href="<?= h(url_for('rss')) ?>">RSS</a>
          <span class="terminal-footer__separator">·</span>
          <a href="<?= h(url_for('sitemap')) ?>">Sitemap</a>
        </footer>
      </main>
      <footer class="prompt-line"><span class="prompt"><span>visitor@<?= h($siteName) ?></span><span class="path" id="prompt-path">:~</span><span class="symbol">$</span>&nbsp;</span><span class="input-text" id="input-text"></span><span class="cursor"></span><span class="ghost-text" id="ghost-text"></span><input id="input" type="text" autofocus autocomplete="off" spellcheck="false" aria-label="Terminal input"></footer>
    </div>
  <?php else: ?>
    <div class="site-frame">
      <header class="site-header">
        <div class="site-header__inner">
          <a class="site-brand" href="<?= h($admin ? url_for('admin') : url_for('home')) ?>">
            <img class="site-brand__logo" src="<?= h(theme_logo_url()) ?>" width="44" height="44" alt="<?= h($siteName) ?>">
            <span class="site-brand__copy">
              <strong class="site-brand__title"><?= h($siteName) ?></strong>
              <span class="site-brand__meta"><?= $admin ? 'Simple-PHP-Blog Admin' : 'Admin Entry' ?></span>
            </span>
          </a>
          <?php if ($admin): ?>
            <nav class="site-nav site-nav--admin" aria-label="Primary">
              <a class="nav-link<?= $active === 'admin' ? ' is-active' : '' ?>" href="<?= h(url_for('admin')) ?>">管理后台</a>
              <a class="nav-link nav-link--pill<?= in_array($active, ['write', 'edit'], true) ? ' is-active' : '' ?>" href="<?= h(url_for('write')) ?>">撰写文章</a>
              <a class="nav-link" href="<?= h(url_for('logout')) ?>">退出</a>
            </nav>
          <?php endif; ?>
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
          <span class="site-footer__meta">Powered by Simple PHP Blog <?= h(APP_VERSION) ?></span>
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
        'comments' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4v8z"></path><path d="M8 9h8"></path><path d="M8 13h5"></path>',
        'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path>',
        'categories' => '<path d="M3 6h7l2 2h9v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6z"></path>',
        'tags' => '<path d="M12.6 2.6H5a2.4 2.4 0 0 0-2.4 2.4v7.6a2.4 2.4 0 0 0 .7 1.7l6.4 6.4a2.4 2.4 0 0 0 3.4 0l7.6-7.6a2.4 2.4 0 0 0 0-3.4l-6.4-6.4a2.4 2.4 0 0 0-1.7-.7z"></path><circle cx="8" cy="8" r="1"></circle>',
        'links' => '<path d="M10 13a5 5 0 0 0 7.5.5l2-2a5 5 0 0 0-7.1-7.1l-1.1 1.1"></path><path d="M14 11a5 5 0 0 0-7.5-.5l-2 2a5 5 0 0 0 7.1 7.1l1.1-1.1"></path>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'ai' => '<path d="m12 3-1.4 3.6L7 8l3.6 1.4L12 13l1.4-3.6L17 8l-3.6-1.4L12 3z"></path><path d="m5 14-.8 2.2L2 17l2.2.8L5 20l.8-2.2L8 17l-2.2-.8L5 14z"></path><path d="m19 13-1 2.5-2.5 1 2.5 1L19 20l1-2.5 2.5-1-2.5-1L19 13z"></path>',
        'settings' => '<path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5z"></path><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1A2 2 0 1 1 4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1 1.7 1.7 0 0 0-.3-1.9L4.2 7A2 2 0 1 1 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3h.1a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5h.1a1.7 1.7 0 0 0 1.9-.3l.1-.1A2 2 0 1 1 19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9v.1a1.7 1.7 0 0 0 1.5 1h.1a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"></path>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><path d="M16 17l5-5-5-5"></path><path d="M21 12H9"></path>',
        default => '<circle cx="12" cy="12" r="8"></circle>',
    };

    return '<svg ' . $attrs . '>' . $paths . '</svg>';
}

function render_admin_sidebar(string $active, array $summary = []): string
{
    $siteName = setting('site_name', default_settings()['site_name']);
    $admin = current_admin();
    $adminName = (string)($admin['username'] ?? 'Admin');
    $adminId = (int)($admin['id'] ?? 0);
    $adminAvatarUrl = trim((string)($admin['avatar_url'] ?? ''));
    $adminInitial = str_sub_u($adminName, 0, 1);
    $userSettingsUrl = $adminId > 0 ? url_with_query(url_for('admin_users'), ['id' => $adminId]) : url_for('admin_users');
    $unreadComments = unread_comment_count();
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
            'label' => '评论管理',
            'icon' => 'comments',
            'note' => '审核与通知',
            'href' => url_for('admin_comments'),
            'active' => $active === 'comments',
            'badge' => $unreadComments,
        ],
        [
            'label' => '分类管理',
            'icon' => 'categories',
            'note' => '分类与排序',
            'href' => url_for('admin_categories'),
            'active' => $active === 'categories',
        ],
        [
            'label' => '标签管理',
            'icon' => 'tags',
            'note' => '重命名与清理',
            'href' => url_for('admin_tags'),
            'active' => $active === 'tags',
        ],
        [
            'label' => '友情链接',
            'icon' => 'links',
            'note' => '添加、排序与维护',
            'href' => url_for('admin_links'),
            'active' => $active === 'links',
        ],
        [
            'label' => 'AI 设置',
            'icon' => 'ai',
            'note' => '模型与接口',
            'href' => url_for('admin_ai'),
            'active' => $active === 'ai',
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
            <?php $linkBadge = (int)($link['badge'] ?? 0); ?>
            <?php $linkLabel = (string)$link['label'] . ($linkBadge > 0 ? '，' . $linkBadge . ' 条未读评论' : ''); ?>
            <a class="admin-side__link<?= $link['active'] ? ' is-active' : '' ?>" href="<?= h((string)$link['href']) ?>" title="<?= h((string)$link['label']) ?>" aria-label="<?= h($linkLabel) ?>"<?= $link['active'] ? ' aria-current="page"' : '' ?>>
              <?= admin_icon((string)$link['icon']) ?>
              <strong><?= h((string)$link['label']) ?></strong>
              <span><?= h((string)$link['note']) ?></span>
              <?php if ($linkBadge > 0): ?>
                <small class="admin-count-badge" aria-hidden="true"><?= h((string)min(99, $linkBadge)) ?><?= $linkBadge > 99 ? '+' : '' ?></small>
              <?php endif; ?>
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
        <details class="admin-side__account" data-admin-account>
          <summary class="admin-side__account-toggle" aria-label="打开用户菜单：<?= h($adminName) ?>">
            <span class="admin-side__avatar">
              <span aria-hidden="true"><?= h($adminInitial) ?></span>
              <?php if ($adminAvatarUrl !== ''): ?>
                <img src="<?= h($adminAvatarUrl) ?>" alt="" decoding="async" onerror="this.remove()">
              <?php endif; ?>
            </span>
            <span class="admin-side__footer-text"><?= h($adminName) ?></span>
            <span class="admin-side__account-caret" aria-hidden="true"></span>
          </summary>
          <div class="admin-side__account-menu" role="menu">
            <a class="admin-side__account-item" role="menuitem" href="<?= h($userSettingsUrl) ?>">
              <?= admin_icon('users') ?>
              <span>用户设置</span>
            </a>
            <a class="admin-side__account-item admin-side__account-item--danger" role="menuitem" href="<?= h(url_for('logout')) ?>">
              <?= admin_icon('logout') ?>
              <span>退出登录</span>
            </a>
          </div>
        </details>
      </div>
    </aside>
    <?php

    return (string)ob_get_clean();
}

function render_admin_topbar(string $title, string $actionLabel = '', string $actionUrl = ''): string
{
    $unreadComments = unread_comment_count();
    $notificationUrl = $unreadComments > 0 ? admin_comments_url('unread') : url_for('admin_comments');
    ob_start();
    ?>
    <div class="admin-topbar">
      <div class="admin-crumb">控制台 / <b><?= h($title) ?></b></div>
      <div class="admin-topbar__actions">
        <a class="admin-icon-btn admin-icon-btn--notifications" href="<?= h($notificationUrl) ?>" title="评论通知" aria-label="<?= $unreadComments > 0 ? h((string)$unreadComments) . ' 条未读评论' : '暂无未读评论' ?>">
          <?= admin_icon('bell') ?>
          <?php if ($unreadComments > 0): ?><small class="admin-count-badge"><?= h((string)min(99, $unreadComments)) ?><?= $unreadComments > 99 ? '+' : '' ?></small><?php endif; ?>
        </a>
        <a class="admin-icon-btn" href="<?= h(url_for('home')) ?>" target="_blank" rel="noopener noreferrer" title="网站首页" aria-label="网站首页">
          <?= admin_icon('home') ?>
        </a>
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

function render_tag_chips(array $post, string $class = 'post-tag-list'): string
{
    $tags = tag_descriptors($post);
    if ($tags === []) {
        return '';
    }

    ob_start();
    ?>
    <div class="<?= h($class) ?>">
      <?php foreach ($tags as $tag): ?>
        <a class="post-tag" href="<?= h(url_for('tag', ['slug' => $tag['slug']])) ?>">#<?= h($tag['label']) ?></a>
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

function render_comments_section(array $post, array $form = [], array $errors = []): string
{
    $postId = (int)$post['id'];
    $comments = public_comments_for_post($postId);
    $total = approved_comment_count($postId);
    $accepting = setting('comments_enabled', '1') === '1' && is_live_post($post);
    $notice = pull_comment_notice($postId);

    if (!$accepting && $total === 0 && $notice === null) {
        return '';
    }

    $authenticatedIdentity = authenticated_comment_identity();
    $identity = $authenticatedIdentity ?? (is_array($_SESSION['comment_identity'] ?? null) ? $_SESSION['comment_identity'] : []);
    $values = array_merge([
        'author_name' => (string)($identity['author_name'] ?? ''),
        'author_email' => (string)($identity['author_email'] ?? ''),
        'author_url' => (string)($identity['author_url'] ?? ''),
        'content' => '',
        'parent_id' => '',
    ], $form);
    if ($authenticatedIdentity !== null) {
        $values = array_merge($values, $authenticatedIdentity);
    }
    $replyTarget = approved_reply_target($postId, (int)$values['parent_id']);
    $replyTargetId = (int)($replyTarget['id'] ?? 0);
    $replyTargetName = (string)($replyTarget['author_name'] ?? '');
    $visibleCommentIds = [];
    foreach ($comments as $visibleComment) {
        $visibleCommentIds[(int)$visibleComment['id']] = true;
    }
    $invalidFields = [
        'author_name' => false,
        'author_email' => false,
        'author_url' => false,
        'content' => false,
    ];
    foreach ($errors as $error) {
        $error = (string)$error;
        if (str_contains($error, '昵称')) { $invalidFields['author_name'] = true; }
        if (str_contains($error, '邮箱')) { $invalidFields['author_email'] = true; }
        if (str_contains($error, '网站地址')) { $invalidFields['author_url'] = true; }
        if (str_contains($error, '评论内容') || str_contains($error, '已经提交过')) { $invalidFields['content'] = true; }
    }

    ob_start();
    ?>
    <section class="comments" id="comments" aria-labelledby="comments-title">
      <header class="comments__head">
        <h2 class="section-header" id="comments-title">comments.log</h2>
        <span class="comments__count"><?= $total > count($comments) ? '最新 ' . h((string)count($comments)) . ' / 共 ' : '' ?><?= h((string)$total) ?> 条</span>
      </header>

      <?php if ($notice): ?>
        <div class="comment-notice<?= (string)($notice['type'] ?? '') === 'error' ? ' comment-notice--error' : '' ?>" role="<?= (string)($notice['type'] ?? '') === 'error' ? 'alert' : 'status' ?>"><?= h((string)($notice['message'] ?? '')) ?></div>
      <?php endif; ?>

      <?php if ($comments): ?>
        <ol class="comment-list">
          <?php foreach ($comments as $comment): ?>
            <?php $authorUrl = safe_link_url((string)$comment['author_url']); ?>
            <?php $replyName = trim((string)$comment['reply_to_name']); ?>
            <?php $replyParentId = (int)$comment['parent_id']; ?>
            <?php $replyAnchorVisible = $replyParentId > 0 && isset($visibleCommentIds[$replyParentId]); ?>
            <li class="comment-item<?= $replyName !== '' ? ' comment-item--reply' : '' ?>" id="comment-<?= h((string)$comment['id']) ?>">
              <header class="comment-item__meta">
                <?php if ($authorUrl !== '#'): ?>
                  <a class="comment-item__author" href="<?= h($authorUrl) ?>" target="_blank" rel="ugc nofollow noopener noreferrer">@<?= h((string)$comment['author_name']) ?></a>
                <?php else: ?>
                  <strong class="comment-item__author">@<?= h((string)$comment['author_name']) ?></strong>
                <?php endif; ?>
                <time class="comment-item__time" datetime="<?= h(date(DATE_ATOM, (int)$comment['created_at'])) ?>"><?= h(pretty_date((int)$comment['created_at'], true)) ?></time>
                <?php if ($accepting): ?>
                  <button class="comment-reply-button" type="button" data-comment-reply data-comment-id="<?= h((string)$comment['id']) ?>" data-comment-author="<?= h((string)$comment['author_name']) ?>" aria-controls="comment-form" aria-pressed="<?= $replyTargetId === (int)$comment['id'] ? 'true' : 'false' ?>" aria-label="回复 @<?= h((string)$comment['author_name']) ?>" title="回复 @<?= h((string)$comment['author_name']) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 17-5-5 5-5"></path><path d="M20 18v-2a4 4 0 0 0-4-4H4"></path></svg>
                    <span>回复</span>
                  </button>
                <?php endif; ?>
              </header>
              <div class="comment-item__body">
                <?php if ($replyName !== ''): ?>
                  <?php if ($replyAnchorVisible): ?>
                    <a class="comment-item__reply-target" href="#comment-<?= h((string)$replyParentId) ?>"><span class="sr-only">回复给 @<?= h($replyName) ?></span><span class="comment-item__reply-label" aria-hidden="true">@<?= h($replyName) ?></span></a>
                  <?php else: ?>
                    <span class="comment-item__reply-target"><span class="sr-only">回复给 @<?= h($replyName) ?></span><span class="comment-item__reply-label" aria-hidden="true">@<?= h($replyName) ?></span></span>
                  <?php endif; ?>
                <?php endif; ?>
                <span class="comment-item__content"><?= nl2br(h((string)$comment['content']), false) ?></span>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php else: ?>
        <div class="comments__empty empty-notice">// 暂无评论</div>
      <?php endif; ?>

      <?php if ($accepting): ?>
        <form class="comment-form" id="comment-form" method="post" action="<?= h(url_for('submit_comment')) ?>#comments">
          <?= csrf_field() ?>
          <input type="hidden" name="post_id" value="<?= h((string)$postId) ?>">
          <input type="hidden" name="parent_id" value="<?= $replyTargetId > 0 ? h((string)$replyTargetId) : '' ?>" data-comment-parent-id>
          <input type="hidden" name="comment_started_at" value="<?= h((string)comment_form_started_at($postId)) ?>">
          <div class="comment-honeypot" aria-hidden="true">
            <label for="comment-company">Company</label>
            <input id="comment-company" name="company" type="text" tabindex="-1" autocomplete="off">
          </div>

          <h3 class="comment-form__title">new-comment</h3>
          <div class="comment-reply-state" data-comment-reply-state<?= $replyTargetId > 0 ? '' : ' hidden' ?>>
            <span class="comment-reply-state__text" role="status" aria-live="polite" aria-atomic="true">reply-to: <strong data-comment-reply-name><?= $replyTargetId > 0 ? '@' . h($replyTargetName) : '' ?></strong></span>
            <button class="comment-reply-cancel" type="button" data-comment-reply-cancel aria-label="取消回复">[取消]</button>
          </div>
          <?php if ($errors): ?>
            <div class="comment-notice comment-notice--error" id="comment-errors" role="alert">
              <ul><?php foreach ($errors as $error): ?><li><?= h((string)$error) ?></li><?php endforeach; ?></ul>
            </div>
          <?php endif; ?>

          <?php if ($authenticatedIdentity === null): ?>
            <div class="comment-form__grid">
              <div class="comment-field">
                <label for="comment-author">昵称</label>
                <input id="comment-author" name="author_name" value="<?= h((string)$values['author_name']) ?>" maxlength="50" autocomplete="name"<?= $invalidFields['author_name'] ? ' aria-invalid="true" aria-describedby="comment-errors"' : '' ?> required>
              </div>
              <div class="comment-field">
                <label for="comment-email">邮箱</label>
                <input id="comment-email" name="author_email" type="email" value="<?= h((string)$values['author_email']) ?>" maxlength="160" autocomplete="email"<?= $invalidFields['author_email'] ? ' aria-invalid="true" aria-describedby="comment-errors"' : '' ?> required>
              </div>
              <div class="comment-field comment-field--wide">
                <label for="comment-url">网站（可选）</label>
                <input id="comment-url" name="author_url" type="url" value="<?= h((string)$values['author_url']) ?>" maxlength="300" autocomplete="url" placeholder="https://example.com"<?= $invalidFields['author_url'] ? ' aria-invalid="true" aria-describedby="comment-errors"' : '' ?>>
              </div>
            </div>
          <?php endif; ?>
          <div class="comment-field">
            <label for="comment-content">评论</label>
            <textarea id="comment-content" name="content" rows="6" maxlength="3000"<?= $invalidFields['content'] ? ' aria-invalid="true" aria-describedby="comment-errors"' : '' ?> required><?= h((string)$values['content']) ?></textarea>
          </div>
          <div class="comment-form__actions">
            <button class="terminal-action" type="submit">[提交评论]</button>
          </div>
        </form>
      <?php elseif ($total > 0): ?>
        <div class="comments__empty empty-notice">// 评论已关闭</div>
      <?php endif; ?>
    </section>
    <?php
    return (string)ob_get_clean();
}

function render_post_page(array $post, array $commentForm = [], array $commentErrors = []): void
{
    increment_content_views($post);

    if ($commentForm === [] && $commentErrors === []) {
        [$commentForm, $commentErrors] = pull_comment_feedback((int)$post['id']);
    }

    $neighbors = post_neighbors($post);
    $state = post_state($post);
    $authorProfile = one('SELECT username, nickname FROM users WHERE id = ?', [(int)($post['author_id'] ?? 0)]);
    $author = trim((string)($authorProfile['nickname'] ?? '')) ?: (string)($authorProfile['username'] ?? 'Admin');
    $category = category_by_id(isset($post['category_id']) ? (int)$post['category_id'] : null);
    $categoryName = (string)($category['name'] ?? category_name(null));
    $viewCount = (int)val('SELECT views FROM posts WHERE id = ?', [(int)$post['id']]);
    $displayTime = (int)($post['published_at'] ?: $post['updated_at'] ?: $post['created_at']);
    $tagsMarkup = render_tag_chips($post);

    ob_start();
    ?>
    <article>
      <h1 class="post-title" itemprop="name headline"><?= h($post['title']) ?></h1>
      <div class="meta">
        <span><?= h(date('F j, Y', $displayTime)) ?></span>
        <span>作者: <?= h($author) ?></span>
        <span>分类: <?php if ($category): ?><a href="<?= h(url_for('category', ['slug' => (string)$category['slug']])) ?>"><?= h($categoryName) ?></a><?php else: ?><?= h($categoryName) ?><?php endif; ?></span>
        <span>浏览: <?= h((string)$viewCount) ?></span>
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

    <?= render_comments_section($post, $commentForm, $commentErrors) ?>
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
            <a class="tag-index-link" href="<?= h(url_for('tag', ['slug' => $tag['slug']])) ?>">
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

function render_category_page(string $slug): void
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) { simple_error_page('分类不存在', '没有找到这个文章分类。', 404); }
    $posts = all_rows(
        'SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC',
        ['post', (int)$category['id'], 'published', time()]
    );
    ob_start(); ?>
    <h1 class="post-title"><?= h((string)$category['name']) ?></h1>
    <?php if (trim((string)$category['description']) !== ''): ?><div class="meta"><?= h((string)$category['description']) ?></div><?php endif; ?>
    <?php if ($posts): ?><article><div class="recent-posts section"><?= render_public_post_list($posts) ?></div></article><?php else: ?><div class="empty-notice"><p>这个分类下还没有已发布文章。</p></div><?php endif; ?>
    <?php
    render_layout((string)$category['name'], (string)ob_get_clean(), ['active' => 'home', 'mode' => 'public', 'description' => trim((string)$category['description']) ?: '分类文章']);
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
      <section class="panel auth-panel admin-animate admin-animate--1">
        <div class="panel__body">
          <header class="auth-heading">
            <p class="auth-heading__eyebrow">Admin access</p>
            <h1>登录后台</h1>
            <p>使用管理员账号继续。</p>
          </header>

          <?php if ($error !== ''): ?>
            <div class="flash flash--error" role="alert"><?= h($error) ?></div>
          <?php endif; ?>

          <form class="form-stack" method="post" action="<?= h(url_for('login')) ?>">
            <?= csrf_field() ?>
            <div class="field">
              <label for="username">用户名</label>
              <input id="username" name="username" type="text" value="<?= h((string)($form['username'] ?? '')) ?>" autocomplete="username" required autofocus>
            </div>
            <div class="field">
              <label for="password">密码</label>
              <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <div class="action-row auth-actions">
              <button class="button" type="submit">登录后台</button>
            </div>
            <p class="auth-link-row"><a href="<?= h(url_for('forgot_password')) ?>">忘记密码？</a></p>
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

function password_reset_notice_path(string $token): string
{
    ensure_runtime_dirs();
    return CACHE_DIR . '/password-reset-' . substr(hash('sha256', $token), 0, 16) . '.txt';
}

function create_password_reset(array $user): array
{
    $token = bin2hex(random_bytes(32));
    $now = time();
    $expiresAt = $now + 3600;

    q('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at = 0', [$now, (int)$user['id']]);
    q(
        'INSERT INTO password_resets(user_id, token_hash, expires_at, used_at, created_at) VALUES(?,?,?,?,?)',
        [(int)$user['id'], hash('sha256', $token), $expiresAt, 0, $now]
    );

    return [$token, $expiresAt];
}

function send_password_reset_notice(array $user, string $token, int $expiresAt): bool
{
    $link = absolute_url(url_with_query(url_for('reset_password'), ['token' => $token]));
    $siteName = setting('site_name', default_settings()['site_name']);
    $body = "你正在重置 {$siteName} 的管理员密码。\n\n重置链接：{$link}\n\n链接将在 " . date('Y-m-d H:i:s', $expiresAt) . " 过期。如果不是你本人操作，请忽略这封邮件。";
    $sent = false;
    $email = trim((string)($user['email'] ?? ''));

    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && function_exists('mail')) {
        $subject = '重置 ' . $siteName . ' 管理员密码';
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . preg_replace('/[\r\n]+/', '', $siteName) . ' <no-reply@' . parse_url(site_root_url(), PHP_URL_HOST) . '>',
        ];
        $sent = @mail($email, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers));
    }

    file_put_contents(password_reset_notice_path($token), $body . "\n", LOCK_EX);
    return $sent;
}

function password_reset_by_token(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $reset = one(
        'SELECT r.*, u.username, u.email FROM password_resets r INNER JOIN users u ON u.id = r.user_id WHERE r.token_hash = ? AND r.used_at = 0 AND r.expires_at >= ?',
        [hash('sha256', $token), time()]
    );

    return $reset ?: null;
}

function render_forgot_password_page(string $notice = '', string $error = '', array $form = []): void
{
    ob_start();
    ?>
    <div class="auth-layout">
      <section class="panel auth-panel admin-animate admin-animate--1">
        <div class="panel__body">
          <header class="auth-heading">
            <p class="auth-heading__eyebrow">Password reset</p>
            <h1>找回密码</h1>
            <p>输入管理员用户名或邮箱，系统会生成一次性重置链接。</p>
          </header>

          <?php if ($notice !== ''): ?><div class="flash flash--success" role="status"><?= h($notice) ?></div><?php endif; ?>
          <?php if ($error !== ''): ?><div class="flash flash--error" role="alert"><?= h($error) ?></div><?php endif; ?>

          <form class="form-stack" method="post" action="<?= h(url_for('forgot_password')) ?>">
            <?= csrf_field() ?>
            <div class="field">
              <label for="account">用户名或邮箱</label>
              <input id="account" name="account" type="text" value="<?= h((string)($form['account'] ?? '')) ?>" autocomplete="username" required autofocus>
            </div>
            <div class="action-row auth-actions">
              <button class="button" type="submit">发送重置链接</button>
            </div>
            <p class="auth-link-row"><a href="<?= h(url_for('login')) ?>">返回登录</a></p>
          </form>
        </div>
      </section>
    </div>
    <?php
    render_layout('找回密码', (string)ob_get_clean(), [
        'active' => 'login',
        'description' => '找回博客后台密码',
    ]);
}

function render_reset_password_page(string $token, string $error = ''): void
{
    ob_start();
    ?>
    <div class="auth-layout">
      <section class="panel auth-panel admin-animate admin-animate--1">
        <div class="panel__body">
          <header class="auth-heading">
            <p class="auth-heading__eyebrow">Password reset</p>
            <h1>设置新密码</h1>
            <p>新密码至少需要 8 个字符。</p>
          </header>

          <?php if ($error !== ''): ?><div class="flash flash--error" role="alert"><?= h($error) ?></div><?php endif; ?>

          <form class="form-stack" method="post" action="<?= h(url_for('reset_password')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <div class="field">
              <label for="password">新密码</label>
              <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus>
            </div>
            <div class="field">
              <label for="password_confirm">确认新密码</label>
              <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" minlength="8" required>
            </div>
            <div class="action-row auth-actions">
              <button class="button" type="submit">更新密码</button>
            </div>
          </form>
        </div>
      </section>
    </div>
    <?php
    render_layout('设置新密码', (string)ob_get_clean(), [
        'active' => 'login',
        'description' => '设置博客后台新密码',
    ]);
}

function render_admin_page(): void
{
    require_admin();

    $metrics = admin_metrics();
    $update = github_update_info();
    $commentNotifications = recent_comment_notifications();
    $sidebar = render_admin_sidebar('admin');

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>

      <div class="admin-main">
        <?= render_admin_topbar('博客数据预览') ?>

        <div class="admin-grid">
          <?php if (!empty($update['available'])): ?>
            <section class="panel update-notice admin-animate">
              <div class="panel__body">
                <div><strong>发现新版本 <?= h((string)$update['latest']) ?></strong><p>当前版本 <?= h(APP_VERSION) ?>。更新会自动备份并覆盖程序文件，站点数据和上传文件不受影响。</p></div>
                <form method="post" action="<?= h(url_for('install_update')) ?>" onsubmit="return confirm('确定更新到 <?= h((string)$update['latest']) ?> 吗？更新期间请勿关闭页面。');">
                  <?= csrf_field() ?><button class="button button--primary" type="submit">立即更新</button>
                </form>
              </div>
            </section>
          <?php endif; ?>
          <?php if ($commentNotifications): ?>
            <section class="panel admin-list-panel comment-notifications admin-animate admin-animate--2">
              <div class="panel__header">
                <div class="admin-head">
                  <div class="admin-head-left">
                    <h2>评论通知</h2>
                    <p class="panel__meta"><?= h((string)$metrics['pending_comments']) ?> 条待审核，最近未读如下。</p>
                  </div>
                  <a class="button button--secondary" href="<?= h(admin_comments_url('unread')) ?>">查看全部</a>
                </div>
              </div>
              <div class="panel__body panel__body--flush">
                <ol class="comment-notice-list">
                  <?php foreach ($commentNotifications as $notification): ?>
                    <li class="comment-notice-item is-unread">
                      <div class="comment-notice-item__body">
                        <div class="comment-notice-item__meta">
                          <strong><?= h((string)$notification['author_name']) ?></strong>
                          <?php if (trim((string)$notification['reply_to_name']) !== ''): ?><span class="comment-notice-item__reply">回复 @<?= h((string)$notification['reply_to_name']) ?></span><?php endif; ?>
                          <time datetime="<?= h(date(DATE_ATOM, (int)$notification['created_at'])) ?>"><?= h(pretty_date((int)$notification['created_at'], true)) ?></time>
                        </div>
                        <p class="comment-notice-item__excerpt"><?= h(comment_excerpt((string)$notification['content'], 140)) ?></p>
                        <a href="<?= h(content_permalink(['kind' => (string)$notification['post_kind'], 'slug' => (string)$notification['post_slug']])) ?>"><?= h((string)$notification['post_title']) ?></a>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ol>
              </div>
            </section>
          <?php endif; ?>
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
                <div class="metric-card">
                  <span class="metric-card__label">评论总数</span>
                  <strong class="metric-card__value"><?= h((string)$metrics['comments']) ?></strong>
                  <span class="metric-card__trend">包含所有审核状态</span>
                </div>
                <div class="metric-card">
                  <span class="metric-card__label">待审核评论</span>
                  <strong class="metric-card__value"><?= h((string)$metrics['pending_comments']) ?></strong>
                  <span class="metric-card__trend">需要管理员处理</span>
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

function render_sitemap(): void
{
    $now = time();
    $rows = all_rows(
        'SELECT slug, kind, updated_at, published_at FROM posts WHERE status = ? AND published_at <= ? ORDER BY updated_at DESC',
        ['published', $now]
    );
    $signature = (string)($rows[0]['updated_at'] ?? 0) . ':' . count($rows) . ':' . (string)val('SELECT COALESCE(MAX(updated_at), 0) FROM tag_meta');
    $etag = '"' . sha1($signature) . '"';
    header('Content-Type: application/xml; charset=UTF-8');
    header('Cache-Control: public, max-age=900, stale-while-revalidate=3600');
    header('ETag: ' . $etag);
    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }

    $urls = [];
    $add = static function (string $url, int $updatedAt = 0, string $priority = '0.6') use (&$urls): void {
        $urls[$url] = ['url' => absolute_url($url), 'updated_at' => $updatedAt, 'priority' => $priority];
    };
    $add(url_for('home'), $now, '1.0');
    $add(url_for('archives'), $now, '0.7');
    $add(url_for('tags'), $now, '0.7');
    $add(url_for('links'), $now, '0.5');
    foreach ($rows as $row) {
        $route = (string)$row['kind'] === 'page' ? 'page' : 'post';
        $add(url_for($route, ['slug' => (string)$row['slug']]), (int)$row['updated_at'], $route === 'post' ? '0.8' : '0.6');
    }
    foreach (tag_index_data() as $tag) {
        $add(url_for('tag', ['slug' => (string)$tag['slug']]), $now, '0.5');
    }
    foreach (all_rows('SELECT slug, updated_at FROM categories ORDER BY id') as $category) {
        $add(url_for('category', ['slug' => (string)$category['slug']]), (int)$category['updated_at'], '0.5');
    }

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $item) {
        echo "  <url>\n";
        echo '    <loc>' . x((string)$item['url']) . "</loc>\n";
        if ((int)$item['updated_at'] > 0) { echo '    <lastmod>' . gmdate('Y-m-d\TH:i:s\Z', (int)$item['updated_at']) . "</lastmod>\n"; }
        echo '    <priority>' . x((string)$item['priority']) . "</priority>\n";
        echo "  </url>\n";
    }
    echo '</urlset>';
    exit;
}

function render_links_page(): void
{
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    ob_start();
    ?>
    <article class="links-page">
      <h1 class="post-title">链接</h1>
      <p class="meta">一些值得访问的网站与朋友。</p>
      <?php if ($links): ?>
        <div class="friend-links">
          <?php foreach ($links as $link): ?>
            <?php $host = (string)(parse_url((string)$link['url'], PHP_URL_HOST) ?: $link['url']); ?>
            <a class="friend-link" href="<?= h((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer">
              <span class="friend-link__head"><?php if (trim((string)$link['icon_url']) !== ''): ?><img src="<?= h((string)$link['icon_url']) ?>" width="24" height="24" alt=""><?php endif; ?><strong><?= h((string)$link['name']) ?></strong></span>
              <?php if (trim((string)$link['description']) !== ''): ?><span><?= h((string)$link['description']) ?></span><?php endif; ?>
              <small><?= h($host) ?> ↗</small>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-notice"><p>还没有添加友情链接。</p></div>
      <?php endif; ?>
    </article>
    <?php
    render_layout('链接', (string)ob_get_clean(), [
        'active' => 'links',
        'mode' => 'public',
        'description' => '友情链接',
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
                          <?php if (!empty($post['is_pinned']) && content_kind($post) === 'post'): ?><span class="admin-pinned-badge">置顶</span><?php endif; ?>
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

function render_admin_comments_page(): void
{
    require_admin();

    $filter = trim((string)($_GET['filter'] ?? 'all'));
    $search = str_sub_u(trim((string)($_GET['q'] ?? '')), 0, 100);
    $requestedPage = max(1, (int)($_GET['p'] ?? 1));
    [$comments, $total, $page, $totalPages, $filter] = fetch_admin_comments($filter, $search, $requestedPage);
    $counts = comment_admin_counts();
    $sidebar = render_admin_sidebar('comments', [
        'title' => '评论概览',
        'stats' => [
            ['label' => '未读', 'value' => $counts['unread']],
            ['label' => '待审核', 'value' => $counts['pending']],
            ['label' => '已通过', 'value' => $counts['approved']],
        ],
    ]);
    $filters = [
        'all' => ['label' => '全部', 'count' => $counts['all']],
        'unread' => ['label' => '未读', 'count' => $counts['unread']],
        'pending' => ['label' => '待审核', 'count' => $counts['pending']],
        'approved' => ['label' => '已通过', 'count' => $counts['approved']],
        'spam' => ['label' => '垃圾', 'count' => $counts['spam']],
    ];

    ob_start();
    ?>
    <div class="admin-shell">
      <?= $sidebar ?>
      <div class="admin-main">
        <?= render_admin_topbar('评论管理') ?>

        <section class="panel admin-list-panel admin-animate admin-animate--2">
          <div class="panel__header">
            <div class="admin-head">
              <div class="admin-head-left">
                <h2>评论管理</h2>
                <p class="panel__meta">当前筛选 <?= h((string)$total) ?> 条，审核状态与未读通知独立管理。</p>
              </div>
              <?php if ($counts['unread'] > 0): ?>
                <form method="post" action="<?= h(url_for('mark_comments_read')) ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="filter" value="<?= h($filter) ?>">
                  <input type="hidden" name="q" value="<?= h($search) ?>">
                  <input type="hidden" name="p" value="<?= h((string)$page) ?>">
                  <button class="button button--secondary comment-mark-read" type="submit">全部标为已读</button>
                </form>
              <?php endif; ?>
            </div>
          </div>

          <div class="admin-comment-toolbar">
            <nav class="admin-filter-tabs" aria-label="评论筛选">
              <?php foreach ($filters as $key => $item): ?>
                <a class="admin-filter-tab<?= $filter === $key ? ' is-active' : '' ?>" href="<?= h(admin_comments_url($key, $search)) ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?>>
                  <?= h((string)$item['label']) ?><span><?= h((string)$item['count']) ?></span>
                </a>
              <?php endforeach; ?>
            </nav>
            <form class="comment-search" method="get" action="<?= h(url_for('admin_comments')) ?>">
              <?php if (!use_pretty_url()): ?><input type="hidden" name="a" value="admin_comments"><?php endif; ?>
              <?php if ($filter !== 'all'): ?><input type="hidden" name="filter" value="<?= h($filter) ?>"><?php endif; ?>
              <label class="sr-only" for="comment-search">搜索评论</label>
              <input id="comment-search" name="q" type="search" value="<?= h($search) ?>" placeholder="作者、邮箱、正文或文章">
              <button class="button button--secondary" type="submit">搜索</button>
            </form>
          </div>

          <div class="panel__body panel__body--flush">
            <?php if ($comments): ?>
              <div class="table-wrap">
                <table class="admin-table comment-table">
                  <thead>
                    <tr>
                      <th><label class="table-check"><input type="checkbox" data-check-all="comment_ids[]" aria-label="全选评论"><span class="sr-only">全选</span></label></th>
                      <th>评论者</th>
                      <th>内容与文章</th>
                      <th>状态</th>
                      <th>提交时间</th>
                      <th>操作</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($comments as $comment): ?>
                      <?php $state = comment_status_meta((string)$comment['status']); ?>
                      <?php $authorUrl = safe_link_url((string)$comment['author_url']); ?>
                      <tr class="comment-row<?= (int)$comment['is_read'] === 0 ? ' is-unread' : '' ?>">
                        <td><label class="table-check"><input type="checkbox" name="comment_ids[]" value="<?= h((string)$comment['id']) ?>" form="comments-bulk-form" aria-label="选择 <?= h((string)$comment['author_name']) ?> 的评论"><span class="sr-only">选择评论</span></label></td>
                        <td>
                          <div class="table-title">
                            <strong><?= h((string)$comment['author_name']) ?></strong>
                            <span><?= h((string)$comment['author_email']) ?></span>
                            <?php if ($authorUrl !== '#'): ?><a href="<?= h($authorUrl) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= h((string)parse_url($authorUrl, PHP_URL_HOST)) ?></a><?php endif; ?>
                          </div>
                        </td>
                        <td>
                          <div class="comment-summary">
                            <?php if (trim((string)$comment['reply_to_name']) !== ''): ?><span class="comment-summary__reply">回复 @<?= h((string)$comment['reply_to_name']) ?></span><?php endif; ?>
                            <p><?= h(comment_excerpt((string)$comment['content'])) ?></p>
                            <a href="<?= h(content_permalink(['kind' => (string)$comment['post_kind'], 'slug' => (string)$comment['post_slug']])) ?><?= (string)$comment['status'] === 'approved' && (string)$comment['post_kind'] === 'post' ? '#comment-' . h((string)$comment['id']) : '' ?>"><?= h((string)$comment['post_title']) ?></a>
                          </div>
                        </td>
                        <td>
                          <span class="status-badge status-badge--<?= h((string)$state['class']) ?>"><?= h((string)$state['label']) ?></span>
                          <?php if ((int)$comment['is_read'] === 0): ?><span class="comment-unread-dot">未读</span><?php endif; ?>
                        </td>
                        <td><time datetime="<?= h(date(DATE_ATOM, (int)$comment['created_at'])) ?>"><?= h(pretty_date((int)$comment['created_at'], true)) ?></time></td>
                        <td>
                          <form class="table-actions comment-actions" method="post" action="<?= h(url_for('moderate_comments')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="comment_id" value="<?= h((string)$comment['id']) ?>">
                            <input type="hidden" name="filter" value="<?= h($filter) ?>">
                            <input type="hidden" name="q" value="<?= h($search) ?>">
                            <input type="hidden" name="p" value="<?= h((string)$page) ?>">
                            <?php if ((string)$comment['status'] !== 'approved'): ?><button class="button button--ghost" name="action" value="approve" type="submit">通过</button><?php endif; ?>
                            <?php if ((string)$comment['status'] === 'approved'): ?><button class="button button--ghost" name="action" value="pending" type="submit">撤下</button><?php endif; ?>
                            <?php if ((string)$comment['status'] !== 'spam'): ?><button class="button button--ghost" name="action" value="spam" type="submit">垃圾</button><?php endif; ?>
                            <?php if ((int)$comment['is_read'] === 0): ?><button class="button button--ghost" name="action" value="read" type="submit">已读</button><?php endif; ?>
                            <button class="button button--danger" name="action" value="delete" type="submit" onclick="return confirm('确定永久删除这条评论吗？');">删除</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="admin-table-footer">
                <form id="comments-bulk-form" class="comment-bulk-form" method="post" action="<?= h(url_for('moderate_comments')) ?>" onsubmit="return this.elements.action.value !== 'delete' || confirm('确定永久删除选中的评论吗？');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="filter" value="<?= h($filter) ?>">
                  <input type="hidden" name="q" value="<?= h($search) ?>">
                  <input type="hidden" name="p" value="<?= h((string)$page) ?>">
                  <label for="comment-bulk-action">批量操作</label>
                  <select id="comment-bulk-action" name="action" required>
                    <option value="">请选择</option>
                    <option value="approve">通过</option>
                    <option value="pending">转待审核</option>
                    <option value="spam">标记垃圾</option>
                    <option value="read">标为已读</option>
                    <option value="delete">删除</option>
                  </select>
                  <button class="button button--secondary" type="submit">应用</button>
                </form>

                <?php if ($totalPages > 1): ?>
                  <nav class="admin-pagination" aria-label="评论分页">
                    <?php if ($page > 1): ?><a class="button button--secondary" href="<?= h(admin_comments_url($filter, $search, $page - 1)) ?>">上一页</a><?php endif; ?>
                    <span>第 <?= h((string)$page) ?> / <?= h((string)$totalPages) ?> 页</span>
                    <?php if ($page < $totalPages): ?><a class="button button--secondary" href="<?= h(admin_comments_url($filter, $search, $page + 1)) ?>">下一页</a><?php endif; ?>
                  </nav>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <div class="empty-state empty-state--inside"><p><?= $search !== '' ? '没有匹配的评论。' : '当前筛选下没有评论。' ?></p></div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </div>
    <?php
    render_layout('评论管理', (string)ob_get_clean(), [
        'active' => 'comments',
        'wide' => true,
        'description' => '博客评论管理',
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
                            <form method="post" action="<?= h(url_for('delete_category')) ?>" onsubmit="return confirm('确定删除这个空分类吗？');">
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

function render_admin_links_page(array $form = [], array $errors = []): void
{
    require_admin();
    $links = all_rows('SELECT * FROM links ORDER BY sort_order ASC, id DESC');
    $id = (int)($_GET['id'] ?? $form['id'] ?? 0);
    $editing = $id > 0 ? one('SELECT * FROM links WHERE id = ?', [$id]) : null;
    $values = array_merge([
        'id' => (string)($editing['id'] ?? ''), 'name' => (string)($editing['name'] ?? ''),
        'url' => (string)($editing['url'] ?? ''), 'icon_url' => (string)($editing['icon_url'] ?? ''), 'description' => (string)($editing['description'] ?? ''),
        'sort_order' => (string)($editing['sort_order'] ?? '0'),
    ], $form);
    $sidebar = render_admin_sidebar('links');
    ob_start();
    ?>
    <div class="admin-shell"><?= $sidebar ?><div class="admin-main">
      <?= render_admin_topbar('友情链接') ?>
      <div class="admin-grid admin-grid--split">
        <section class="panel admin-list-panel"><div class="panel__header"><h2>链接列表</h2><p class="panel__meta">排序数字越小越靠前。</p></div><div class="panel__body panel__body--flush">
          <?php if ($links): ?><div class="table-wrap"><table class="admin-table"><thead><tr><th>名称</th><th>网址</th><th>排序</th><th>操作</th></tr></thead><tbody>
          <?php foreach ($links as $link): ?><tr><td><div class="table-title"><strong><?= h((string)$link['name']) ?></strong><span><?= h((string)$link['description']) ?></span></div></td><td><a href="<?= h((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer"><?= h((string)$link['url']) ?></a></td><td><?= h((string)$link['sort_order']) ?></td><td><div class="table-actions"><a class="button button--ghost" href="<?= h(url_with_query(url_for('admin_links'), ['id' => (int)$link['id']])) ?>">编辑</a><form method="post" action="<?= h(url_for('delete_link')) ?>" onsubmit="return confirm('确定删除这个链接吗？');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($link['id']) ?>"><button class="button button--danger" type="submit">删除</button></form></div></td></tr><?php endforeach; ?>
          </tbody></table></div><?php else: ?><div class="empty-state empty-state--inside"><p>还没有友情链接。</p></div><?php endif; ?>
        </div></section>
        <section class="panel admin-list-panel"><div class="panel__header"><h2><?= $editing ? '编辑链接' : '添加链接' ?></h2></div><div class="panel__body">
          <?php if ($errors): ?><div class="flash flash--error"><?= h(implode(' ', $errors)) ?></div><?php endif; ?>
          <form class="form-stack" method="post" action="<?= h(url_for('save_link')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h((string)$values['id']) ?>">
            <div class="field"><label for="link_name">网站名称</label><input id="link_name" name="name" value="<?= h((string)$values['name']) ?>" required></div>
            <div class="field"><label for="link_url">网站地址</label><input id="link_url" name="url" type="url" value="<?= h((string)$values['url']) ?>" placeholder="https://example.com" required></div>
            <div class="field"><label for="link_icon_url">网站图标地址</label><input id="link_icon_url" name="icon_url" type="url" value="<?= h((string)$values['icon_url']) ?>" placeholder="https://example.com/favicon.ico"></div>
            <div class="field"><label for="link_description">简短描述</label><textarea id="link_description" name="description" rows="3"><?= h((string)$values['description']) ?></textarea></div>
            <div class="field"><label for="link_sort">排序</label><input id="link_sort" name="sort_order" type="number" value="<?= h((string)$values['sort_order']) ?>"></div>
            <div class="action-row"><?php if ($editing): ?><a class="button button--secondary" href="<?= h(url_for('admin_links')) ?>">取消编辑</a><?php endif; ?><button class="button" type="submit"><?= $editing ? '保存修改' : '添加链接' ?></button></div>
          </form>
        </div></section>
      </div>
    </div></div>
    <?php
    render_layout('友情链接', (string)ob_get_clean(), ['active' => 'links', 'wide' => true, 'description' => '友情链接管理']);
}

function replace_tag_everywhere(string $old, ?string $new): void
{
    foreach (all_rows('SELECT id, tags FROM posts') as $post) {
        $tags = post_tags($post);
        $changed = false;
        $result = [];
        foreach ($tags as $tag) {
            if (str_lower_u($tag) === str_lower_u($old)) {
                $changed = true;
                if ($new !== null && $new !== '') { $result[] = $new; }
            } else { $result[] = $tag; }
        }
        if ($changed) { q('UPDATE posts SET tags = ?, updated_at = ? WHERE id = ?', [encode_tags(parse_tags_input(implode(',', $result))), time(), (int)$post['id']]); }
    }
}

function render_admin_tags_page(array $form = [], array $errors = []): void
{
    require_admin();
    $tags = tag_index_data(false);
    $old = trim((string)($_GET['tag'] ?? $form['old_tag'] ?? ''));
    $currentSlug = $old !== '' ? tag_slug_for_label($old) : '';
    $sidebar = render_admin_sidebar('tags');
    ob_start(); ?>
    <div class="admin-shell"><?= $sidebar ?><div class="admin-main"><?= render_admin_topbar('标签管理') ?><div class="admin-grid admin-grid--split">
      <section class="panel admin-list-panel"><div class="panel__header"><h2>标签列表</h2><p class="panel__meta">标签来自文章内容，共 <?= h((string)count($tags)) ?> 个。</p></div><div class="panel__body panel__body--flush">
      <?php if ($tags): ?><form method="post" action="<?= h(url_for('delete_tag')) ?>" onsubmit="return confirm('确定删除选中的标签吗？文章本身不会被删除。');"><?= csrf_field() ?><div class="table-wrap"><table class="admin-table"><thead><tr><th><input type="checkbox" aria-label="全选" data-check-all="tag_ids[]"></th><th>标签</th><th>Slug</th><th>文章数</th><th>操作</th></tr></thead><tbody><?php foreach ($tags as $tag): ?><tr><td><input type="checkbox" name="tag_ids[]" value="<?= h((string)$tag['label']) ?>" aria-label="选择 <?= h((string)$tag['label']) ?>"></td><td><strong>#<?= h((string)$tag['label']) ?></strong></td><td><?= h((string)$tag['slug']) ?></td><td><?= h((string)$tag['count']) ?></td><td><a class="button button--ghost" href="<?= h(url_with_query(url_for('admin_tags'), ['tag' => (string)$tag['label']])) ?>">修改</a></td></tr><?php endforeach; ?></tbody></table></div><div class="panel__body"><button class="button button--danger" type="submit">批量删除</button></div></form><?php else: ?><div class="empty-state empty-state--inside"><p>还没有标签。</p></div><?php endif; ?>
      </div></section>
      <section class="panel admin-list-panel"><div class="panel__header"><h2>修改标签</h2></div><div class="panel__body"><?php if ($errors): ?><div class="flash flash--error"><?= h(implode(' ', $errors)) ?></div><?php endif; ?><form class="form-stack" method="post" action="<?= h(url_for('save_tag')) ?>"><?= csrf_field() ?><div class="field"><label>原标签</label><input name="old_tag" value="<?= h($old) ?>" readonly required></div><div class="field"><label>标签名称</label><input name="new_tag" value="<?= h((string)($form['new_tag'] ?? $old)) ?>" required></div><div class="field"><label>Slug</label><input name="tag_slug" value="<?= h((string)($form['tag_slug'] ?? $currentSlug)) ?>" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" required><p class="field-hint">仅使用小写字母、数字和连字符。</p></div><div class="action-row"><button class="button">保存修改</button></div></form></div></section>
    </div></div></div><?php
    render_layout('标签管理', (string)ob_get_clean(), ['active' => 'tags', 'wide' => true, 'description' => '标签管理']);
}

function render_admin_users_page(array $form = [], array $errors = []): void
{
    require_admin();
    $users = all_rows('SELECT * FROM users ORDER BY id ASC');
    $id = (int)($_GET['id'] ?? $form['id'] ?? 0);
    $editing = $id > 0 ? one('SELECT * FROM users WHERE id = ?', [$id]) : null;
    $username = (string)($form['username'] ?? $editing['username'] ?? '');
    $profile = array_merge(['nickname' => '', 'email' => '', 'avatar_url' => '', 'website_url' => '', 'social_links' => '', 'signature' => ''], $editing ?: [], $form);
    $sidebar = render_admin_sidebar('users');
    ob_start(); ?>
    <div class="admin-shell"><?= $sidebar ?><div class="admin-main"><?= render_admin_topbar('用户管理') ?><div class="admin-grid admin-grid--split">
      <section class="panel admin-list-panel"><div class="panel__header"><h2>管理员账号</h2><p class="panel__meta">系统至少保留一个管理员。</p></div><div class="panel__body panel__body--flush"><div class="table-wrap"><table class="admin-table"><thead><tr><th>用户</th><th>邮箱</th><th>创建时间</th><th>操作</th></tr></thead><tbody><?php foreach ($users as $user): ?><tr><td><div class="table-title"><strong><?= h((string)($user['nickname'] ?: $user['username'])) ?></strong><span>@<?= h((string)$user['username']) ?><?= (int)$user['id'] === (int)(current_admin()['id'] ?? 0) ? '（当前）' : '' ?></span></div></td><td><?= h((string)$user['email']) ?></td><td><?= h(pretty_date((int)$user['created_at'], true)) ?></td><td><div class="table-actions"><a class="button button--ghost" href="<?= h(url_with_query(url_for('admin_users'), ['id' => (int)$user['id']])) ?>">编辑</a><?php if ((int)$user['id'] !== (int)(current_admin()['id'] ?? 0)): ?><form method="post" action="<?= h(url_for('delete_user')) ?>" onsubmit="return confirm('确定删除这个管理员吗？');"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h($user['id']) ?>"><button class="button button--danger">删除</button></form><?php endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div></div></section>
      <section class="panel admin-list-panel"><div class="panel__header"><h2><?= $editing ? '编辑用户' : '添加用户' ?></h2></div><div class="panel__body"><?php if ($errors): ?><div class="flash flash--error"><?= h(implode(' ', $errors)) ?></div><?php endif; ?><form class="form-stack" method="post" action="<?= h(url_for('save_user')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= h((string)$id) ?>"><div class="field-grid"><div class="field"><label>用户名</label><input name="username" value="<?= h($username) ?>" required></div><div class="field"><label>昵称</label><input name="nickname" value="<?= h((string)$profile['nickname']) ?>" required></div></div><div class="field"><label>密码<?= $editing ? '（留空则不修改）' : '' ?></label><input name="password" type="password"<?= $editing ? '' : ' required' ?> minlength="8"></div><div class="field"><label>个人签名档</label><textarea name="signature" rows="3" placeholder="一句话介绍自己"><?= h((string)$profile['signature']) ?></textarea></div><div class="field"><label>邮箱地址</label><input name="email" type="email" value="<?= h((string)$profile['email']) ?>"></div><div class="field"><label>头像地址</label><input name="avatar_url" type="url" value="<?= h((string)$profile['avatar_url']) ?>" placeholder="https://example.com/avatar.jpg"></div><div class="field"><label>网站地址</label><input name="website_url" type="url" value="<?= h((string)$profile['website_url']) ?>" placeholder="https://example.com"></div><div class="field"><label>社交媒体</label><textarea name="social_links" rows="4" placeholder="每行填写一个完整链接"><?= h((string)$profile['social_links']) ?></textarea></div><div class="action-row"><?php if ($editing): ?><a class="button button--secondary" href="<?= h(url_for('admin_users')) ?>">取消编辑</a><?php endif; ?><button class="button"><?= $editing ? '保存修改' : '添加用户' ?></button></div></form></div></section>
    </div></div></div><?php
    render_layout('用户管理', (string)ob_get_clean(), ['active' => 'users', 'wide' => true, 'description' => '用户管理']);
}

function render_admin_ai_page(): void
{
    require_admin();
    $sidebar = render_admin_sidebar('ai');
    ob_start(); ?>
    <div class="admin-shell"><?= $sidebar ?><div class="admin-main"><?= render_admin_topbar('AI 设置') ?>
      <section class="panel admin-list-panel"><div class="panel__header"><h2>模型接口</h2><p class="panel__meta">兼容 OpenAI Chat Completions 格式的服务。</p></div><div class="panel__body">
        <form class="form-stack" method="post" action="<?= h(url_for('save_ai_settings')) ?>"><?= csrf_field() ?>
          <div class="field"><label for="ai_api_url">API 地址</label><input id="ai_api_url" name="ai_api_url" type="url" value="<?= h(setting('ai_api_url', 'https://api.deepseek.com')) ?>" placeholder="https://api.deepseek.com" required><p class="field-hint">可以填写服务根地址或完整的 /chat/completions 地址。</p></div>
          <div class="field"><label for="ai_api_key">API 密钥</label><input id="ai_api_key" name="ai_api_key" type="password" value="" placeholder="<?= setting('ai_api_key') !== '' ? '已保存，留空则不修改' : 'sk-...' ?>" autocomplete="new-password"><p class="field-hint">密钥仅保存在服务器 SQLite 中，不会发送到浏览器前端。</p></div>
          <div class="field"><label for="ai_model">模型名称</label><input id="ai_model" name="ai_model" value="<?= h(setting('ai_model', 'deepseek-v4-flash')) ?>" placeholder="deepseek-v4-flash" required></div>
          <div class="field"><label for="ai_slug_prompt">Slug 提示词</label><textarea id="ai_slug_prompt" name="ai_slug_prompt" rows="4" required><?= h(setting('ai_slug_prompt', default_settings()['ai_slug_prompt'])) ?></textarea></div>
          <div class="field"><label for="ai_summary_prompt">摘要提示词</label><textarea id="ai_summary_prompt" name="ai_summary_prompt" rows="4" required><?= h(setting('ai_summary_prompt', default_settings()['ai_summary_prompt'])) ?></textarea></div>
          <div class="field"><label for="ai_polish_prompt">润色提示词</label><textarea id="ai_polish_prompt" name="ai_polish_prompt" rows="4" required><?= h(setting('ai_polish_prompt', default_settings()['ai_polish_prompt'])) ?></textarea><p class="field-hint">弹窗中填写的具体要求会追加到这条系统提示词之后。</p></div>
          <div class="action-row"><button class="button">保存 AI 设置</button></div>
        </form>
      </div></section>
    </div></div><?php
    render_layout('AI 设置', (string)ob_get_clean(), ['active' => 'ai', 'wide' => true, 'description' => 'AI 模型设置']);
}

function ai_completion(string $instruction, string $content): array
{
    $baseUrl = rtrim(trim(setting('ai_api_url')), '/');
    $apiKey = trim(setting('ai_api_key'));
    $model = trim(setting('ai_model'));
    if ($baseUrl === '' || $apiKey === '' || $model === '') { return [false, '请先完成 AI 设置。']; }
    $url = str_ends_with($baseUrl, '/chat/completions') ? $baseUrl : $baseUrl . '/chat/completions';
    $endpoint = validated_ai_endpoint($url);
    if ($endpoint === null) { return [false, 'AI 地址必须使用 HTTPS 并解析到公网地址。']; }
    $payload = json_encode(['model' => $model, 'messages' => [['role' => 'system', 'content' => $instruction], ['role' => 'user', 'content' => $content]], 'temperature' => 0.3], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $curl = curl_init($url);
    $resolvedIp = str_contains($endpoint['ip'], ':') ? '[' . $endpoint['ip'] . ']' : $endpoint['ip'];
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_RESOLVE => [$endpoint['host'] . ':' . $endpoint['port'] . ':' . $resolvedIp], CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => $payload]);
    $body = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    if ($body === false) { return [false, 'AI 服务连接失败：' . $error]; }
    $data = json_decode((string)$body, true);
    $result = trim((string)($data['choices'][0]['message']['content'] ?? ''));
    if ($status < 200 || $status >= 300 || $result === '') { return [false, (string)($data['error']['message'] ?? 'AI 服务返回异常（HTTP ' . $status . '）。')]; }
    return [true, $result];
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
              <div class="field"><label for="site_name">站点名称</label><input id="site_name" name="site_name" type="text" value="<?= h(setting('site_name')) ?>" required></div>
              <div class="field"><label for="site_tagline">首页副标题</label><input id="site_tagline" name="site_tagline" type="text" value="<?= h(setting('site_tagline')) ?>"></div>
              <div class="field"><label for="site_description">站点描述</label><textarea id="site_description" name="site_description" rows="3"><?= h(setting('site_description')) ?></textarea></div>
              <div class="field"><label for="site_keywords">站点关键字</label><input id="site_keywords" name="site_keywords" value="<?= h(setting('site_keywords')) ?>" placeholder="PHP, SQLite, 博客"><p class="field-hint">使用英文逗号分隔，页面将输出为 SEO keywords 元信息。</p></div>
              <div class="field">
                <label for="site_url">站点地址</label>
                <input id="site_url" name="site_url" type="url" value="<?= h(setting('site_url')) ?>" placeholder="https://example.com/blog">
                <p class="field-hint">RSS 会优先使用这里的绝对地址，子目录部署时请带上完整路径。</p>
              </div>
              <div class="field"><label for="favicon_url">Favicon 地址</label><input id="favicon_url" name="favicon_url" value="<?= h(setting('favicon_url', 'logo.png')) ?>" placeholder="logo.png"><p class="field-hint">默认使用项目根目录的 logo.png，也可以填写完整图片 URL 或站内绝对路径。</p></div>
              <div class="field">
                <label for="footer_beian">备案号</label>
                <input id="footer_beian" name="footer_beian" type="text" value="<?= h(setting('footer_beian')) ?>" placeholder="京 ICP 备 12345678 号">
              </div>
              <div class="field">
                <label for="posts_per_page">首页每页文章数</label>
                <input id="posts_per_page" name="posts_per_page" type="number" min="1" max="24" value="<?= h(setting('posts_per_page', '6')) ?>">
              </div>
              <fieldset class="field settings-field">
                <legend>评论设置</legend>
                <div class="settings-option-list">
                  <label class="setting-option"><input id="comments_enabled" name="comments_enabled" type="checkbox" value="1"<?= setting('comments_enabled', '1') === '1' ? ' checked' : '' ?>><span>允许访客提交评论</span></label>
                  <label class="setting-option"><input name="comments_require_approval" type="checkbox" value="1"<?= setting('comments_require_approval', '1') === '1' ? ' checked' : '' ?>><span>访客首次留言需审核后展示（按邮箱判断）</span></label>
                  <label class="setting-option"><input name="comments_notify" type="checkbox" value="1"<?= setting('comments_notify', '1') === '1' ? ' checked' : '' ?>><span>新评论显示后台提醒</span></label>
                </div>
              </fieldset>
              <div class="field">
                  <label for="pretty_url">伪静态 URL</label>
                  <select id="pretty_url" name="pretty_url">
                    <option value="0"<?= setting('pretty_url', '0') === '0' ? ' selected' : '' ?>>关闭</option>
                    <option value="1"<?= setting('pretty_url', '0') === '1' ? ' selected' : '' ?>>开启</option>
                  </select>
                  <p class="field-hint">开启后文章链接会变成 `/archive/slug`，需要服务器 rewrite 支持。</p>
                  <div class="rewrite-help" data-rewrite-help<?= setting('pretty_url', '0') === '1' ? '' : ' hidden' ?>>
                    <strong>Apache</strong>
                    <p>启用 <code>mod_rewrite</code>，并为当前目录设置 <code>AllowOverride All</code>。项目根目录已有可直接使用的 <code>.htaccess</code>。</p>
                    <strong>Nginx</strong>
                    <pre><code>location ^~ /data/ { deny all; }
location ^~ /cache/ { deny all; }

location / {
    try_files $uri $uri/ /index.php?$query_string;
}</code></pre>
                    <p>若博客安装在子目录，请把 <code>/index.php</code> 改为包含子目录的入口路径，例如 <code>/blog/index.php</code>。</p>
                  </div>
              </div>
              <div class="field">
                <label for="site_footer">页脚文案</label>
                <input id="site_footer" name="site_footer" type="text" value="<?= h(setting('site_footer')) ?>" placeholder="支持 {year} 占位符">
              </div>
              <div class="field">
                <label for="custom_head_code">Head 自定义代码</label>
                <textarea id="custom_head_code" name="custom_head_code" rows="10" spellcheck="false" placeholder="&lt;script&gt;...&lt;/script&gt;"><?= h(setting('custom_head_code')) ?></textarea>
                <p class="field-hint">原样插入前台页面的 &lt;/head&gt; 前，可用于统计脚本、meta 或 style；请仅使用可信代码。</p>
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

    $categories = category_options();
    $defaultCategoryId = $categories ? (string)$categories[0]['id'] : '';
    $defaults = [
        'kind' => (string)($existing['kind'] ?? 'post'),
        'category_id' => (string)($existing['category_id'] ?? $defaultCategoryId),
        'title' => (string)($existing['title'] ?? ''),
        'slug' => (string)($existing['slug'] ?? ''),
        'tags_input' => implode(', ', post_tags($existing ?? [])),
        'excerpt' => (string)($existing['excerpt'] ?? ''),
        'content' => (string)($existing['content'] ?? ''),
        'status' => (string)($existing['status'] ?? 'draft'),
        'published_at' => $existing ? datetime_local_value((int)($existing['published_at'] ?: time())) : datetime_local_value(time()),
        'is_pinned' => (string)(int)($existing['is_pinned'] ?? 0),
    ];

    $values = array_merge($defaults, $form);
    $isEdit = $existing !== null;
    $siteName = setting('site_name', default_settings()['site_name']);
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
                  <div class="field-label-row"><label for="slug">Slug</label><button class="button button--ghost button--compact" type="button" data-ai-action="slug">AI 生成</button></div>
                  <input id="slug" name="slug" type="text" value="<?= h((string)$values['slug']) ?>" placeholder="留空将自动生成">
                </div>
                <div class="field">
                  <label for="category_id">分类</label>
                  <select id="category_id" name="category_id" required>
                    <option value="" disabled>请选择分类</option>
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
                <div class="field-label-row"><label for="excerpt">摘要</label><button class="button button--ghost button--compact" type="button" data-ai-action="summary">AI 摘要</button></div>
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

              <label class="pin-option" for="is_pinned">
                <input id="is_pinned" name="is_pinned" type="checkbox" value="1"<?= (string)$values['is_pinned'] === '1' ? ' checked' : '' ?>>
                <span><strong>置顶文章</strong><small>发布后优先显示在前端文章列表顶部，仅对文章生效。</small></span>
              </label>

              <div class="field">
                <div class="field-label-row"><label for="content">正文</label><button class="button button--ghost button--compact" type="button" data-ai-action="polish">AI 润色</button></div>
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
            <div class="ai-editor" data-ai-editor data-url="<?= h(url_for('ai_generate')) ?>" data-csrf="<?= h(csrf_token()) ?>">
              <div class="ai-modal" data-ai-modal hidden role="dialog" aria-modal="true" aria-labelledby="ai-modal-title">
                <div class="ai-modal__backdrop" data-ai-close></div>
                <div class="ai-modal__panel">
                  <div class="ai-modal__header"><h2 id="ai-modal-title">AI 润色正文</h2><button class="button button--ghost button--compact" type="button" data-ai-close aria-label="关闭">关闭</button></div>
                  <div class="field"><label for="ai_instruction">润色或生成要求</label><textarea id="ai_instruction" rows="5" placeholder="例如：修正语病，保持 Markdown 格式；补充一段实际使用示例；将内容改得更简洁。"></textarea></div>
                  <p class="field-hint" data-ai-status></p>
                  <div class="action-row"><button class="button button--secondary" type="button" data-ai-close>取消</button><button class="button" type="button" data-ai-confirm>确定并填入正文</button></div>
                </div>
              </div>
            </div>
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

if (!is_installed()) {
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

    case 'sitemap':
        render_sitemap();
        break;

    case 'archives':
        render_archives();
        break;

    case 'tags':
        render_tags_index();
        break;

    case 'links':
        render_links_page();
        break;

    case 'tag':
        render_tag_page(trim((string)($_GET['slug'] ?? '')));
        break;

    case 'category':
        render_category_page(trim((string)($_GET['slug'] ?? '')));
        break;

    case 'submit_comment':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect_to(url_for('home'));
        }
        verify_csrf();
        $postId = (int)($_POST['post_id'] ?? 0);
        $post = one(
            'SELECT * FROM posts WHERE id = ? AND kind = ? AND status = ? AND published_at > 0 AND published_at <= ?',
            [$postId, 'post', 'published', time()]
        );
        if (!$post) {
            simple_error_page('文章不存在', '这篇文章当前无法接收评论。', 404);
        }
        $returnUrl = content_permalink($post) . '#comments';
        if (setting('comments_enabled', '1') !== '1') {
            set_comment_notice($postId, 'error', '评论功能当前已关闭。');
            redirect_to($returnUrl);
        }
        $authenticatedIdentity = authenticated_comment_identity();
        $commentInput = $_POST;
        if ($authenticatedIdentity !== null) {
            $commentInput = array_merge($commentInput, $authenticatedIdentity);
        }
        $startedAt = (int)($_POST['comment_started_at'] ?? 0);
        if (!record_comment_attempt()) {
            [$comment] = validate_comment_input($commentInput, $authenticatedIdentity === null);
            forget_comment_form($postId, $startedAt);
            set_comment_feedback($postId, $comment, ['提交过于频繁，请稍后再试。']);
            redirect_to($returnUrl);
        }
        if (trim((string)($_POST['company'] ?? '')) !== '') {
            forget_comment_form($postId, $startedAt);
            set_comment_notice($postId, 'success', '评论已提交，审核通过后会显示。');
            redirect_to($returnUrl);
        }

        [$comment, $commentErrors] = validate_comment_input($commentInput, $authenticatedIdentity === null);
        $parentId = (int)$comment['parent_id'];
        $replyTarget = approved_reply_target($postId, $parentId);
        if ($parentId > 0 && $replyTarget === null) {
            $commentErrors[] = '回复目标不存在或当前不可用。';
        }
        $formExists = !empty($_SESSION['comment_forms'][$postId][(string)$startedAt]);
        $elapsed = time() - $startedAt;
        if ($startedAt < 1 || !$formExists || $elapsed < 2 || $elapsed > 7200) {
            $commentErrors[] = $elapsed < 2 ? '提交过快，请稍后再试。' : '评论表单已失效，请刷新文章后重试。';
        }
        forget_comment_form($postId, $startedAt);
        if ($commentErrors) {
            set_comment_feedback($postId, $comment, array_values(array_unique($commentErrors)));
            redirect_to($returnUrl);
        }

        $linkCount = preg_match_all('#https?://#i', $comment['content']);
        $isAuthenticatedAdmin = $authenticatedIdentity !== null;
        $hasApprovedVisitorEmail = !$isAuthenticatedAdmin && visitor_email_has_approved_comment($comment['author_email']);
        $needsApproval = !$isAuthenticatedAdmin
            && !$hasApprovedVisitorEmail
            && (setting('comments_require_approval', '1') === '1' || $linkCount > 2);
        $status = $needsApproval ? 'pending' : 'approved';
        $isRead = setting('comments_notify', '1') === '1' ? 0 : 1;
        $now = time();
        $userId = (int)($authenticatedIdentity['user_id'] ?? 0);
        $insertParams = [
            $postId,
            $userId > 0 ? $userId : null,
            $comment['author_name'],
            $comment['author_email'],
            $comment['author_url'],
            $comment['content'],
            $status,
            $isRead,
            comment_ip_hash(),
            str_sub_u((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            $now,
            $now,
        ];
        $database = db();
        $duplicateCutoff = time() - 86400;
        $duplicateIdentitySql = $userId > 0 ? 'duplicate.user_id = ?' : 'duplicate.user_id IS NULL AND duplicate.author_email = ?';
        $duplicateIdentityValue = $userId > 0 ? $userId : $comment['author_email'];
        try {
            $database->exec('BEGIN IMMEDIATE');
            $duplicateError = duplicate_comment_error($postId, $parentId, $userId, $comment['author_email'], $comment['content']);
            if ($duplicateError !== '') {
                $database->rollBack();
                set_comment_feedback($postId, $comment, [$duplicateError]);
                redirect_to($returnUrl);
            }

            if ($parentId > 0) {
                $inserted = q(
                    "INSERT INTO comments(post_id, user_id, parent_id, reply_to_name, author_name, author_email, author_url, content, status, is_read, ip_hash, user_agent, created_at, updated_at)
                     SELECT ?, ?, parent.id, parent.author_name, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                     FROM comments parent
                     WHERE parent.id = ? AND parent.post_id = ? AND parent.status = 'approved'
                       AND NOT EXISTS (
                           SELECT 1 FROM comments duplicate
                           WHERE duplicate.post_id = ? AND COALESCE(duplicate.parent_id, 0) = parent.id
                             AND {$duplicateIdentitySql} AND duplicate.content = ? AND duplicate.created_at >= ?
                       )",
                    array_merge($insertParams, [$parentId, $postId, $postId, $duplicateIdentityValue, $comment['content'], $duplicateCutoff])
                )->rowCount();
                if ($inserted !== 1) {
                    $targetStillAvailable = approved_reply_target($postId, $parentId) !== null;
                    $database->rollBack();
                    if ($targetStillAvailable) {
                        $failureMessage = '这条评论已经提交过了。';
                    } else {
                        $comment['parent_id'] = '';
                        $failureMessage = '回复目标已不可用，请重新选择。';
                    }
                    set_comment_feedback($postId, $comment, [$failureMessage]);
                    redirect_to($returnUrl);
                }
            } else {
                $inserted = q(
                    "INSERT INTO comments(post_id, user_id, parent_id, reply_to_name, author_name, author_email, author_url, content, status, is_read, ip_hash, user_agent, created_at, updated_at)
                     SELECT ?, ?, NULL, '', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                     WHERE NOT EXISTS (
                         SELECT 1 FROM comments duplicate
                         WHERE duplicate.post_id = ? AND COALESCE(duplicate.parent_id, 0) = 0
                           AND {$duplicateIdentitySql} AND duplicate.content = ? AND duplicate.created_at >= ?
                     )",
                    array_merge($insertParams, [$postId, $duplicateIdentityValue, $comment['content'], $duplicateCutoff])
                )->rowCount();
                if ($inserted !== 1) {
                    $database->rollBack();
                    set_comment_feedback($postId, $comment, ['这条评论已经提交过了。']);
                    redirect_to($returnUrl);
                }
            }
            $database->commit();
        } catch (Throwable $exception) {
            if ($database->inTransaction()) {
                $database->rollBack();
            }
            throw $exception;
        }
        if ($authenticatedIdentity === null) {
            $_SESSION['comment_identity'] = [
                'author_name' => $comment['author_name'],
                'author_email' => $comment['author_email'],
                'author_url' => $comment['author_url'],
            ];
        }
        set_comment_notice($postId, 'success', $status === 'approved' ? '评论已发布。' : '评论已提交，审核通过后会显示。');
        redirect_to($returnUrl);
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

    case 'forgot_password':
        if (is_admin()) {
            redirect_to(url_for('admin'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $rate = password_reset_rate_state();
            if ((int)$rate['count'] >= 3) {
                render_forgot_password_page('', '重置请求过于频繁，请 15 分钟后再试。');
            }
            password_reset_rate_state(true);

            $account = trim((string)($_POST['account'] ?? ''));
            if ($account === '') {
                render_forgot_password_page('', '请填写用户名或邮箱。', ['account' => $account]);
            }

            $user = one(
                'SELECT * FROM users WHERE username = ? OR lower(email) = ? LIMIT 1',
                [$account, str_lower_u($account)]
            );

            if ($user) {
                [$token, $expiresAt] = create_password_reset($user);
                send_password_reset_notice($user, $token, $expiresAt);
            }

            render_forgot_password_page('如果账号存在，重置链接已经生成。请检查管理员邮箱；若服务器未配置发信，请查看 cache 目录中的 password-reset 文件。');
        }

        render_forgot_password_page();
        break;

    case 'reset_password':
        if (is_admin()) {
            redirect_to(url_for('admin'));
        }

        $token = trim((string)($_POST['token'] ?? $_GET['token'] ?? ''));
        $reset = password_reset_by_token($token);
        if (!$reset) {
            render_forgot_password_page('', '重置链接无效或已过期，请重新申请。');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $password = (string)($_POST['password'] ?? '');
            $confirm = (string)($_POST['password_confirm'] ?? '');

            if (strlen($password) < 8) {
                render_reset_password_page($token, '新密码至少需要 8 个字符。');
            }
            if ($password !== $confirm) {
                render_reset_password_page($token, '两次输入的密码不一致。');
            }

            $now = time();
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]);
            q('UPDATE password_resets SET used_at = ? WHERE id = ?', [$now, (int)$reset['id']]);
            q('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at = 0', [$now, (int)$reset['user_id']]);
            set_flash('success', '密码已更新，请使用新密码登录。');
            redirect_to(url_for('login'));
        }

        render_reset_password_page($token);
        break;

    case 'login':
        if (is_admin()) {
            redirect_to(url_for('admin'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $rate = login_rate_state();
            if ((int)$rate['count'] >= 5) {
                render_login_page('登录尝试过多，请 15 分钟后再试。');
            }
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $user = one('SELECT * FROM users WHERE username = ?', [$username]);

            if (!$user || !password_verify($password, (string)$user['password_hash'])) {
                login_rate_state(true);
                render_login_page('用户名或密码不正确。', ['username' => $username]);
            }

            login_rate_state(false, true);
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

    case 'install_update':
        require_admin_post(url_for('admin'));
        try {
            $version = install_github_update(github_update_info(true));
            set_flash('success', '已更新到 ' . $version . '。如版本包含数据库变更，请继续访问 update.php。');
        } catch (Throwable $exception) {
            set_flash('error', '更新失败：' . $exception->getMessage());
        }
        redirect_to(url_for('admin'));
        break;

    case 'admin_posts':
        render_admin_posts_page();
        break;

    case 'admin_comments':
        render_admin_comments_page();
        break;

    case 'admin_categories':
        render_admin_categories_page();
        break;

    case 'admin_tags':
        render_admin_tags_page();
        break;

    case 'admin_links':
        render_admin_links_page();
        break;

    case 'admin_users':
        render_admin_users_page();
        break;

    case 'admin_ai':
        render_admin_ai_page();
        break;

    case 'admin_settings':
        render_admin_settings_page();
        break;

    case 'save_ai_settings':
        require_admin_post(url_for('admin_ai'));
        $apiUrl = rtrim(trim((string)($_POST['ai_api_url'] ?? '')), '/');
        $apiKey = trim((string)($_POST['ai_api_key'] ?? ''));
        $model = trim((string)($_POST['ai_model'] ?? ''));
        $slugPrompt = trim((string)($_POST['ai_slug_prompt'] ?? ''));
        $summaryPrompt = trim((string)($_POST['ai_summary_prompt'] ?? ''));
        $polishPrompt = trim((string)($_POST['ai_polish_prompt'] ?? ''));
        if (validated_ai_endpoint($apiUrl) === null || $model === '' || $slugPrompt === '' || $summaryPrompt === '' || $polishPrompt === '') {
            set_flash('error', 'API 地址必须使用 HTTPS 并解析到公网地址，同时请填写模型名称和提示词。');
            redirect_to(url_for('admin_ai'));
        }
        $values = ['ai_api_url' => $apiUrl, 'ai_model' => $model, 'ai_slug_prompt' => $slugPrompt, 'ai_summary_prompt' => $summaryPrompt, 'ai_polish_prompt' => $polishPrompt];
        if ($apiKey !== '') { $values['ai_api_key'] = $apiKey; }
        save_settings($values);
        set_flash('success', 'AI 设置已保存。');
        redirect_to(url_for('admin_ai'));
        break;

    case 'ai_generate':
        require_admin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['ok' => false, 'error' => '仅支持 POST 请求。'], 405); }
        verify_csrf();
        $type = (string)($_POST['type'] ?? '');
        $content = trim((string)($_POST['content'] ?? ''));
        $instruction = trim((string)($_POST['instruction'] ?? ''));
        if (str_len_u($content) > 50000) { json_response(['ok' => false, 'error' => '内容过长，请控制在 50000 字以内。'], 422); }
        if ($type === 'slug') {
            if ($content === '') { json_response(['ok' => false, 'error' => '请先填写文章标题。'], 422); }
            [$ok, $result] = ai_completion(setting('ai_slug_prompt', default_settings()['ai_slug_prompt']), $content);
            if ($ok) { $result = trim((string)preg_replace('/[^a-z0-9]+/', '-', str_lower_u($result)), '-'); $result = substr($result, 0, 100); }
        } elseif ($type === 'summary') {
            if ($content === '') { json_response(['ok' => false, 'error' => '请先填写文章正文。'], 422); }
            [$ok, $result] = ai_completion(setting('ai_summary_prompt', default_settings()['ai_summary_prompt']), $content);
            if ($ok) { $result = str_sub_u($result, 0, 100); }
        } elseif ($type === 'polish') {
            if ($instruction === '') { json_response(['ok' => false, 'error' => '请填写润色或生成要求。'], 422); }
            [$ok, $result] = ai_completion(setting('ai_polish_prompt', default_settings()['ai_polish_prompt']) . ' 用户要求：' . $instruction, $content !== '' ? $content : '请根据要求生成正文。');
        } else { json_response(['ok' => false, 'error' => '未知的 AI 操作。'], 422); }
        if (!$ok) { json_response(['ok' => false, 'error' => $result], 502); }
        json_response(['ok' => true, 'result' => $result]);
        break;

    case 'save_settings':
        require_admin_post(url_for('admin_settings'));
        $siteName = trim((string)($_POST['site_name'] ?? ''));
        $postsPerPage = max(1, min(24, (int)($_POST['posts_per_page'] ?? (int)default_settings()['posts_per_page'])));
        $prettyUrl = (string)($_POST['pretty_url'] ?? '0') === '1' ? '1' : '0';
        save_settings([
            'site_name' => $siteName !== '' ? $siteName : default_settings()['site_name'],
            'site_url' => trim((string)($_POST['site_url'] ?? '')),
            'favicon_url' => trim((string)($_POST['favicon_url'] ?? '')) ?: default_settings()['favicon_url'],
            'footer_beian' => trim((string)($_POST['footer_beian'] ?? '')),
            'posts_per_page' => (string)$postsPerPage,
            'pretty_url' => $prettyUrl,
            'comments_enabled' => isset($_POST['comments_enabled']) ? '1' : '0',
            'comments_require_approval' => isset($_POST['comments_require_approval']) ? '1' : '0',
            'comments_notify' => isset($_POST['comments_notify']) ? '1' : '0',
            'site_tagline' => trim((string)($_POST['site_tagline'] ?? '')),
            'site_description' => trim((string)($_POST['site_description'] ?? '')),
            'site_keywords' => trim((string)($_POST['site_keywords'] ?? '')),
            'site_footer' => trim((string)($_POST['site_footer'] ?? '')),
            'custom_head_code' => trim((string)($_POST['custom_head_code'] ?? '')),
        ]);
        set_flash('success', '站点设置已更新。');
        redirect_to(url_for('admin_settings'));
        break;

    case 'mark_comments_read':
        require_admin_post(url_for('admin_comments'));
        $filter = trim((string)($_POST['filter'] ?? 'all'));
        $search = trim((string)($_POST['q'] ?? ''));
        $page = max(1, (int)($_POST['p'] ?? 1));
        $updated = q('UPDATE comments SET is_read = 1, updated_at = ? WHERE is_read = 0', [time()])->rowCount();
        set_flash('success', $updated > 0 ? '所有评论通知已标为已读。' : '当前没有未读评论。');
        redirect_to(admin_comments_url($filter, $search, $page));
        break;

    case 'moderate_comments':
        require_admin_post(url_for('admin_comments'));
        $filter = trim((string)($_POST['filter'] ?? 'all'));
        $search = trim((string)($_POST['q'] ?? ''));
        $page = max(1, (int)($_POST['p'] ?? 1));
        $returnUrl = admin_comments_url($filter, $search, $page);
        $action = trim((string)($_POST['action'] ?? ''));
        $ids = $_POST['comment_ids'] ?? [];
        $singleId = (int)($_POST['comment_id'] ?? 0);
        if ($singleId > 0) { $ids = [$singleId]; }
        $ids = positive_int_ids($ids);
        if (!in_array($action, ['approve', 'pending', 'spam', 'read', 'delete'], true) || $ids === []) {
            set_flash('error', $ids === [] ? '请先选择评论。' : '未知的评论操作。');
            redirect_to($returnUrl);
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($action === 'delete') {
            $affected = q("DELETE FROM comments WHERE id IN ({$placeholders})", $ids)->rowCount();
            $message = '已删除 ' . $affected . ' 条评论。';
        } elseif ($action === 'read') {
            $params = array_merge([time()], $ids);
            $affected = q("UPDATE comments SET is_read = 1, updated_at = ? WHERE id IN ({$placeholders})", $params)->rowCount();
            $message = '已将 ' . $affected . ' 条评论标为已读。';
        } else {
            $status = ['approve' => 'approved', 'pending' => 'pending', 'spam' => 'spam'][$action];
            $params = array_merge([$status, time()], $ids);
            $affected = q("UPDATE comments SET status = ?, is_read = 1, updated_at = ? WHERE id IN ({$placeholders})", $params)->rowCount();
            $message = match ($status) {
                'approved' => '已通过 ' . $affected . ' 条评论。',
                'spam' => '已将 ' . $affected . ' 条评论标记为垃圾。',
                default => '已将 ' . $affected . ' 条评论转为待审核。',
            };
        }
        set_flash('success', $message);
        redirect_to($returnUrl);
        break;

    case 'save_category':
        require_admin_post(url_for('admin_categories'));
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
        require_admin_post(url_for('admin_categories'));
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $postCount = (int)val('SELECT COUNT(*) FROM posts WHERE kind = ? AND category_id = ?', ['post', $id]);
            if ($postCount > 0) {
                set_flash('error', '该分类下仍有文章，请先将文章移动到其他分类。');
                redirect_to(url_for('admin_categories'));
            }
            q('DELETE FROM categories WHERE id = ?', [$id]);
        }
        set_flash('success', '分类已删除。');
        redirect_to(url_for('admin_categories'));
        break;

    case 'save_link':
        require_admin_post(url_for('admin_links'));
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $url = trim((string)($_POST['url'] ?? ''));
        $iconUrl = trim((string)($_POST['icon_url'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $errors = [];
        if ($name === '') { $errors[] = '请填写网站名称。'; }
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(str_lower_u((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) { $errors[] = '请填写有效的 HTTP 或 HTTPS 地址。'; }
        if ($iconUrl !== '' && !filter_var($iconUrl, FILTER_VALIDATE_URL)) { $errors[] = '网站图标地址格式不正确。'; }
        if ($errors) { render_admin_links_page(['id' => (string)$id, 'name' => $name, 'url' => $url, 'icon_url' => $iconUrl, 'description' => $description, 'sort_order' => (string)$sortOrder], $errors); }
        if ($id > 0 && one('SELECT id FROM links WHERE id = ?', [$id])) {
            q('UPDATE links SET name = ?, url = ?, icon_url = ?, description = ?, sort_order = ?, updated_at = ? WHERE id = ?', [$name, $url, $iconUrl, $description, $sortOrder, time(), $id]);
            set_flash('success', '链接已更新。');
        } else {
            $now = time();
            q('INSERT INTO links(name, url, icon_url, description, sort_order, created_at, updated_at) VALUES(?,?,?,?,?,?,?)', [$name, $url, $iconUrl, $description, $sortOrder, $now, $now]);
            set_flash('success', '链接已添加。');
        }
        redirect_to(url_for('admin_links'));
        break;

    case 'save_tag':
        require_admin_post(url_for('admin_tags'));
        $oldTag = trim((string)($_POST['old_tag'] ?? ''));
        $newTag = trim((string)($_POST['new_tag'] ?? ''));
        $tagSlug = trim((string)($_POST['tag_slug'] ?? ''));
        $errors = [];
        if ($oldTag === '' || $newTag === '') { $errors[] = '原标签和新标签不能为空。'; }
        if (count(parse_tags_input($newTag)) !== 1) { $errors[] = '新标签不能包含逗号。'; }
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $tagSlug)) { $errors[] = 'Slug 格式不正确。'; }
        if (one('SELECT label FROM tag_meta WHERE slug = ? AND label != ?', [$tagSlug, $oldTag])) { $errors[] = 'Slug 已被其他标签使用。'; }
        if ($errors) { render_admin_tags_page(['old_tag' => $oldTag, 'new_tag' => $newTag, 'tag_slug' => $tagSlug], $errors); }
        if (str_lower_u($oldTag) !== str_lower_u($newTag)) { replace_tag_everywhere($oldTag, $newTag); }
        q('DELETE FROM tag_meta WHERE label = ?', [$oldTag]);
        q('INSERT OR REPLACE INTO tag_meta(label, slug, updated_at) VALUES(?,?,?)', [$newTag, $tagSlug, time()]);
        set_flash('success', '标签名称和 Slug 已更新。');
        redirect_to(url_for('admin_tags'));
        break;

    case 'delete_tag':
        require_admin_post(url_for('admin_tags'));
        $selected = $_POST['tag_ids'] ?? [];
        if (!is_array($selected)) { $selected = []; }
        $selected = array_values(array_unique(array_filter(array_map(static fn($tag): string => trim((string)$tag), $selected))));
        foreach ($selected as $tag) {
            replace_tag_everywhere($tag, null);
            q('DELETE FROM tag_meta WHERE label = ?', [$tag]);
        }
        set_flash('success', $selected ? '所选标签已移除，文章内容保持不变。' : '请先选择需要删除的标签。');
        redirect_to(url_for('admin_tags'));
        break;

    case 'delete_link':
        require_admin_post(url_for('admin_links'));
        q('DELETE FROM links WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        set_flash('success', '链接已删除。');
        redirect_to(url_for('admin_links'));
        break;

    case 'save_user':
        require_admin_post(url_for('admin_users'));
        $id = (int)($_POST['id'] ?? 0);
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $nickname = trim((string)($_POST['nickname'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $avatarUrl = trim((string)($_POST['avatar_url'] ?? ''));
        $websiteUrl = trim((string)($_POST['website_url'] ?? ''));
        $socialLinks = trim((string)($_POST['social_links'] ?? ''));
        $signature = trim((string)($_POST['signature'] ?? ''));
        $errors = [];
        if ($username === '') { $errors[] = '用户名不能为空。'; }
        if ($nickname === '') { $errors[] = '昵称不能为空。'; }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = '邮箱地址格式不正确。'; }
        foreach (['头像地址' => $avatarUrl, '网站地址' => $websiteUrl] as $label => $url) { if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) { $errors[] = $label . '格式不正确。'; } }
        foreach (preg_split('/\R+/', $socialLinks) ?: [] as $url) { if (trim($url) !== '' && !filter_var(trim($url), FILTER_VALIDATE_URL)) { $errors[] = '社交媒体链接必须是完整网址。'; break; } }
        if (one('SELECT id FROM users WHERE username = ? AND id != ?', [$username, $id])) { $errors[] = '用户名已存在。'; }
        if ($id < 1 && strlen($password) < 8) { $errors[] = '密码至少需要 8 个字符。'; }
        if ($id > 0 && $password !== '' && strlen($password) < 8) { $errors[] = '新密码至少需要 8 个字符。'; }
        $profileForm = ['id' => (string)$id, 'username' => $username, 'nickname' => $nickname, 'email' => $email, 'avatar_url' => $avatarUrl, 'website_url' => $websiteUrl, 'social_links' => $socialLinks, 'signature' => $signature];
        if ($errors) { render_admin_users_page($profileForm, $errors); }
        if ($id > 0 && one('SELECT id FROM users WHERE id = ?', [$id])) {
            if ($password !== '') { q('UPDATE users SET username = ?, password_hash = ?, nickname = ?, email = ?, avatar_url = ?, website_url = ?, social_links = ?, signature = ? WHERE id = ?', [$username, password_hash($password, PASSWORD_DEFAULT), $nickname, $email, $avatarUrl, $websiteUrl, $socialLinks, $signature, $id]); }
            else { q('UPDATE users SET username = ?, nickname = ?, email = ?, avatar_url = ?, website_url = ?, social_links = ?, signature = ? WHERE id = ?', [$username, $nickname, $email, $avatarUrl, $websiteUrl, $socialLinks, $signature, $id]); }
            set_flash('success', '用户已更新。');
        } else {
            q('INSERT INTO users(username, password_hash, nickname, email, avatar_url, website_url, social_links, signature, created_at) VALUES(?,?,?,?,?,?,?,?,?)', [$username, password_hash($password, PASSWORD_DEFAULT), $nickname, $email, $avatarUrl, $websiteUrl, $socialLinks, $signature, time()]);
            set_flash('success', '用户已添加。');
        }
        redirect_to(url_for('admin_users'));
        break;

    case 'delete_user':
        require_admin_post(url_for('admin_users'));
        $id = (int)($_POST['id'] ?? 0);
        if ($id === (int)(current_admin()['id'] ?? 0)) { set_flash('error', '不能删除当前登录账号。'); }
        elseif ((int)val('SELECT COUNT(*) FROM users') <= 1) { set_flash('error', '系统必须保留至少一个管理员。'); }
        else { q('UPDATE posts SET author_id = ? WHERE author_id = ?', [(int)(current_admin()['id'] ?? 0), $id]); q('DELETE FROM users WHERE id = ?', [$id]); set_flash('success', '用户已删除，其文章已转移给当前管理员。'); }
        redirect_to(url_for('admin_users'));
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
                    'INSERT INTO posts(author_id, kind, category_id, slug, title, tags, excerpt, content, status, published_at, is_pinned, created_at, updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [
                        (int)(current_admin()['id'] ?? 0),
                        $data['kind'],
                        $data['category_id'],
                        $data['slug'],
                        $data['title'],
                        $data['tags'],
                        $data['excerpt'],
                        $data['content'],
                        $data['status'],
                        $data['published_at'],
                        $data['is_pinned'],
                        $now,
                        $now,
                    ]
                );
                $id = (int)db()->lastInsertId();
                set_flash('success', '文章已创建。');
                redirect_to(url_for('edit', ['id' => $id]));
            }
            render_editor_page(null, post_form_from_request($_POST), $errors);
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
                    'UPDATE posts SET kind = ?, category_id = ?, slug = ?, title = ?, tags = ?, excerpt = ?, content = ?, status = ?, published_at = ?, is_pinned = ?, updated_at = ? WHERE id = ?',
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
                        $data['is_pinned'],
                        time(),
                        $id,
                    ]
                );
                set_flash('success', '文章已保存。');
                redirect_to(url_for('edit', ['id' => $id]));
            }
            render_editor_page($post, post_form_from_request($_POST), $errors);
        }
        render_editor_page($post);
        break;

    case 'change_status':
        require_admin_post(url_for('admin_posts'));
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
        require_admin_post(url_for('admin_posts'));
        $id = (int)($_POST['id'] ?? 0);
        q('DELETE FROM posts WHERE id = ?', [$id]);
        set_flash('success', '文章已删除。');
        redirect_to(url_for('admin_posts'));
        break;

    default:
        simple_error_page('页面不存在', '当前操作未定义。', 404);
        break;
}
