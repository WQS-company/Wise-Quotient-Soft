<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Compute web-relative path for JavaScript fetch URLs
$_bannerScript = $_SERVER['SCRIPT_NAME'] ?? '/';
if (strpos($_bannerScript, '/admin/') !== false) {
    $_bannerWebPath = '../';
} elseif (strpos($_bannerScript, '/user/') !== false) {
    $_bannerWebPath = '../';
} else {
    $_bannerWebPath = './';
}

$bannerAds = [];

try {
    if (class_exists('AdPlacer')) {
        $bannerAds = AdPlacer::getInstance()->getAdsByDisplayType('hero_banner', 5);
    }
} catch (Exception $e) { $bannerAds = []; }

if (empty($bannerAds)) return;
$total = count($bannerAds);
?>
<style>
@keyframes techPulse {
    0%, 100% { opacity: 0.15; transform: scale(1); }
    50% { opacity: 0.3; transform: scale(1.05); }
}
@keyframes techScan {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(100%); }
}
@keyframes floatDot {
    0%, 100% { transform: translateY(0) scale(1); opacity: 0; }
    10% { opacity: 0.6; }
    90% { opacity: 0.1; }
    50% { transform: translateY(-30px) scale(0.5); }
}
@keyframes borderGlow {
    0%, 100% { border-color: rgba(59,130,246,0.3); box-shadow: 0 0 20px rgba(59,130,246,0.05); }
    50% { border-color: rgba(139,92,246,0.4); box-shadow: 0 0 40px rgba(139,92,246,0.1); }
}
@keyframes btnPulse {
    0%, 100% { box-shadow: 0 0 12px rgba(59,130,246,0.3); }
    50% { box-shadow: 0 0 28px rgba(59,130,246,0.6); }
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes slideUpContent {
    0% { opacity: 0; transform: translateY(20px); }
    100% { opacity: 1; transform: translateY(0); }
}
.tech-banner {
    position: relative; width: 100%; overflow: hidden;
    border-radius: 20px; margin-bottom: 2rem;
    background: #0b1120;
    animation: borderGlow 4s ease-in-out infinite;
}
.tech-banner::before {
    content: ''; position: absolute; inset: 0;
    background:
        linear-gradient(90deg, rgba(59,130,246,0.03) 1px, transparent 1px),
        linear-gradient(rgba(59,130,246,0.03) 1px, transparent 1px);
    background-size: 40px 40px;
    z-index: 1; pointer-events: none;
}
.tech-banner::after {
    content: ''; position: absolute; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, transparent, rgba(59,130,246,0.3), rgba(139,92,246,0.3), transparent);
    top: 0; z-index: 2; animation: techScan 6s linear infinite; pointer-events: none;
}
.tech-glow {
    position: absolute; width: 300px; height: 300px; border-radius: 50%;
    filter: blur(80px); pointer-events: none; z-index: 0;
}
.tech-glow-1 { top: -100px; right: -50px; background: rgba(59,130,246,0.12); animation: techPulse 5s ease-in-out infinite; }
.tech-glow-2 { bottom: -100px; left: -50px; background: rgba(139,92,246,0.1); animation: techPulse 7s ease-in-out infinite 1s; }
.tech-dots {
    position: absolute; inset: 0; overflow: hidden; z-index: 1; pointer-events: none;
}
.tech-dots span {
    position: absolute; width: 3px; height: 3px; border-radius: 50%;
    background: rgba(59,130,246,0.4); animation: floatDot 8s linear infinite;
}
.tech-dots span:nth-child(1) { left: 10%; top: 60%; animation-delay: 0s; width: 2px; height: 2px; }
.tech-dots span:nth-child(2) { left: 25%; top: 30%; animation-delay: 1.2s; }
.tech-dots span:nth-child(3) { left: 45%; top: 70%; animation-delay: 2.5s; width: 4px; height: 4px; background: rgba(139,92,246,0.3); }
.tech-dots span:nth-child(4) { left: 65%; top: 20%; animation-delay: 0.8s; }
.tech-dots span:nth-child(5) { left: 80%; top: 50%; animation-delay: 3.2s; width: 2px; height: 2px; }
.tech-dots span:nth-child(6) { left: 55%; top: 85%; animation-delay: 1.8s; background: rgba(59,130,246,0.25); }
.tech-dots span:nth-child(7) { left: 15%; top: 10%; animation-delay: 4s; width: 4px; height: 4px; }
.tech-dots span:nth-child(8) { left: 90%; top: 75%; animation-delay: 0.5s; background: rgba(139,92,246,0.2); }

.tech-slide {
    display: none; width: 100%; position: relative; z-index: 2;
}
.tech-slide.active { display: block; }
.tech-slide.active .tech-img { animation: slideUp 0.6s cubic-bezier(0.16,1,0.3,1); }
.tech-slide.active .tech-overlay { animation: slideUpContent 0.6s cubic-bezier(0.16,1,0.3,1) 0.2s both; }

.tech-img {
    width: 100%; display: block; max-height: 520px;
    object-fit: cover; border-radius: 20px;
}

.tech-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(135deg, rgba(11,17,32,0.75) 0%, rgba(11,17,32,0.3) 50%, transparent 100%);
    display: flex; flex-direction: column; justify-content: flex-end;
    padding: 3rem; border-radius: 20px;
}
.tech-badge {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(59,130,246,0.15); backdrop-filter: blur(8px);
    border: 1px solid rgba(59,130,246,0.2); border-radius: 50px;
    padding: 4px 14px; margin-bottom: 0.75rem; width: fit-content;
    font-size: 0.7rem; font-weight: 600; letter-spacing: 0.5px;
    text-transform: uppercase; color: rgba(255,255,255,0.7);
}
.tech-badge .badge-dot {
    width: 6px; height: 6px; border-radius: 50%; background: #22c55e;
    animation: btnPulse 2s ease-in-out infinite;
}
.tech-overlay h2 {
    font-size: clamp(1.4rem, 3vw, 2.2rem); font-weight: 800;
    color: white; margin-bottom: 0.3rem; line-height: 1.15;
    text-shadow: 0 2px 12px rgba(0,0,0,0.3);
}
.tech-overlay h2 .highlight {
    background: linear-gradient(135deg, #60a5fa, #a78bfa);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.tech-overlay p {
    font-size: clamp(0.85rem, 1.2vw, 1rem); color: rgba(255,255,255,0.75);
    margin-bottom: 1rem; max-width: 600px;
    text-shadow: 0 1px 6px rgba(0,0,0,0.2);
}
.tech-cta {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 28px; border-radius: 50px; text-decoration: none;
    font-weight: 700; font-size: 0.85rem; color: white;
    transition: all 0.3s ease; width: fit-content;
    animation: btnPulse 3s ease-in-out infinite;
}
.tech-cta:hover { transform: translateY(-2px) scale(1.03); color: white; }
.tech-cta i { font-size: 0.75rem; transition: transform 0.3s ease; }
.tech-cta:hover i { transform: translateX(4px); }

.tech-dots-nav {
    position: absolute; bottom: 16px; right: 24px; z-index: 5;
    display: flex; gap: 8px;
}
.tech-dots-nav button {
    width: 10px; height: 10px; border-radius: 50%; border: none; cursor: pointer; padding: 0;
    background: rgba(255,255,255,0.2); transition: all 0.4s ease; position: relative;
}
.tech-dots-nav button.active {
    background: #60a5fa; width: 28px; border-radius: 5px;
    box-shadow: 0 0 12px rgba(96,165,250,0.4);
}
.tech-arrows {
    position: absolute; top: 50%; transform: translateY(-50%); z-index: 5;
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(11,17,32,0.5); backdrop-filter: blur(8px);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.7); font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: all 0.3s ease; opacity: 0;
}
.tech-banner:hover .tech-arrows { opacity: 1; }
.tech-arrows:hover { background: rgba(59,130,246,0.3); border-color: rgba(59,130,246,0.3); color: white; transform: translateY(-50%) scale(1.1); }
.tech-prev { left: 14px; }
.tech-next { right: 14px; }

@media (max-width: 768px) {
    .tech-overlay { padding: 1.5rem; justify-content: flex-end; }
    .tech-overlay h2 { font-size: 1.2rem; }
    .tech-overlay p { font-size: 0.8rem; margin-bottom: 0.5rem; }
    .tech-cta { padding: 8px 20px; font-size: 0.75rem; }
    .tech-dots-nav { bottom: 10px; right: 14px; }
    .tech-dots-nav button { width: 8px; height: 8px; }
    .tech-dots-nav button.active { width: 20px; }
    .tech-banner { border-radius: 14px; }
    .tech-img { border-radius: 14px; max-height: 320px; }
}
</style>

<div class="tech-banner">
    <div class="tech-glow tech-glow-1"></div>
    <div class="tech-glow tech-glow-2"></div>
    <div class="tech-dots">
        <span></span><span></span><span></span><span></span>
        <span></span><span></span><span></span><span></span>
    </div>

    <?php foreach ($bannerAds as $i => $ad): ?>
    <div class="tech-slide <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>" data-id="<?= $ad['id'] ?>">
        <?php if ($ad['image_url']): 
            $adImgSrc = $ad['image_url'];
            if ($adImgSrc && !preg_match('/^https?:\/\//i', $adImgSrc) && !preg_match('/^data:/i', $adImgSrc) && !str_starts_with($adImgSrc, '../')) {
                $adImgSrc = $_bannerWebPath . $adImgSrc;
            }
        ?>
            <img src="<?= htmlspecialchars($adImgSrc) ?>" class="tech-img" alt="<?= htmlspecialchars($ad['title']) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
        <?php else: ?>
            <div class="tech-img" style="min-height:360px;background:linear-gradient(135deg,<?= $ad['primary_color'] ?>,<?= $ad['secondary_color'] ?>);border-radius:20px;"></div>
        <?php endif; ?>

        <?php if ($ad['headline']): ?>
        <div class="tech-overlay">
            <div class="tech-badge"><span class="badge-dot"></span> Promotional</div>
            <h2><?= htmlspecialchars($ad['headline']) ?> <?php if ($ad['subtitle']): ?><span class="highlight">— <?= htmlspecialchars($ad['subtitle']) ?></span><?php endif; ?></h2>
            <?php if ($ad['description']): ?><p><?= htmlspecialchars($ad['description']) ?></p><?php endif; ?>
            <?php
            $ad_button_url = $ad['button_url'] ?: '#';
            if ($ad_button_url !== '#' && !preg_match('/^(https?:\/\/|mailto:|tel:|\/|#)/i', $ad_button_url)) {
                $ad_button_url = (isset($path_to_root) ? $path_to_root : './') . $ad_button_url;
            }
            ?>
            <a href="<?= htmlspecialchars($ad_button_url) ?>" class="tech-cta" style="background:linear-gradient(135deg,<?= $ad['primary_color'] ?>,<?= $ad['secondary_color'] ?>);" onclick="trackBannerClick(<?= $ad['id'] ?>)"><?= htmlspecialchars($ad['button_text'] ?: 'Learn More') ?> <i class="fas fa-arrow-right"></i></a>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if ($total > 1): ?>
    <div class="tech-dots-nav">
        <?php for ($i = 0; $i < $total; $i++): ?>
        <button class="<?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>" onclick="goToBanner(<?= $i ?>)"></button>
        <?php endfor; ?>
    </div>
    <button class="tech-arrows tech-prev" onclick="prevBanner()"><i class="fas fa-chevron-left"></i></button>
    <button class="tech-arrows tech-next" onclick="nextBanner()"><i class="fas fa-chevron-right"></i></button>
    <?php endif; ?>
</div>

<script>
let bannerIndex = 0, bannerTotal = <?= $total ?>, bannerTimer = null;
let bannerIntervals = [<?php foreach ($bannerAds as $ad) { echo max(2, (int)($ad['slider_interval'] ?? 5)) . ','; } ?>];

function showBanner(idx) {
    const slides = document.querySelectorAll('.tech-slide');
    const dots = document.querySelectorAll('.tech-dots-nav button');
    slides.forEach((s, i) => s.classList.toggle('active', i === idx));
    dots.forEach((d, i) => d.classList.toggle('active', i === idx));
    const slide = slides[idx];
    if (slide) trackBannerView(slide.dataset.id);
}
function nextBanner() { bannerIndex = (bannerIndex + 1) % bannerTotal; showBanner(bannerIndex); resetBannerTimer(); }
function prevBanner() { bannerIndex = (bannerIndex - 1 + bannerTotal) % bannerTotal; showBanner(bannerIndex); resetBannerTimer(); }
function goToBanner(idx) { bannerIndex = idx; showBanner(bannerIndex); resetBannerTimer(); }
function resetBannerTimer() { if (bannerTimer) clearInterval(bannerTimer); bannerTimer = setInterval(nextBanner, (bannerIntervals[bannerIndex] || 5) * 1000); }
function trackBannerView(adId) { fetch('<?= $_bannerWebPath ?>api/track_ad.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'view', ad_id:adId}) }).catch(()=>{}); }
function trackBannerClick(adId) { fetch('<?= $_bannerWebPath ?>api/track_ad.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({action:'click', ad_id:adId}) }).catch(()=>{}); }
document.addEventListener('DOMContentLoaded', function() {
    if (bannerTotal > 1) bannerTimer = setInterval(nextBanner, (bannerIntervals[0] || 5) * 1000);
    const first = document.querySelector('.tech-slide.active');
    if (first) trackBannerView(first.dataset.id);
});
</script>
