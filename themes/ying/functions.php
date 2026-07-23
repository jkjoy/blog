<?php

declare(strict_types=1);

function ying_post_cover(array $post): string
{
    $content = (string)($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\((https?:\/\/[^\s)]+|\/[^\s)]+)(?:\s+["\'][^"\']*["\'])?\)/i', $content, $match)
        || preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $match)) {
        $url = safe_link_url((string)$match[1]);
        return $url !== '#' ? $url : '';
    }

    return '';
}

function ying_render_home_content(): string
{
    $page = max(1, (int)($_GET['p'] ?? 1));
    $perPage = max(1, (int)setting('posts_per_page', '6'));
    $total = count_published_posts();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $posts = fetch_published_posts($perPage, ($page - 1) * $perPage);

    ob_start();
    if ($posts):
        foreach ($posts as $index => $post):
            $cover = ying_post_cover($post);
            ?>
            <div class="flex gap-2.5 items-start post-card" style="--delay:<?= h((string)($index * 0.05)) ?>s">
              <?php if ($cover !== ''): ?>
                <div class="overflow-hidden size-11 rounded-sm">
                  <img class="w-full h-full object-cover" src="<?= h($cover) ?>" width="44" height="44" loading="lazy" alt="" onerror="this.onerror=null;this.src='<?= h(theme_asset_url('assets/images/loading.gif')) ?>'">
                </div>
              <?php endif; ?>
              <div class="flex grow flex-col justify-between mb-5">
                <div class="text-xs text-zinc-400 mb-0.5"><?= h(date('n月 d, Y', (int)$post['published_at'])) ?></div>
                <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>" class="hover:underline">
                  <?php if (!empty($post['is_pinned'])): ?><span class="text-red-500">[置顶]</span><?php endif; ?>
                  <span><?= h((string)$post['title']) ?></span>
                </a>
              </div>
            </div>
            <?php
        endforeach;
        if ($totalPages > 1): ?>
          <ul class="flex justify-between pt-2">
            <li><?php if ($page < $totalPages): ?><a href="<?= h(home_page_url($page + 1)) ?>"><span>下一页</span></a><?php endif; ?></li>
            <li><?php if ($page > 1): ?><a href="<?= h(home_page_url($page - 1)) ?>"><span>上一页</span></a><?php endif; ?></li>
          </ul>
        <?php endif;
    else: ?>
      <div class="empty-notice"><p>还没有已发布的文章。</p></div>
    <?php endif;
    return (string)ob_get_clean();
}

function ying_render_archives_content(): string
{
    $years = [];
    foreach (fetch_archive_posts() as $post) {
        $timestamp = (int)$post['published_at'];
        $years[date('Y', $timestamp)][date('n', $timestamp)][] = $post;
    }

    ob_start();
    ?>
    <div class="archives-content">
      <article>
        <h1 class="post-title">归档</h1>
        <p class="my-5">共有 <?= h((string)count_published_posts()) ?> 篇文章</p>
        <?php foreach ($years as $year => $months): ?>
          <h2 class="post-card"><?= h((string)$year) ?></h2>
          <div class="list-none mb-5">
            <?php foreach ($months as $month => $posts): ?>
              <h3 class="post-card my-5"><?= h((string)$month) ?>月</h3>
              <?php foreach ($posts as $post): ?>
                <li class="post-card">
                  <span class="archives-li"><?= h(str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '/' . date('d', (int)$post['published_at'])) ?></span>
                  <a href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>" class="text-sm hover:underline"><?= h((string)$post['title']) ?></a>
                </li>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </article>
    </div>
    <?php
    return (string)ob_get_clean();
}

function ying_render_category_content(string $slug): string
{
    $category = one('SELECT * FROM categories WHERE slug = ?', [trim($slug)]);
    if (!$category) { return ''; }
    $posts = all_rows(
        'SELECT * FROM posts WHERE kind = ? AND category_id = ? AND status = ? AND published_at <= ? ORDER BY is_pinned DESC, published_at DESC, id DESC',
        ['post', (int)$category['id'], 'published', time()]
    );
    $description = trim((string)$category['description']);

    ob_start();
    ?>
    <div class="category-content">
      <article>
        <header class="category-header">
          <h1 class="item-a category-title">分类：<?= h((string)$category['name']) ?></h1>
          <p class="category-summary">
            <span>共有 <strong><?= h((string)count($posts)) ?></strong> 篇文章</span>
            <?php if ($description !== ''): ?><span class="category-summary__divider" aria-hidden="true">·</span><span><?= h($description) ?></span><?php endif; ?>
          </p>
        </header>

        <?php if ($posts): ?>
          <ol class="category-posts">
            <?php foreach ($posts as $index => $post): ?>
              <?php $publishedAt = (int)$post['published_at']; ?>
              <li class="category-post post-card" style="--delay:<?= h((string)(min($index, 8) * 0.04)) ?>s">
                <time class="archives-li category-post__date" datetime="<?= h(date('Y-m-d', $publishedAt)) ?>"><?= h(date('Y/m/d', $publishedAt)) ?></time>
                <a class="category-post__link" href="<?= h(url_for('post', ['slug' => (string)$post['slug']])) ?>">
                  <?php if (!empty($post['is_pinned'])): ?><span class="category-post__pinned">[置顶]</span><?php endif; ?>
                  <span><?= h((string)$post['title']) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php else: ?>
          <div class="empty-notice"><p>这个分类下还没有已发布文章。</p></div>
        <?php endif; ?>
      </article>
    </div>
    <?php
    return (string)ob_get_clean();
}

function ying_render_links_content(): string
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
            <?php $name = trim((string)$link['name']); ?>
            <?php $iconUrl = trim((string)$link['icon_url']); ?>
            <a class="friend-link" href="<?= h((string)$link['url']) ?>" target="_blank" rel="noopener noreferrer">
              <span class="friend-link__avatar" aria-hidden="true">
                <span><?= h(str_sub_u($name, 0, 1)) ?></span>
                <?php if ($iconUrl !== ''): ?><img src="<?= h($iconUrl) ?>" width="58" height="58" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?>
              </span>
              <span class="friend-link__copy">
                <strong><?= h($name) ?></strong>
                <span><?= h(trim((string)$link['description']) ?: '欢迎访问这个网站') ?></span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty-notice"><p>还没有添加友情链接。</p></div>
      <?php endif; ?>
    </article>
    <?php
    return (string)ob_get_clean();
}

add_theme_filter('body_class', static function (string $classes, array $context): string {
    return trim($classes . ' ying-theme');
});

add_theme_filter('content', static function (string $content, array $context): string {
    $active = (string)($context['active'] ?? '');
    if ($active === 'home' && (string)($_GET['a'] ?? '') === 'category') {
        $categoryContent = ying_render_category_content((string)($_GET['slug'] ?? ''));
        return $categoryContent !== '' ? $categoryContent : $content;
    }
    if ($active === 'home' && (string)($context['title'] ?? '') === (string)($context['site_name'] ?? '')) {
        return ying_render_home_content();
    }
    if ($active === 'archives') {
        return ying_render_archives_content();
    }
    if ($active === 'links') {
        return ying_render_links_content();
    }

    return strtr($content, [
        '<h2 class="section-header" id="comments-title">comments.log</h2>' => '<h2 class="section-header" id="comments-title">评论</h2>',
        '<h3 class="comment-form__title">new-comment</h3>' => '<h3 class="comment-form__title">写下评论</h3>',
        '<button class="terminal-action" type="submit">[提交评论]</button>' => '<button class="terminal-action" type="submit">提交评论</button>',
        '<div class="comments__empty empty-notice">// 暂无评论</div>' => '<div class="comments__empty empty-notice">还没有评论</div>',
        '<div class="comments__empty empty-notice">// 评论已关闭</div>' => '<div class="comments__empty empty-notice">评论已关闭</div>',
    ]);
});

add_theme_action('head', static function (array $context): string {
    return <<<'HTML'
<meta name="theme-color" content="#f3f4f6" data-ying-theme-color>
<script>(function(){try{document.documentElement.classList.toggle('dark',localStorage.getItem('darkMode')==='true')}catch(e){}})();</script>
HTML;
});
