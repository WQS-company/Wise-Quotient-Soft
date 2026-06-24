<?php
require_once __DIR__ . '/config.php';
$postId = isset($_GET['id']) ? wqs_decrypt_id($_GET['id']) : 0;

$post = null;
$tags = [];
$related = [];

if ($postId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT bp.*, u.name as author_name, u.picture as author_picture,
                               bc.name as category_name, bc.slug as category_slug, bc.color as category_color
                               FROM blog_posts bp
                               JOIN users u ON bp.author_id = u.id
                               LEFT JOIN blog_categories bc ON bp.category_id = bc.id
                               WHERE bp.id = ? AND bp.status = 'published'");
        $stmt->execute([$postId]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($post) {
            // Increment view
            try { $pdo->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?")->execute([$postId]); } catch (Exception $ex) {}

            // Fetch tags
            $tagStmt = $pdo->prepare("SELECT bt.name, bt.slug FROM blog_post_tags bpt JOIN blog_tags bt ON bpt.tag_id = bt.id WHERE bpt.post_id = ?");
            $tagStmt->execute([$postId]);
            $tags = $tagStmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch related posts (same category, excluding current)
            if ($post['category_id']) {
                $relStmt = $pdo->prepare("SELECT bp.id, bp.title, bp.slug, bp.cover_image, bp.media_type,
                                          bp.excerpt, bp.created_at, u.name as author_name,
                                          bc.name as category_name
                                          FROM blog_posts bp
                                          JOIN users u ON bp.author_id = u.id
                                          LEFT JOIN blog_categories bc ON bp.category_id = bc.id
                                          WHERE bp.category_id = ? AND bp.id != ? AND bp.status = 'published'
                                          ORDER BY bp.created_at DESC LIMIT 3");
                $relStmt->execute([$post['category_id'], $postId]);
                $related = $relStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } catch (Exception $e) {}
}

if (!$post) {
    header("Location: blog.php");
    exit;
}

// SEO meta
$metaTitle = !empty($post['meta_title']) ? $post['meta_title'] : $post['title'] . ' | WQS Blog';
$metaDesc = !empty($post['meta_description']) ? $post['meta_description'] : (!empty($post['excerpt']) ? $post['excerpt'] : strip_tags($post['content']));

$page_title = htmlspecialchars($metaTitle);

// SEO Variables for structured data
$ogImage = !empty($post['cover_image']) ? $post['cover_image'] : 'https://wisequotientsoft.com/images/og-blog.jpg';
$pubDate = $post['published_at'] ?? $post['created_at'];
$modDate = $post['updated_at'] ?? $pubDate;
$seo = [
    'title'       => $metaTitle,
    'description' => strip_tags(substr($metaDesc, 0, 160)),
    'keywords'    => !empty($tags) ? implode(', ', array_column($tags, 'name')) : 'blog, technology, software development, AI',
    'canonical'   => 'https://wisequotientsoft.com/blog_detail.php?id=' . urlencode($_GET['id'] ?? ''),
    'og_type'     => 'article',
    'og_image'    => $ogImage,
    'article'     => [
        'published_time' => $pubDate,
        'modified_time'  => $modDate,
        'author'         => $post['author_name'] ?? 'Wise Quotient Soft',
        'section'        => $post['category_name'] ?? 'Technology',
        'tags'           => array_column($tags, 'name'),
    ],
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Blog', 'url' => '/blog.php'],
        ['name' => $post['title']],
    ],
];

require_once __DIR__ . '/includes/public_header.php';

// Author initials
$parts = explode(' ', $post['author_name']);
$initials = '';
foreach ($parts as $p) $initials .= strtoupper($p[0] ?? '');
$initials = substr($initials, 0, 2);

// Read time
$wordCount = str_word_count(strip_tags($post['content']));
$readTime = max(1, ceil($wordCount / 200));
?>

<style>
  .article-hero {
    background: radial-gradient(circle at 10% 20%, rgba(10, 45, 94, 0.45) 0%, rgba(3, 7, 18, 0.95) 80%), #030712;
    padding: 5.5rem 0 4rem;
    color: white;
    position: relative;
    overflow: hidden;
  }
  .article-hero-glow {
    position: absolute; width: 400px; height: 400px; border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 102, 0, 0.15) 0%, transparent 70%);
    top: -50px; right: 10%; filter: blur(80px); pointer-events: none;
  }
  .article-card-premium {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 24px;
    padding: 3rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
    margin-top: -3rem;
    position: relative;
    z-index: 10;
  }
  .blog-tag {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #ff6600;
    background: rgba(255, 102, 0, 0.08);
    padding: 0.35rem 0.9rem;
    border-radius: 50px;
    display: inline-block;
    margin-bottom: 1.25rem;
  }
  .author-avatar-lg {
    width: 50px; height: 50px;
    border-radius: 50%;
    background: #00264d;
    color: #fff;
    font-size: 1rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
  }
  .article-body {
    line-height: 1.9;
    font-size: 1.05rem;
    color: #334155;
  }
  .article-body p { margin-bottom: 1.5rem; }
  .article-body h2, .article-body h3 { margin-top: 2rem; margin-bottom: 1rem; color: #0f172a; font-weight: 700; }
  .article-body ul, .article-body ol { margin-bottom: 1.5rem; padding-left: 1.5rem; }
  .article-body img { max-width: 100%; border-radius: 12px; margin: 1.5rem 0; }
  .article-body blockquote {
    border-left: 4px solid #ff6600; padding: 1rem 1.5rem; margin: 1.5rem 0;
    background: #f8fafc; border-radius: 0 12px 12px 0; font-style: italic; color: #475569;
  }
  .article-body pre {
    background: #0f172a; color: #e2e8f0; padding: 1.25rem; border-radius: 12px;
    overflow-x: auto; font-size: .9rem; margin: 1.5rem 0;
  }
  .article-body code { background: #f1f5f9; padding: .15rem .4rem; border-radius: 4px; font-size: .9rem; }
  .article-body pre code { background: transparent; padding: 0; }
  .article-cover-image {
    width: 100%;
    max-height: 420px;
    object-fit: cover;
    border-radius: 18px;
    border: 1px solid #e2e8f0;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0,0,0,0.06);
  }
  .tag-link {
    background: #f1f5f9; color: #64748b; padding: .2rem .65rem; border-radius: 6px;
    font-size: .78rem; font-weight: 600; text-decoration: none; border: 1px solid #e2e8f0;
    transition: all .2s; display: inline-block;
  }
  .tag-link:hover { background: #ff6600; color: white; border-color: #ff6600; }
  .related-card {
    background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden;
    transition: all .3s; height: 100%;
  }
  .related-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,.06); }
  .related-card-img { width: 100%; height: 160px; object-fit: cover; }
  .related-card-body { padding: 1.25rem; }
  .share-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 38px; height: 38px; border-radius: 50%; border: 1px solid #e2e8f0;
    color: #64748b; text-decoration: none; transition: all .2s;
  }
  .share-btn:hover { background: #ff6600; color: white; border-color: #ff6600; }
</style>

<!-- Hero Section -->
<section class="article-hero">
  <div class="article-hero-glow"></div>
  <div class="container position-relative" style="z-index: 10;">
    <div class="d-flex align-items-center gap-2 mb-2" style="font-size: 0.85rem; color: #cbd5e1;">
      <a href="blog.php" class="text-white text-decoration-none"><i class="fas fa-arrow-left me-1"></i> WQS Insights</a>
      <span>/</span>
      <span class="text-orange"><?= htmlspecialchars($post['category_name'] ?? $post['tag']) ?></span>
    </div>
  </div>
</section>

<!-- Content -->
<section class="py-5 bg-body-tertiary" style="min-height: 60vh;">
  <div class="container py-2">
    <div class="row justify-content-center">
      <div class="col-lg-9">
        <div class="article-card-premium">

          <!-- Category + Read time -->
          <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
            <span class="blog-tag" style="background:<?= htmlspecialchars($post['category_color'] ?? '#ff6600') ?>15; color:<?= htmlspecialchars($post['category_color'] ?? '#ff6600') ?>; margin-bottom:0;">
              <?= htmlspecialchars($post['category_name'] ?? $post['tag']) ?>
            </span>
            <span class="text-muted small"><i class="far fa-clock me-1"></i> <?= $readTime ?> min read</span>
            <span class="text-muted small"><i class="far fa-eye me-1"></i> <?= number_format($post['views']) ?> views</span>
          </div>

          <!-- Title -->
          <h1 class="fw-extrabold text-body mb-4" style="font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800; line-height: 1.3; font-size: clamp(1.5rem, 3vw, 2.2rem);">
            <?= htmlspecialchars($post['title']) ?>
          </h1>

          <!-- Author row -->
          <div class="d-flex align-items-center gap-3 border-bottom pb-4 mb-4">
            <?php if (!empty($post['author_picture'])): ?>
              <img src="<?= htmlspecialchars($post['author_picture']) ?>" class="author-avatar-lg" style="object-fit: cover;" alt="">
            <?php else: ?>
              <div class="author-avatar-lg"><?= htmlspecialchars($initials) ?></div>
            <?php endif; ?>
            <div>
              <div class="fw-bold text-body" style="font-size: 0.95rem;"><?= htmlspecialchars($post['author_name']) ?></div>
              <div class="text-muted small">Published on <?= date('M d, Y', strtotime($post['published_at'] ?? $post['created_at'])) ?></div>
            </div>
            <!-- Share buttons -->
            <div class="ms-auto d-flex gap-1">
              <?php $shareUrl = 'https://' . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'wisequotientsoft.com') . '/blog_detail.php?id=' . urlencode($_GET['id'] ?? ''); ?>
              <a href="https://twitter.com/intent/tweet?text=<?= urlencode($post['title']) ?>&url=<?= urlencode($shareUrl) ?>" target="_blank" class="share-btn" title="Share on Twitter"><i class="fab fa-twitter"></i></a>
              <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode($shareUrl) ?>" target="_blank" class="share-btn" title="Share on LinkedIn"><i class="fab fa-linkedin-in"></i></a>
            </div>
          </div>

          <!-- Cover media -->
          <?php if (!empty($post['cover_image'])): ?>
            <?php if (isset($post['media_type']) && $post['media_type'] === 'video'): ?>
              <video src="<?= htmlspecialchars($post['cover_image']) ?>" class="article-cover-image" controls playsinline></video>
            <?php else: ?>
              <img src="<?= htmlspecialchars($post['cover_image']) ?>" class="article-cover-image" alt="<?= htmlspecialchars($post['title']) ?>">
            <?php endif; ?>
          <?php endif; ?>

          <!-- Article content -->
          <div class="article-body">
            <?= $post['content'] ?>
          </div>

          <!-- Tags -->
          <?php if (!empty($tags)): ?>
          <div class="mt-5 pt-4 border-top">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <span class="fw-bold text-body me-2" style="font-size:.9rem;"><i class="fas fa-tags me-1"></i> Tags:</span>
              <?php foreach ($tags as $tag): ?>
                <span class="tag-link">#<?= htmlspecialchars($tag['name']) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Back button -->
          <div class="mt-4 pt-3 d-flex justify-content-between align-items-center">
            <a href="blog.php" class="btn btn-outline-dark rounded-pill px-4"><i class="fas fa-arrow-left me-2"></i>Back to Blog</a>
            <a href="#" onclick="window.print();return false;" class="btn btn-outline-secondary rounded-pill px-3"><i class="fas fa-print me-1"></i></a>
          </div>
        </div>

        <!-- Related Posts -->
        <?php if (!empty($related)): ?>
        <div class="mt-5">
          <h3 class="fw-bold mb-4" style="color:#0f172a;">Related Articles</h3>
          <div class="row g-4">
            <?php foreach ($related as $rel):
              $relParts = explode(' ', $rel['author_name']);
              $relInit = '';
              foreach ($relParts as $rp) $relInit .= strtoupper($rp[0] ?? '');
              $relInit = substr($relInit, 0, 2);
            ?>
              <div class="col-md-4">
                <a href="blog_detail.php?id=<?= wqs_encrypt_id($rel['id']) ?>" class="text-decoration-none">
                  <div class="related-card">
                    <?php if (!empty($rel['cover_image'])): ?>
                      <img src="<?= htmlspecialchars($rel['cover_image']) ?>" class="related-card-img" alt="">
                    <?php else: ?>
                      <div class="related-card-img d-flex align-items-center justify-content-center" style="background:linear-gradient(135deg,#0a2d5e,#ff6600);">
                        <i class="fas fa-cubes" style="color:rgba(255,255,255,0.2); font-size:2rem;"></i>
                      </div>
                    <?php endif; ?>
                    <div class="related-card-body">
                      <span class="text-orange small fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:1px;"><?= htmlspecialchars($rel['category_name'] ?? 'Article') ?></span>
                      <h5 class="fw-bold mt-1" style="color:#0f172a; font-size:.95rem;"><?= htmlspecialchars($rel['title']) ?></h5>
                      <p class="text-muted small mb-0" style="font-size:.8rem;"><?= htmlspecialchars(!empty($rel['excerpt']) ? substr($rel['excerpt'],0,80) : substr(strip_tags($post['content']),0,80)) ?>…</p>
                    </div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</section>

<?php
require_once __DIR__ . '/includes/public_footer.php';
?>
