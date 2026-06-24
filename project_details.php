<?php
require_once __DIR__ . '/config.php';

// Fetch the project ID
$proj_id = isset($_GET['id']) ? wqs_decrypt_id($_GET['id']) : 0;
$project = null;

if ($proj_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND is_visible = 1");
        $stmt->execute([$proj_id]);
        $project = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($project) {
            // Fetch images
            $stmt = $pdo->prepare("SELECT image_path, caption FROM project_images WHERE project_id = ?");
            $stmt->execute([$proj_id]);
            $raw_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $project['images'] = [];
            foreach ($raw_images as $row) {
                $paths = explode(';', $row['image_path']);
                foreach ($paths as $img) {
                    $cleaned = trim($img);
                    if (!empty($cleaned)) {
                        $isHttp = strpos($cleaned, 'http') === 0;
                        $project['images'][] = [
                            'path' => $isHttp ? $cleaned : 'admin/' . $cleaned,
                            'caption' => $row['caption'] ?? ''
                        ];
                    }
                }
            }

            // Fetch tech stacks
            $stack_stmt = $pdo->prepare("SELECT stack_name FROM project_tech_stacks WHERE project_id = ?");
            $stack_stmt->execute([$proj_id]);
            $project['tech_stacks'] = $stack_stmt->fetchAll(PDO::FETCH_COLUMN);

            // Fetch features
            $feat_stmt = $pdo->prepare("SELECT feature_name FROM project_features WHERE project_id = ?");
            $feat_stmt->execute([$proj_id]);
            $project['features'] = $feat_stmt->fetchAll(PDO::FETCH_COLUMN);

            $project['enable_download'] = (bool)$project['enable_download'];
        }
    } catch (Exception $e) {
        // Fail-safe
    }
}

// Redirect to portfolio if project doesn't exist
if (!$project) {
    header("Location: portforlio.php");
    exit;
}

$page_title = 'Wise Quotient Soft | Project Details - ' . htmlspecialchars($project['title']);
require_once __DIR__ . '/includes/public_header.php';

// Smart classification for breadcrumbs/details
$stacksStr = strtolower(implode(' ', $project['tech_stacks']));
$category = 'Enterprise System';
if (strpos($stacksStr, 'react native') !== false || strpos($stacksStr, 'flutter') !== false || strpos($stacksStr, 'swift') !== false || strpos($stacksStr, 'android') !== false || strpos($stacksStr, 'kotlin') !== false || strpos($stacksStr, 'ios') !== false) {
    $category = 'Mobile Application';
} elseif (strpos($stacksStr, 'html') !== false || strpos($stacksStr, 'css') !== false || strpos($stacksStr, 'react') !== false || strpos($stacksStr, 'vue') !== false || strpos($stacksStr, 'php') !== false || strpos($stacksStr, 'laravel') !== false) {
    $category = 'Web Application';
}
?>

<style>
  /* ===== PROJECT DETAILS PREMIUM STYLES ===== */
  .details-hero {
    background: radial-gradient(circle at 10% 20%, rgba(10, 45, 94, 0.45) 0%, rgba(3, 7, 18, 0.95) 80%), #030712;
    padding: 5.5rem 0 4rem;
    color: white;
    position: relative;
    overflow: hidden;
  }
  .details-hero-glow {
    position: absolute; width: 400px; height: 400px; border-radius: 50%;
    background: radial-gradient(circle, rgba(255, 102, 0, 0.15) 0%, transparent 70%);
    top: -50px; right: 10%; filter: blur(80px); pointer-events: none;
  }
  
  .details-card-premium {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 24px;
    padding: 2.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
    margin-bottom: 2rem;
  }
  
  .sidebar-card-pro {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 20px;
    padding: 1.75rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.015);
    margin-bottom: 1.5rem;
  }

  .detail-tag {
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: #ff6600;
    background: rgba(255, 102, 0, 0.08);
    padding: 0.35rem 0.9rem;
    border-radius: 50px;
    display: inline-block;
    margin-bottom: 1rem;
  }

  .stack-pills {
    display: flex; flex-wrap: wrap; gap: 0.4rem; margin-bottom: 1.5rem;
  }
  .stack-pill-badge {
    font-size: 0.75rem; font-weight: 600; color: #0f3a5d;
    background: #eef2f6; border: 1px solid #e2e8f0;
    padding: 0.25rem 0.75rem; border-radius: 8px;
  }

  /* ===== PREMIUM GALLERY VIEWER ===== */
  .gallery-section {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0,0,0,0.02);
    margin-bottom: 2rem;
  }
  .gallery-main-viewer {
    position: relative;
    background: #0f172a;
    overflow: hidden;
    cursor: zoom-in;
  }
  .gallery-main-viewer img {
    width: 100%;
    max-height: 480px;
    object-fit: contain;
    display: block;
    transition: opacity 0.3s ease;
  }
  .gallery-main-caption {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, transparent 100%);
    color: white;
    padding: 1.5rem 1.5rem 1rem;
    font-size: 0.88rem;
    font-weight: 500;
    letter-spacing: 0.01em;
    transition: opacity 0.3s ease;
  }
  .gallery-main-caption i {
    color: #ff6600;
    margin-right: 0.4rem;
  }
  .gallery-nav-btn {
    position: absolute;
    top: 50%; transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255,255,255,0.2);
    backdrop-filter: blur(8px);
    color: white;
    width: 42px; height: 42px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1rem;
    transition: all 0.25s ease;
    z-index: 10;
  }
  .gallery-nav-btn:hover {
    background: rgba(255, 102, 0, 0.75);
    border-color: #ff6600;
    transform: translateY(-50%) scale(1.1);
  }
  .gallery-nav-prev { left: 1rem; }
  .gallery-nav-next { right: 1rem; }
  
  .gallery-counter {
    position: absolute;
    top: 1rem; right: 1rem;
    background: rgba(0,0,0,0.5);
    backdrop-filter: blur(6px);
    color: white;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 0.3rem 0.75rem;
    border-radius: 20px;
    border: 1px solid rgba(255,255,255,0.15);
    letter-spacing: 1px;
    z-index: 10;
  }

  /* Thumbnail Strip */
  .gallery-thumbnails {
    padding: 1rem 1.25rem;
    background: var(--color-bg);
    border-top: 1px solid var(--color-border);
    display: flex;
    gap: 0.65rem;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
  }
  .gallery-thumbnails::-webkit-scrollbar { height: 4px; }
  .gallery-thumbnails::-webkit-scrollbar-track { background: transparent; }
  .gallery-thumbnails::-webkit-scrollbar-thumb { background: var(--color-text-light); border-radius: 2px; }

  .gallery-thumb {
    flex-shrink: 0;
    width: 90px; height: 65px;
    border-radius: 10px;
    overflow: hidden;
    border: 2.5px solid transparent;
    cursor: pointer;
    transition: all 0.25s ease;
    position: relative;
    background: #0f172a;
  }
  .gallery-thumb img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: opacity 0.2s;
    opacity: 0.65;
  }
  .gallery-thumb:hover img { opacity: 0.9; }
  .gallery-thumb.active {
    border-color: #ff6600;
    box-shadow: 0 0 0 2px rgba(255,102,0,0.2);
  }
  .gallery-thumb.active img { opacity: 1; }
  .gallery-thumb-index {
    position: absolute;
    bottom: 2px; right: 4px;
    color: rgba(255,255,255,0.7);
    font-size: 0.65rem;
    font-weight: 700;
  }

  /* Fullscreen Lightbox */
  .gallery-lightbox {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.94);
    z-index: 99999;
    display: none;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 1rem;
  }
  .gallery-lightbox.open { display: flex; }
  .gallery-lightbox img {
    max-width: 90vw;
    max-height: 82vh;
    object-fit: contain;
    border-radius: 8px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
    transition: opacity 0.3s;
  }
  .lightbox-caption {
    color: rgba(255,255,255,0.75);
    margin-top: 1rem;
    font-size: 0.9rem;
    text-align: center;
  }
  .lightbox-close {
    position: fixed;
    top: 1.25rem; right: 1.5rem;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    width: 40px; height: 40px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1.1rem;
    transition: all 0.2s;
  }
  .lightbox-close:hover { background: #ef4444; }
  .lightbox-nav {
    position: fixed;
    top: 50%; transform: translateY(-50%);
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    width: 48px; height: 48px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    font-size: 1.2rem;
    transition: all 0.25s;
  }
  .lightbox-nav:hover { background: rgba(255,102,0,0.75); }
  .lightbox-prev { left: 1.25rem; }
  .lightbox-next { right: 1.25rem; }

  /* Metric cards */
  .detail-stat-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 0.75rem 0; border-bottom: 1px solid #f1f5f9;
  }
  .detail-stat-row:last-child {
    border-bottom: none;
  }
  .detail-stat-row span {
    font-size: 0.85rem; color: #64748b; font-weight: 600;
  }
  .detail-stat-row strong {
    font-size: 0.9rem; color: #00264d; font-weight: 800; font-family: 'Outfit', sans-serif;
  }

  /* Comments review block */
  .reviews-list-box {
    max-height: 350px; overflow-y: auto; margin-bottom: 1.5rem; padding-right: 0.5rem;
  }
  .review-bubble-item {
    background:var(--color-bg); border: 1px solid #e2e8f0; border-radius: 12px;
    padding: 0.9rem 1.1rem; margin-bottom: 0.75rem;
  }
  .review-bubble-item .meta {
    display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748b; margin-bottom: 0.35rem;
  }
</style>

<!-- Details Hero -->
<section class="details-hero">
  <div class="details-hero-glow"></div>
  <div class="container position-relative" style="z-index: 10;">
    <div class="d-flex align-items-center gap-2 mb-2" style="font-size: 0.85rem; color: #cbd5e1;">
      <a href="portforlio.php" class="text-white text-decoration-none">Portfolio</a>
      <span>/</span>
      <span class="text-orange"><?= htmlspecialchars($project['title']) ?></span>
    </div>
    <span class="detail-tag"><?= htmlspecialchars($category) ?></span>
    <h1 class="fw-extrabold text-white mb-2" style="font-size: clamp(1.8rem, 4vw, 2.8rem); font-family: 'Plus Jakarta Sans', sans-serif; font-weight: 800;">
      <?= htmlspecialchars($project['title']) ?>
    </h1>
  </div>
</section>

<!-- Content Stage -->
<section class="py-5" style="background-color: #f8fafc;">
  <div class="container py-2">
    <div class="row g-4">
      
      <!-- Main Content Column (Left) -->
      <div class="col-lg-8">
        <!-- Overview Description Card -->
        <div class="details-card-premium">
          <h3 class="fw-bold mb-3 text-body" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.35rem;">Scope &amp; Solution Objectives</h3>
          <div class="text-muted" style="line-height: 1.8; font-size: 0.95rem;">
            <?= $project['description'] ?>
          </div>
        </div>

        <?php if (!empty($project['features'])): ?>
        <!-- Features List Card -->
        <div class="details-card-premium">
          <h3 class="fw-bold mb-3 text-body" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.35rem;">Key Features &amp; Capabilities</h3>
          <div class="row g-3">
            <?php foreach ($project['features'] as $feat): ?>
              <div class="col-md-6 d-flex align-items-center gap-2">
                <span style="color: #ff6600; font-size: 1.1rem;"><i class="fas fa-check-circle"></i></span>
                <span class="text-body fw-medium" style="font-size: 0.95rem;"><?= htmlspecialchars($feat) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- === PREMIUM GALLERY VIEWER === -->
        <div class="mb-4">
          <h3 class="fw-bold mb-4 text-body" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.35rem;">
            <i class="fas fa-images me-2 text-orange"></i>Project Screenshot Gallery
          </h3>
          
          <?php if (!empty($project['images'])): ?>
            <div class="gallery-section">
              <!-- Main Viewer -->
              <div class="gallery-main-viewer" id="galleryMainViewer" onclick="openLightbox(currentGalleryIndex)">
                <img id="galleryMainImage" 
                     src="<?= htmlspecialchars($project['images'][0]['path']) ?>" 
                     alt="<?= htmlspecialchars($project['images'][0]['caption'] ?: $project['title']) ?>">
                
                <!-- Counter badge -->
                <div class="gallery-counter" id="galleryCounter">
                  <i class="fas fa-image me-1" style="font-size:0.65rem;"></i>
                  <span id="galleryCurrentIdx">1</span> / <?= count($project['images']) ?>
                </div>

                <!-- Caption overlay -->
                <div class="gallery-main-caption" id="galleryMainCaption">
                  <?php if (!empty($project['images'][0]['caption'])): ?>
                    <i class="fas fa-camera"></i><?= htmlspecialchars($project['images'][0]['caption']) ?>
                  <?php else: ?>
                    <i class="fas fa-camera"></i><?= htmlspecialchars($project['title']) ?> — Screenshot 1
                  <?php endif; ?>
                </div>

                <!-- Prev/Next Arrows (only show if more than 1 image) -->
                <?php if (count($project['images']) > 1): ?>
                  <div class="gallery-nav-btn gallery-nav-prev" onclick="event.stopPropagation(); changeGallery(-1)">
                    <i class="fas fa-chevron-left"></i>
                  </div>
                  <div class="gallery-nav-btn gallery-nav-next" onclick="event.stopPropagation(); changeGallery(1)">
                    <i class="fas fa-chevron-right"></i>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Thumbnail Strip -->
              <?php if (count($project['images']) > 1): ?>
                <div class="gallery-thumbnails" id="galleryThumbs">
                  <?php foreach ($project['images'] as $i => $img): ?>
                    <div class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>" 
                         id="galleryThumb<?= $i ?>"
                         onclick="setGalleryImage(<?= $i ?>)"
                         title="<?= htmlspecialchars($img['caption'] ?: 'Screenshot ' . ($i + 1)) ?>">
                      <img src="<?= htmlspecialchars($img['path']) ?>" alt="Thumbnail <?= $i + 1 ?>">
                      <span class="gallery-thumb-index"><?= $i + 1 ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Fullscreen Lightbox -->
            <div class="gallery-lightbox" id="galleryLightbox">
              <div class="lightbox-close" onclick="closeLightbox()"><i class="fas fa-times"></i></div>
              <img id="lightboxImage" src="" alt="">
              <div class="lightbox-caption" id="lightboxCaption"></div>
              <?php if (count($project['images']) > 1): ?>
                <div class="lightbox-nav lightbox-prev" onclick="lightboxNav(-1)"><i class="fas fa-chevron-left"></i></div>
                <div class="lightbox-nav lightbox-next" onclick="lightboxNav(1)"><i class="fas fa-chevron-right"></i></div>
              <?php endif; ?>
            </div>

          <?php else: ?>
            <div class="details-card-premium text-center text-muted py-5">
              <i class="fas fa-image fa-3x mb-3" style="color: #cbd5e1;"></i>
              <p>No snapshots or visual boards uploaded for this client file yet.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Sidebar Column (Right) -->
      <div class="col-lg-4">
        <!-- Technical Infrastructure -->
        <div class="sidebar-card-pro">
          <h5 class="fw-bold text-body mb-3" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.05rem;">Technical Stack</h5>
          <div class="stack-pills">
            <?php foreach ($project['tech_stacks'] as $tag): ?>
              <span class="stack-pill-badge"><?= htmlspecialchars($tag) ?></span>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Project Metrics stats -->
        <div class="sidebar-card-pro">
          <h5 class="fw-bold text-body mb-3" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.05rem;">Development Metrics</h5>
          
          <div class="detail-stat-row">
            <span>Development Timeline</span>
            <?php
              $startStr = $project['start_date'] ? date('M Y', strtotime($project['start_date'])) : '-';
              $endStr = $project['end_date'] ? date('M Y', strtotime($project['end_date'])) : '-';
            ?>
            <strong><?= htmlspecialchars($startStr) ?> – <?= htmlspecialchars($endStr) ?></strong>
          </div>
          
          <div class="detail-stat-row">
            <span>Features Engineered</span>
            <strong><?= $project['num_features'] ? htmlspecialchars($project['num_features']) . ' Features' : 'Custom Scale' ?></strong>
          </div>
          
          <div class="detail-stat-row">
            <span>Contract Valuation</span>
            <?php
              $valuation = $project['actual_amount'] ? '₦' . number_format($project['actual_amount'], 0) : '₦ -';
            ?>
            <strong><?= htmlspecialchars($valuation) ?></strong>
          </div>
        </div>

        <!-- Action CTAs -->
        <div class="sidebar-card-pro">
          <h5 class="fw-bold text-body mb-3" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.05rem;">Project Inquiries</h5>
          <div class="d-grid gap-2">
            <?php if (!empty($project['live_url'])): ?>
              <a href="<?= htmlspecialchars($project['live_url']) ?>" target="_blank" class="btn btn-orange py-2.5 rounded-3 fw-bold">
                <i class="fas fa-external-link-alt me-2"></i>Launch Live Application
              </a>
            <?php endif; ?>
            
            <?php if ($project['enable_download'] && !empty($project['download_url'])): ?>
              <a href="<?= htmlspecialchars($project['download_url']) ?>" download class="btn btn-outline-dark py-2.5 rounded-3 fw-bold">
                <i class="fas fa-download me-2"></i>Download Assets
              </a>
            <?php endif; ?>

            <a href="login.php" class="btn btn-outline-secondary py-2.5 rounded-3 fw-bold" style="font-size: 0.85rem;">
              <i class="fas fa-question-circle me-2"></i>Request Similar Project
            </a>
          </div>
        </div>

        <!-- Comments & Client Reviews Panel -->
        <div class="sidebar-card-pro">
          <h5 class="fw-bold text-body mb-3" style="font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.05rem;"><i class="far fa-comments me-2"></i>Reviews &amp; Comments</h5>
          
          <!-- Spinner -->
          <div id="commentsLoading" class="text-center text-muted py-2" style="display:none;">
            <i class="fas fa-spinner fa-spin me-2"></i>Loading reviews...
          </div>
          
          <!-- Feed -->
          <div class="reviews-list-box" id="commentsFeed">
            <!-- Dynamic comments bubbles -->
          </div>

          <!-- Comment submit form -->
          <form id="commentForm" onsubmit="submitComment(event)" class="mt-3">
            <input type="hidden" id="commentProjId" value="<?= $project['id'] ?>">
            <div class="mb-2">
              <input type="text" id="commenterName" class="form-control rounded-3 border text-muted shadow-none" placeholder="Your Name" required style="font-size:0.85rem; padding: 0.5rem 0.75rem;">
            </div>
            <div class="mb-2">
              <textarea id="commentText" class="form-control rounded-3 border text-muted shadow-none" rows="2" placeholder="Write feedback or review..." required style="font-size:0.85rem; padding: 0.5rem 0.75rem; resize: none;"></textarea>
            </div>
            <button type="submit" class="btn btn-orange w-100 rounded-3 py-2 fw-bold" style="font-size:0.82rem;">
              <i class="fas fa-paper-plane me-1"></i>Submit Feedback
            </button>
          </form>
        </div>

      </div>

    </div>
  </div>
</section>

<!-- Gallery JavaScript -->
<script>
  // Gallery images data
  const galleryImages = <?= json_encode(array_map(function($img) {
    return ['src' => $img['path'], 'caption' => $img['caption']];
  }, $project['images'])) ?>;
  const totalImages = galleryImages.length;
  let currentGalleryIndex = 0;

  function setGalleryImage(index) {
    if (index < 0) index = totalImages - 1;
    if (index >= totalImages) index = 0;
    
    currentGalleryIndex = index;
    const img = galleryImages[index];
    
    const mainImg = document.getElementById('galleryMainImage');
    mainImg.style.opacity = '0';
    setTimeout(() => {
      mainImg.src = img.src;
      mainImg.alt = img.caption || 'Screenshot ' + (index + 1);
      mainImg.style.opacity = '1';
    }, 150);

    // Update caption
    const cap = document.getElementById('galleryMainCaption');
    cap.innerHTML = `<i class="fas fa-camera"></i>${img.caption || 'Screenshot ' + (index + 1)}`;

    // Update counter
    document.getElementById('galleryCurrentIdx').textContent = index + 1;

    // Update thumbnails
    document.querySelectorAll('.gallery-thumb').forEach((t, i) => {
      t.classList.toggle('active', i === index);
    });

    // Scroll active thumb into view
    const activeThumb = document.getElementById('galleryThumb' + index);
    if (activeThumb) {
      activeThumb.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }
  }

  function changeGallery(direction) {
    setGalleryImage(currentGalleryIndex + direction);
  }

  // Lightbox
  function openLightbox(index) {
    const lb = document.getElementById('galleryLightbox');
    lb.classList.add('open');
    document.body.style.overflow = 'hidden';
    updateLightbox(index);
  }

  function closeLightbox() {
    document.getElementById('galleryLightbox').classList.remove('open');
    document.body.style.overflow = '';
  }

  function updateLightbox(index) {
    if (index < 0) index = totalImages - 1;
    if (index >= totalImages) index = 0;
    currentGalleryIndex = index;
    const img = galleryImages[index];
    document.getElementById('lightboxImage').src = img.src;
    document.getElementById('lightboxCaption').textContent = img.caption || ('Screenshot ' + (index + 1));
  }

  function lightboxNav(dir) {
    updateLightbox(currentGalleryIndex + dir);
  }

  // Keyboard navigation
  document.addEventListener('keydown', function(e) {
    const lb = document.getElementById('galleryLightbox');
    if (lb && lb.classList.contains('open')) {
      if (e.key === 'ArrowLeft') lightboxNav(-1);
      if (e.key === 'ArrowRight') lightboxNav(1);
      if (e.key === 'Escape') closeLightbox();
    }
  });

  // Close lightbox on backdrop click
  document.getElementById('galleryLightbox')?.addEventListener('click', function(e) {
    if (e.target === this) closeLightbox();
  });
</script>

<!-- Reviews JS Logic -->
<script>
  function loadComments(projId) {
    const feed = document.getElementById('commentsFeed');
    const loading = document.getElementById('commentsLoading');
    feed.innerHTML = "";
    loading.style.display = "block";

    fetch(`server.php?action=get_comments&project_id=${projId}`)
      .then(res => res.json())
      .then(comments => {
        loading.style.display = "none";
        if (comments && comments.length > 0) {
          comments.forEach(c => {
            const div = document.createElement('div');
            div.className = 'review-bubble-item';
            div.innerHTML = `
              <div class="meta">
                <strong>${escapeHtml(c.commenter_name)}</strong>
                <span>Review</span>
              </div>
              <div class="text-muted" style="font-size:0.8rem; line-height:1.5;">${escapeHtml(c.comment)}</div>
            `;
            feed.appendChild(div);
          });
        } else {
          feed.innerHTML = `<div class="text-center text-muted py-3 small" style="font-size: 0.8rem;">No client reviews submitted yet.</div>`;
        }
      })
      .catch(() => {
        loading.style.display = "none";
        feed.innerHTML = `<div class="text-danger small text-center py-2">Failed to load reviews.</div>`;
      });
  }

  function submitComment(e) {
    e.preventDefault();
    const projId = document.getElementById('commentProjId').value;
    const name = document.getElementById('commenterName').value.trim();
    const text = document.getElementById('commentText').value.trim();

    if (!name || !text) return;

    fetch('server.php?action=add_comment', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        project_id: projId,
        commenter_name: name,
        comment: text
      })
    })
    .then(res => res.json())
    .then(res => {
      if (res.success) {
        document.getElementById('commentText').value = "";
        loadComments(projId);
      }
    });
  }

  function escapeHtml(str) {
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }

  // Initialize
  window.addEventListener('load', () => {
    loadComments(<?= $project['id'] ?>);
  });
</script>

<?php
require_once __DIR__ . '/includes/public_footer.php';
?>
