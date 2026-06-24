<?php
$page_title = 'Blog & Insights - Tech Articles | Wise Quotient Soft';
$seo = [
    'title'       => 'Blog & Insights - Tech Articles | Wise Quotient Soft',
    'description' => 'Read the latest tech insights, software development guides, AI trends, and digital transformation articles from Wise Quotient Soft experts.',
    'keywords'    => 'tech blog, software development blog, AI articles, machine learning insights, digital transformation blog, WQS blog, technology news Nigeria',
    'canonical'   => 'https://wisequotientsoft.com/blog.php',
    'og_image'    => 'https://wisequotientsoft.com/images/og-blog.jpg',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Blog', 'url' => '/blog.php'],
    ],
];
require_once __DIR__ . '/includes/public_header.php';

try {
    // Fetch categories from blog_categories
    $cat_stmt = $pdo->query("SELECT id, name, slug, color FROM blog_categories ORDER BY name ASC");
    $categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch featured post (is_featured=1, newest first)
    $feat_stmt = $pdo->query("SELECT bp.*, u.name as author_name, u.picture as author_picture,
                              bc.name as category_name, bc.color as category_color
                              FROM blog_posts bp
                              JOIN users u ON bp.author_id = u.id
                              LEFT JOIN blog_categories bc ON bp.category_id = bc.id
                              WHERE bp.status = 'published' AND bp.is_featured = 1
                              ORDER BY bp.created_at DESC LIMIT 1");
    $featured = $feat_stmt->fetch(PDO::FETCH_ASSOC);

    // If no featured post, get newest as featured
    if (!$featured) {
        $feat_stmt = $pdo->query("SELECT bp.*, u.name as author_name, u.picture as author_picture,
                                  bc.name as category_name, bc.color as category_color
                                  FROM blog_posts bp
                                  JOIN users u ON bp.author_id = u.id
                                  LEFT JOIN blog_categories bc ON bp.category_id = bc.id
                                  WHERE bp.status = 'published'
                                  ORDER BY bp.created_at DESC LIMIT 1");
        $featured = $feat_stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch other published posts (excluding featured)
    $excludeId = $featured ? $featured['id'] : 0;
    $other_stmt = $pdo->prepare("SELECT bp.*, u.name as author_name, u.picture as author_picture,
                                 bc.name as category_name, bc.color as category_color
                                 FROM blog_posts bp
                                 JOIN users u ON bp.author_id = u.id
                                 LEFT JOIN blog_categories bc ON bp.category_id = bc.id
                                 WHERE bp.status = 'published' AND bp.id != ?
                                 ORDER BY bp.created_at DESC LIMIT 100");
    $other_stmt->execute([$excludeId]);
    $posts = $other_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch tags for all displayed posts
    $allPostIds = [];
    if ($featured) $allPostIds[] = $featured['id'];
    foreach ($posts as $p) $allPostIds[] = $p['id'];
    $postTags = [];
    if (!empty($allPostIds)) {
        $placeholders = implode(',', array_fill(0, count($allPostIds), '?'));
        $tagStmt = $pdo->prepare("SELECT bpt.post_id, bt.name, bt.slug FROM blog_post_tags bpt JOIN blog_tags bt ON bpt.tag_id = bt.id WHERE bpt.post_id IN ($placeholders)");
        $tagStmt->execute($allPostIds);
        foreach ($tagStmt->fetchAll() as $t) {
            $postTags[$t['post_id']][] = $t;
        }
    }
} catch (Exception $e) {
    $categories = [];
    $featured = null;
    $posts = [];
    $postTags = [];
}
?>

<style>
  .blog-hero {
    background: linear-gradient(rgba(3, 7, 18, 0.75), rgba(3, 7, 18, 0.9)), url('images/blog_hero_bg.png') center/cover no-repeat, #030712;
    padding: 5.5rem 0 4rem;
    color: white;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .blog-hero-glow {
    position: absolute; width: 400px; height: 400px; border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 102, 0, 0.15) 0%, transparent 70%);
    top: -50px; left: 50%; transform: translateX(-50%); filter: blur(80px); pointer-events: none;
  }
  .featured-post-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 24px;
    padding: 2.5rem;
    box-shadow: var(--shadow-soft);
    transition: var(--transition-smooth);
    margin-top: -3rem;
    position: relative;
    z-index: 10;
  }
  .featured-post-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
    border-color: #cbd5e1;
  }
  .blog-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    height: 100%;
    transition: var(--transition-smooth);
    box-shadow: var(--shadow-soft);
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }
  .blog-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-hover);
    border-color: #ff6600;
  }
  .blog-card-cover {
    width: 100%;
    height: 190px;
    object-fit: cover;
    display: block;
    border-bottom: 1px solid #f1f5f9;
    transition: transform 0.4s ease;
  }
  .blog-card:hover .blog-card-cover {
    transform: scale(1.03);
  }
  .blog-card-cover-placeholder {
    width: 100%;
    height: 190px;
    background: linear-gradient(135deg, #0a2d5e 0%, #ff6600 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.25);
    font-size: 3rem;
  }
  .blog-card-body {
    padding: 1.75rem;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
    justify-content: space-between;
  }
  .featured-cover-image {
    width: 100%;
    height: 100%;
    min-height: 250px;
    object-fit: cover;
    border-radius: 16px;
    border: 1px solid #e2e8f0;
  }
  .featured-cover-placeholder {
    width: 100%;
    min-height: 250px;
    border-radius: 16px;
    background: linear-gradient(135deg, #0a2d5e 0%, #ff6600 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.2);
    font-size: 6rem;
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
  .author-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: #00264d;
    color: #fff;
    font-size: 0.82rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
  }
  .btn-read-more {
    color: #00264d;
    font-weight: 700;
    text-decoration: none;
    font-size: 0.9rem;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.2s ease;
    margin-top: 1rem;
  }
  .blog-card:hover .btn-read-more, .featured-post-card:hover .btn-read-more {
    color: #ff6600;
    padding-left: 0.25rem;
  }
  .category-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    justify-content: center;
    margin-bottom: 2.5rem;
  }
  .category-pill {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    color: #475569;
    padding: 0.5rem 1.25rem;
    border-radius: 50px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
  }
  .category-pill:hover, .category-pill.active {
    background: #ff6600;
    color: white;
    border-color: #ff6600;
    box-shadow: 0 4px 12px rgba(255, 102, 0, 0.2);
  }
  .post-tags { display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.75rem; }
  .post-tag-link { background:#f1f5f9; color:#64748b; padding:.15rem .55rem; border-radius:6px; font-size:.72rem; font-weight:600; border:1px solid #e2e8f0; text-decoration:none; transition:all .15s; }
  .post-tag-link:hover { background:#ff6600; color:white; border-color:#ff6600; }
  .excerpt-text { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; }
</style>

<!-- Blog Hero -->
<section class="blog-hero">
  <div class="blog-hero-glow"></div>
  <div class="container position-relative" style="z-index: 10;">
    <span class="badge mb-3" style="background: rgba(225, 85, 1, 0.15); color: #ff6600; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 2px; border: 1px solid rgba(225, 85, 1, 0.3); padding: 0.5rem 1.2rem; border-radius: 50px;">
      WQS Insights
    </span>
    <h1 class="fw-extrabold text-white mb-3" style="font-size: clamp(2rem, 5vw, 3rem); font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800;">
      Engineering &amp; <span class="text-orange">Technology Blog</span>
    </h1>
    <p class="text-muted mx-auto" style="max-width: 600px; color: #cbd5e1 !important; font-size: 1.05rem;">
      In-depth guides, articles, and architectures prepared by the software engineers at Wise Quotient Soft.
    </p>
  </div>
</section>

<!-- Featured Post -->
<?php if ($featured):
  $parts = explode(' ', $featured['author_name']);
  $initials = '';
  foreach ($parts as $p) $initials .= strtoupper($p[0] ?? '');
  $f_initials = substr($initials, 0, 2);
  $featTags = $postTags[$featured['id']] ?? [];
?>
<div class="container mb-5 reveal-up">
  <div class="featured-post-card">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <span class="blog-tag" style="background:<?= htmlspecialchars($featured['category_color'] ?? '#ff6600') ?>15; color:<?= htmlspecialchars($featured['category_color'] ?? '#ff6600') ?>;">
          <?= htmlspecialchars($featured['category_name'] ?? $featured['tag']) ?>
        </span>
        <h2 class="fw-bold mb-3" style="color: #00264d; font-family: 'Plus Jakarta Sans', sans-serif; line-height: 1.3;">
          <?= htmlspecialchars($featured['title']) ?>
        </h2>
        <p class="text-muted mb-3 excerpt-text" style="line-height: 1.7;">
          <?= htmlspecialchars(!empty($featured['excerpt']) ? $featured['excerpt'] : strip_tags($featured['content'])) ?>
        </p>
        <?php if (!empty($featTags)): ?>
        <div class="post-tags">
          <?php foreach ($featTags as $ft): ?>
            <span class="post-tag-link">#<?= htmlspecialchars($ft['name']) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mt-3">
          <div class="d-flex align-items-center gap-3">
            <?php if (!empty($featured['author_picture'])): ?>
              <img src="<?= htmlspecialchars($featured['author_picture']) ?>" class="author-avatar" style="object-fit: cover;" alt="">
            <?php else: ?>
              <div class="author-avatar"><?= htmlspecialchars($f_initials) ?></div>
            <?php endif; ?>
            <div>
              <div class="fw-bold text-body" style="font-size: 0.9rem;"><?= htmlspecialchars($featured['author_name']) ?></div>
              <div class="text-muted small">Published • <?= date('M d, Y', strtotime($featured['published_at'] ?? $featured['created_at'])) ?></div>
            </div>
          </div>
          <a href="blog_detail.php?id=<?= wqs_encrypt_id($featured['id']) ?>" class="btn-read-more">Read Article <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
      <div class="col-lg-5">
        <?php if (!empty($featured['cover_image'])): ?>
          <?php if (isset($featured['media_type']) && $featured['media_type'] === 'video'): ?>
            <video src="<?= htmlspecialchars($featured['cover_image']) ?>" class="featured-cover-image" muted autoplay loop playsinline></video>
          <?php else: ?>
            <img src="<?= htmlspecialchars($featured['cover_image']) ?>" class="featured-cover-image" alt="<?= htmlspecialchars($featured['title']) ?>">
          <?php endif; ?>
        <?php else: ?>
          <div class="featured-cover-placeholder">
            <i class="fas fa-cubes"></i>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Blog Articles Grid -->
<section class="py-5 bg-body-tertiary">
  <div class="container py-4">
    <!-- Category Filter Bar -->
    <?php if (!empty($categories)): ?>
      <div class="category-pills" id="blogCategoryFilters">
        <div class="category-pill active" data-filter="all">All Categories</div>
        <?php foreach ($categories as $cat): ?>
          <div class="category-pill" data-filter="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="row g-4" id="blogPostsGrid">
      <?php if (!empty($posts)): ?>
        <?php foreach ($posts as $post):
          $parts = explode(' ', $post['author_name']);
          $initials = '';
          foreach ($parts as $p) $initials .= strtoupper($p[0] ?? '');
          $p_initials = substr($initials, 0, 2);
          $tags = $postTags[$post['id']] ?? [];
        ?>
          <div class="col-md-6 col-lg-4 reveal-up blog-item" data-category="<?= htmlspecialchars($post['category_name'] ?? $post['tag']) ?>">
            <div class="blog-card">
              <div style="overflow:hidden; position: relative;">
                <?php if (!empty($post['cover_image'])): ?>
                  <?php if (isset($post['media_type']) && $post['media_type'] === 'video'): ?>
                    <video src="<?= htmlspecialchars($post['cover_image']) ?>" class="blog-card-cover" muted onmouseover="this.play()" onmouseout="this.pause()" playsinline></video>
                    <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-play" style="font-size: 0.7rem; margin-left: 2px;"></i>
                    </div>
                  <?php else: ?>
                    <img src="<?= htmlspecialchars($post['cover_image']) ?>" class="blog-card-cover" alt="<?= htmlspecialchars($post['title']) ?>">
                  <?php endif; ?>
                <?php else: ?>
                  <div class="blog-card-cover-placeholder">
                    <i class="fas fa-cubes"></i>
                  </div>
                <?php endif; ?>
              </div>
              <div class="blog-card-body">
                <div>
                  <span class="blog-tag" style="background:<?= htmlspecialchars($post['category_color'] ?? '#ff6600') ?>15; color:<?= htmlspecialchars($post['category_color'] ?? '#ff6600') ?>;">
                    <?= htmlspecialchars($post['category_name'] ?? $post['tag']) ?>
                  </span>
                  <h4 class="fw-bold mb-2" style="color: #00264d; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.1rem; line-height: 1.4;">
                    <?= htmlspecialchars($post['title']) ?>
                  </h4>
                  <p class="text-muted excerpt-text" style="font-size: 0.88rem; line-height: 1.6;">
                    <?= htmlspecialchars(!empty($post['excerpt']) ? $post['excerpt'] : strip_tags($post['content'])) ?>
                  </p>
                  <?php if (!empty($tags)): ?>
                  <div class="post-tags" style="margin-top:.5rem;">
                    <?php foreach (array_slice($tags, 0, 3) as $t): ?>
                      <span class="post-tag-link">#<?= htmlspecialchars($t['name']) ?></span>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>
                </div>
                <div class="mt-4 pt-3 border-top d-flex align-items-center justify-content-between">
                  <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($post['author_picture'])): ?>
                      <img src="<?= htmlspecialchars($post['author_picture']) ?>" class="author-avatar" style="width: 30px; height: 30px; font-size: 0.75rem; object-fit: cover;" alt="">
                    <?php else: ?>
                      <div class="author-avatar" style="width: 30px; height: 30px; font-size: 0.75rem;"><?= htmlspecialchars($p_initials) ?></div>
                    <?php endif; ?>
                    <span class="text-muted small"><?= htmlspecialchars($post['author_name']) ?> • <?= date('M d', strtotime($post['created_at'])) ?></span>
                  </div>
                  <a href="blog_detail.php?id=<?= wqs_encrypt_id($post['id']) ?>" class="btn-read-more" style="margin: 0;">Read <i class="fas fa-arrow-right"></i></a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <?php if (!$featured): ?>
          <div class="col-12 text-center text-muted py-5">
            <i class="far fa-newspaper fa-3x mb-3" style="color: #cbd5e1;"></i>
            <p>No blog posts published yet.</p>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterBtns = document.querySelectorAll('.category-pill');
    const blogItems = document.querySelectorAll('.blog-item');
    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const filterValue = btn.getAttribute('data-filter');
            blogItems.forEach(item => {
                const itemCategory = item.getAttribute('data-category');
                if (filterValue === 'all' || filterValue === itemCategory) {
                    item.style.display = 'block';
                    item.style.animation = 'fadeInUp 0.5s ease forwards';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
});
</script>

<?php
require_once __DIR__ . '/includes/public_footer.php';
?>
