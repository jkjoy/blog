# 主题开发

每个自定义主题放在 `themes/<slug>/`。目录名只能包含小写字母、数字、连字符和下划线，并必须提供 `theme.json`：

```json
{
  "name": "主题名称",
  "version": "1.0.0",
  "author": "作者",
  "description": "主题说明"
}
```

主题可包含以下文件：

- `style.css`：通过标准 `head` action 自动在内置前台样式之后加载。
- `functions.php`：注册 action 和 filter 钩子。
- `layout.php`：可选，输出完整 HTML 文档并接管前台布局。
- 其他 CSS、JavaScript 和图片：使用 `theme_asset_url('assets/app.js')` 获取当前主题资源地址。

安装后进入“后台 -> 站点设置 -> 前台主题”启用。主题 PHP 是服务器端可信代码，只安装来源可信的主题。

## 钩子 API

注册 action：

```php
add_theme_action('head', static function (array $context): string {
    return '<meta name="theme-color" content="#101820">';
});
```

注册 filter：

```php
add_theme_filter('body_class', static function (string $classes, array $context): string {
    return trim($classes . ' my-theme');
});
```

可用 action：

- `head`
- `body_open`
- `header_before`、`header_after`
- `content_before`、`content_after`
- `footer_before`、`footer_after`
- `body_close`

可用 filter：

- `document_title`
- `description`
- `body_class`
- `content`

action 回调接收 `$context`；返回字符串会被输出，也可以在回调中直接输出。filter 回调依次接收当前值和 `$context`，应返回过滤后的值。第三个参数可设置优先级，数值越小越先执行：

```php
add_theme_filter('content', $callback, 20);
```

`$context` 包含 `title`、`full_title`、`description`、`content`、`options`、`site_name`、`active`、`admin`、`nav_pages`、`theme`、`body_class`、`style_url` 和 `flash`。

## 自定义布局

`layout.php` 在 `render_layout()` 的局部作用域中加载，可以直接使用 `$title`、`$content`、`$options`、`$siteName`、`$fullTitle`、`$description`、`$bodyClass`、`$theme`、`$themeContext`、`$flash` 和 `$navPages`，也可以调用博客现有的 URL 与转义辅助函数。

自定义布局必须输出完整 HTML 文档。必须在 `<head>` 中调用 `head` action 才会自动加载 `style.css`；建议保留：

```php
<?php theme_action('head', $themeContext); ?>
<?php theme_action('body_open', $themeContext); ?>
<?php theme_action('content_before', $themeContext); ?>
<?= $content ?>
<?php theme_action('content_after', $themeContext); ?>
<?php theme_action('body_close', $themeContext); ?>
```

若 `functions.php`、钩子回调或 `layout.php` 抛出异常，程序会记录到 PHP error log，并尽可能使用内置布局继续响应。主题被删除或清单失效时会自动回退到内置主题。
