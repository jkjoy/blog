# Simple PHP Blog

一个单入口实现思路做的轻量博客：

- 主程序集中在 `index.php`
- 安装流程集中在 `install.php`
- SQLite 存储
- 前台样式参考 Hugo `paper` 主题的窄栏阅读体验

## 功能

- 首页文章列表
- 文章详情页
- 文章评论列表、访客/登录用户评论表单与评论回复
- 独立页面
- 归档页
- 标签聚合页
- RSS 输出
- 管理员登录
- 后台文章管理
- 后台评论审核、未读通知与删除管理
- 草稿、发布、定时发布
- 站点基础设置
- 可扩展前台主题、主题资源与 action/filter 钩子
- AI 辅助生成设置，独立保存 API 配置
- SMTP 邮件通知设置，支持密码重置和新评论通知
- S3 附件上传，支持自定义 Endpoint、CDN 地址和可选本地备份
- 后台自动检查 GitHub Release 并一键更新程序
- 可选伪静态 URL
- 基础 Markdown 渲染

## 环境要求

- PHP 8.0+
- `pdo_sqlite` 扩展
- `fileinfo` 扩展
- 使用 S3 上传时需要 `curl` 扩展
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
uploads/       本地上传文件及可选的 S3 备份
themes/        自定义前台主题
```

## 配置与缓存

- `settings` 表保存站点基础设置。
- `cache/settings.php` 是站点基础设置缓存，用于减少常规页面读取数据库。
- `ai_settings` 表保存 AI 接口、模型、提示词和 API Key，不写入缓存文件。
- `mail_settings` 表保存 SMTP 主机、账号、密码、发件人和通知收件人，不写入缓存文件。
- `s3_settings` 表保存 S3 Endpoint、Bucket、访问密钥和上传选项，不写入缓存文件。
- 后台保存站点基础设置会刷新 `cache/settings.php`。
- 后台保存 AI、邮件通知或 S3 设置只更新对应独立数据表。

## 自定义主题

- 每个主题放在 `themes/<主题目录>/`，并提供 `theme.json`。
- 可通过 `style.css` 覆盖前台样式，通过 `functions.php` 注册 action/filter 钩子，也可用 `layout.php` 接管完整前台布局。
- 后台“站点设置 -> 前台主题”负责启用主题；无效或被删除的主题会回退到内置主题。
- 主题开发接口与完整钩子列表见 `themes/README.md`。
- 一键更新只覆盖程序发布文件，不会清空额外的自定义主题目录。

## S3 上传

后台“S3 存储”中启用后：

- 新附件会通过 AWS Signature V4 上传到 S3 或兼容服务。
- 对象键格式为“路径前缀/年份/随机文件名”。
- 可填写包含协议的完整 CDN 域名；附件 URL 会使用该域名拼接对象键，留空时使用 Endpoint 生成地址。
- MinIO 等需要 Path-style 地址的服务可开启对应选项。
- 开启“在本地保留上传备份”时，同一文件也会写入 `uploads/年份/`；关闭时不在站点目录落盘。
- S3 上传失败时不会插入附件链接；启用本地备份时，本次失败产生的本地文件也会删除。

## 邮件通知

后台“邮件通知”中启用 SMTP 后：

- 忘记密码邮件会优先通过 SMTP 发送。
- 新评论提交成功后，如站点设置里开启“新评论显示后台提醒”，会发送新评论通知邮件。
- 通知收件邮箱优先使用邮件通知设置中的收件邮箱；留空时使用第一个管理员邮箱。
- SMTP 发送失败不会阻止评论提交。
- 密码重置仍会在 `cache/password-reset-*.txt` 写入兜底链接。

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
location ~* ^/themes/.+\.(?:php|json)$ { deny all; }

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
- `ai_settings`、`mail_settings` 和 `s3_settings` 中包含后端密钥类配置，请只通过后台修改
- 如果要重装，先删除 `data/install.lock`
- 更新程序后如涉及数据库结构变更，请先登录后台，再访问 `update.php` 执行升级
- 一键更新会保留 `data/`、`cache/`、`uploads/` 和用户自建主题，递归合并发布包中的 `themes/`，并将被覆盖的程序与主题文件备份到 `cache/update-backup-*`
