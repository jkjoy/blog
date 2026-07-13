# Simple PHP Blog

一个单入口实现思路做的轻量博客：

- 主程序集中在 `index.php`
- 安装流程集中在 `install.php`
- SQLite 存储
- 前台样式参考 Hugo `paper` 主题的窄栏阅读体验

## 功能

- 首页文章列表
- 文章详情页
- 独立页面
- 归档页
- 标签聚合页
- RSS 输出
- 管理员登录
- 后台文章管理
- 草稿、发布、定时发布
- 站点基础设置
- 后台自动检查 GitHub Release 并一键更新程序
- 可选伪静态 URL
- 基础 Markdown 渲染

## 环境要求

- PHP 8.0+
- `pdo_sqlite` 扩展
- Apache / Nginx / Caddy / PHP 内置服务器

## 安装

1. 把项目放到 Web 根目录或子目录。
2. 确保 `data/` 和 `cache/` 可写。
3. 访问 `install.php`。
4. 填写站点信息、管理员账号和欢迎文章。
5. 安装完成后进入后台继续配置。

## 目录

```text
index.php      主入口
install.php    安装页
index.css      前后台样式
index.js       前台交互
.htaccess      Apache 重写和目录保护
data/          SQLite、安装锁、配置
cache/         设置缓存
```

## 本地运行

如果本机有 PHP：

```bash
php -S 127.0.0.1:8000
```

然后访问：

```text
http://127.0.0.1:8000/install.php
```

## 伪静态 URL

后台开启后，公开页面和主要管理页面会使用这类路径：

- `/`
- `/page/2`
- `/archives`
- `/tags`
- `/tag/php`
- `/archive/your-slug`
- `/about`
- `/rss.xml`
- `/login`
- `/admin`
- `/write`
- `/edit/12`

Apache 已可直接使用仓库里的 `.htaccess`。

如果你用 Nginx，可以参考：

```nginx
location ^~ /data/ { deny all; }
location ^~ /cache/ { deny all; }

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
```

## 注意

- `data/` 和 `cache/` 不应该被公网直接访问
- 如果要重装，先删除 `data/install.lock`
- 更新程序后如涉及数据库结构变更，请先登录后台，再访问 `update.php` 执行升级
- 一键更新会保留 `data/`、`cache/`、`uploads/`，并将被覆盖的程序文件备份到 `cache/update-backup-*`
