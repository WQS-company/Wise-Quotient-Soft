<?php
if (session_status() === PHP_SESSION_NONE) session_start();

class AdPlacer {
    private static $instance = null;
    private $pdo;
    private $userId;
    private $userRole;
    private $device;
    private $pathToRoot;

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        global $pdo;
        $this->pdo = $pdo;
        $this->userId = $_SESSION['user']['id'] ?? null;
        $this->userRole = $_SESSION['user']['role'] ?? 'guest';
        $this->device = $this->detectDevice();
        $this->pathToRoot = $this->detectPath();
    }

    private function detectDevice() {
        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false) return 'mobile';
        if (strpos($ua, 'tablet') !== false || strpos($ua, 'ipad') !== false) return 'tablet';
        return 'desktop';
    }

    private function detectPath() {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($script, '/admin/') !== false) return '../';
        if (strpos($script, '/user/') !== false) return '../';
        return './';
    }

    public function getAds($placement, $limit = 5) {
        $now = date('Y-m-d H:i:s');
        $roleMap = ['guest'=>['all','guests'],'user'=>['all','users'],'developer'=>['all','developers'],'partner'=>['all','partners'],'agent'=>['all','agents'],'admin'=>['all','admins']];
        $targets = $roleMap[$this->userRole] ?? ['all'];
        $tPh = implode(',', array_fill(0, count($targets), '?'));
        $sql = "SELECT * FROM ads WHERE run_status=1 AND is_active=1 AND (start_date IS NULL OR start_date<=?) AND (end_date IS NULL OR end_date>=?) AND (max_views IS NULL OR total_views<max_views) AND (placement=? OR placement='all_pages') AND target_audience IN ($tPh) AND (device_target='all' OR device_target=?) ORDER BY featured DESC, priority ASC, created_at DESC LIMIT $limit";
        $params = array_merge([$now, $now, $placement], $targets, [$this->device]);
        try { $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC); }
        catch (Exception $e) { return []; }
    }

    public function getAdsByDisplayType($displayType, $limit = 5) {
        $now = date('Y-m-d H:i:s');
        $roleMap = ['guest'=>['all','guests'],'user'=>['all','users'],'developer'=>['all','developers'],'partner'=>['all','partners'],'agent'=>['all','agents'],'admin'=>['all','admins']];
        $targets = $roleMap[$this->userRole] ?? ['all'];
        $tPh = implode(',', array_fill(0, count($targets), '?'));
        $sql = "SELECT * FROM ads WHERE run_status=1 AND is_active=1 AND display_type=? AND (start_date IS NULL OR start_date<=?) AND (end_date IS NULL OR end_date>=?) AND (max_views IS NULL OR total_views<max_views) AND target_audience IN ($tPh) AND (device_target='all' OR device_target=?) ORDER BY featured DESC, priority ASC, created_at DESC LIMIT $limit";
        $params = array_merge([$displayType, $now, $now], $targets, [$this->device]);
        try { $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC); }
        catch (Exception $e) { return []; }
    }

    public function render($placement, $options = []) {
        $ads = $this->getAds($placement, $options['limit'] ?? 5);
        if (empty($ads)) return '';
        $html = '';
        foreach ($ads as $ad) $html .= $this->renderAd($ad, $placement);
        return $html;
    }

    public function getAdCode($placement, $options = []) {
        return $this->render($placement, $options);
    }

    private function renderAd($ad, $placement) {
        $p = $this->pathToRoot;
        $id = (int)$ad['id'];
        $title = htmlspecialchars($ad['title']);
        $headline = htmlspecialchars($ad['headline'] ?? '');
        $subtitle = htmlspecialchars($ad['subtitle'] ?? '');
        $desc = htmlspecialchars($ad['description'] ?? '');
        $img = htmlspecialchars($ad['image_url'] ?? '');
        if ($img && !preg_match('/^https?:\/\//i', $img) && !preg_match('/^data:/i', $img) && !str_starts_with($img, '../')) {
            $img = $p . $img;
        }
        $bgImg = htmlspecialchars($ad['background_image'] ?? '');
        if ($bgImg && !preg_match('/^https?:\/\//i', $bgImg) && !preg_match('/^data:/i', $bgImg) && !str_starts_with($bgImg, '../')) {
            $bgImg = $p . $bgImg;
        }
        $btnText = htmlspecialchars($ad['button_text'] ?? 'Learn More');
        $btnUrl = htmlspecialchars($ad['button_url'] ?? '#');
        $pc = htmlspecialchars($ad['primary_color'] ?? '#10b981');
        $sc = htmlspecialchars($ad['secondary_color'] ?? '#059669');
        $tc = htmlspecialchars($ad['text_color'] ?? '#ffffff');
        $dt = $ad['display_type'] ?? 'modal';
        if (strpos($placement, 'hero') !== false) $dt = 'hero_banner';
        elseif (strpos($placement, 'sidebar') !== false) $dt = 'side_panel';
        elseif (strpos($placement, 'top') !== false) $dt = 'top_bar';
        elseif (strpos($placement, 'bottom') !== false) $dt = 'bottom_bar';
        elseif ($placement === 'popup_ad') $dt = 'modal';
        elseif ($placement === 'floating_ad') $dt = 'floating';
        $tUrl = $p . 'api/ad_management_api.php';
        $safePlacement = htmlspecialchars($placement);

        $html = '<div class="wqs-ad-slot" data-ad-id="'.$id.'" data-placement="'.$safePlacement.'" style="margin:0.5rem 0;">';

        if ($dt === 'hero_banner') {
            $bgStyle = $bgImg ? 'background-image:url('.$bgImg.');background-size:cover;background-position:center;' : '';
            $html .= '<div style="position:relative;border-radius:16px;overflow:hidden;min-height:300px;background:linear-gradient(135deg,'.$pc.','.$sc.');'.$bgStyle.'">';
            if ($bgImg) $html .= '<div style="position:absolute;inset:0;background:linear-gradient(135deg,'.$pc.'cc,'.$sc.'cc);"></div>';
            $html .= '<div style="position:relative;z-index:1;padding:3rem 2.5rem;color:'.$tc.';">';
            if ($headline) $html .= '<h2 style="font-size:1.8rem;font-weight:800;margin-bottom:0.5rem;">'.$headline.'</h2>';
            if ($subtitle) $html .= '<p style="font-size:1.1rem;opacity:.9;margin-bottom:0.5rem;">'.$subtitle.'</p>';
            if ($desc) $html .= '<p style="font-size:0.9rem;opacity:.8;max-width:600px;margin-bottom:1.5rem;">'.$desc.'</p>';
            if ($btnText && $btnUrl !== '#') $html .= '<a href="'.$btnUrl.'" class="wqs-ad-click" data-id="'.$id.'" data-placement="'.$safePlacement.'" style="display:inline-flex;align-items:center;gap:8px;padding:12px 32px;border-radius:50px;background:white;color:'.$pc.';font-weight:700;font-size:0.9rem;text-decoration:none;">'.$btnText.' <i class="fas fa-arrow-right"></i></a>';
            $html .= '</div>';
            if ($img) $html .= '<div style="position:absolute;right:2rem;top:50%;transform:translateY(-50%);z-index:1;"><img src="'.$img.'" alt="'.$title.'" style="max-height:200px;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.2);"></div>';
            $html .= '</div>';
        } elseif ($dt === 'top_bar' || $dt === 'bottom_bar') {
            $br = $dt === 'top_bar' ? '12px 12px 0 0' : '0 0 12px 12px';
            $html .= '<div style="background:linear-gradient(135deg,'.$pc.','.$sc.');color:'.$tc.';padding:10px 20px;border-radius:'.$br.';display:flex;align-items:center;justify-content:center;gap:1rem;flex-wrap:wrap;">';
            if ($img) $html .= '<img src="'.$img.'" alt="" style="height:30px;border-radius:6px;">';
            if ($headline) $html .= '<strong style="font-size:.88rem;">'.$headline.'</strong>';
            if ($desc) $html .= '<span style="font-size:.8rem;opacity:.9;">'.$desc.'</span>';
            if ($btnText && $btnUrl !== '#') $html .= '<a href="'.$btnUrl.'" class="wqs-ad-click" data-id="'.$id.'" data-placement="'.$safePlacement.'" style="padding:4px 16px;border-radius:50px;background:rgba(255,255,255,.2);color:white;font-weight:600;font-size:.75rem;text-decoration:none;">'.$btnText.'</a>';
            $html .= '</div>';
        } elseif ($dt === 'side_panel') {
            $html .= '<div style="background:var(--color-card-bg,#fff);border:1px solid var(--color-border,#e2e8f0);border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.04);">';
            if ($img) $html .= '<div style="height:120px;overflow:hidden;"><img src="'.$img.'" alt="'.$title.'" style="width:100%;height:100%;object-fit:cover;"></div>';
            $html .= '<div style="padding:1rem;">';
            if ($headline) $html .= '<h6 style="font-weight:700;font-size:.88rem;margin-bottom:.3rem;">'.$headline.'</h6>';
            if ($desc) $html .= '<p style="font-size:.78rem;color:#64748b;margin-bottom:.75rem;">'.$desc.'</p>';
            if ($btnText && $btnUrl !== '#') $html .= '<a href="'.$btnUrl.'" class="wqs-ad-click" data-id="'.$id.'" data-placement="'.$safePlacement.'" style="display:inline-flex;align-items:center;gap:4px;padding:6px 16px;border-radius:50px;background:linear-gradient(135deg,'.$pc.','.$sc.');color:white;font-weight:600;font-size:.75rem;text-decoration:none;">'.$btnText.'</a>';
            $html .= '</div></div>';
        } elseif ($dt === 'modal') {
            $html .= '<div class="wqs-ad-modal" data-ad-id="'.$id.'" style="position:fixed;inset:0;z-index:9999;display:none;align-items:center;justify-content:center;">';
            $html .= '<div onclick="this.parentElement.style.display=\'none\'" style="position:absolute;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);"></div>';
            $html .= '<div style="position:relative;z-index:1;background:white;border-radius:20px;max-width:480px;width:92%;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.2);">';
            $html .= '<button onclick="this.closest(\'.wqs-ad-modal\').style.display=\'none\'" style="position:absolute;top:12px;right:12px;z-index:2;width:30px;height:30px;border-radius:50%;border:none;background:rgba(0,0,0,.06);cursor:pointer;font-size:1.1rem;display:flex;align-items:center;justify-content:center;">&times;</button>';
            if ($img) $html .= '<div style="height:200px;overflow:hidden;"><img src="'.$img.'" alt="'.$title.'" style="width:100%;height:100%;object-fit:cover;"></div>';
            $html .= '<div style="padding:1.5rem;">';
            if ($headline) $html .= '<h4 style="font-weight:800;margin-bottom:.3rem;">'.$headline.'</h4>';
            if ($subtitle) $html .= '<p style="color:#64748b;font-size:.88rem;margin-bottom:.75rem;">'.$subtitle.'</p>';
            if ($btnText && $btnUrl !== '#') $html .= '<a href="'.$btnUrl.'" class="wqs-ad-click" data-id="'.$id.'" data-placement="'.$safePlacement.'" style="display:inline-flex;align-items:center;gap:6px;padding:10px 28px;border-radius:50px;background:linear-gradient(135deg,'.$pc.','.$sc.');color:white;font-weight:700;font-size:.88rem;text-decoration:none;">'.$btnText.'</a>';
            $html .= '</div></div></div>';
        } elseif ($dt === 'floating') {
            $html .= '<div class="wqs-ad-float" style="position:fixed;bottom:80px;right:20px;z-index:9998;max-width:300px;background:white;border-radius:16px;box-shadow:0 8px 32px rgba(0,0,0,.15);overflow:hidden;">';
            $html .= '<button onclick="this.closest(\'.wqs-ad-float\').remove()" style="position:absolute;top:6px;right:6px;z-index:2;width:24px;height:24px;border-radius:50%;border:none;background:rgba(0,0,0,.06);cursor:pointer;font-size:.8rem;">&times;</button>';
            if ($img) $html .= '<div style="height:120px;overflow:hidden;"><img src="'.$img.'" alt="'.$title.'" style="width:100%;height:100%;object-fit:cover;"></div>';
            $html .= '<div style="padding:.8rem;">';
            if ($headline) $html .= '<strong style="font-size:.82rem;">'.$headline.'</strong>';
            if ($btnText && $btnUrl !== '#') $html .= '<a href="'.$btnUrl.'" class="wqs-ad-click" data-id="'.$id.'" data-placement="'.$safePlacement.'" style="display:block;text-align:center;padding:6px;border-radius:8px;background:linear-gradient(135deg,'.$pc.','.$sc.');color:white;font-weight:600;font-size:.75rem;text-decoration:none;margin-top:.5rem;">'.$btnText.'</a>';
            $html .= '</div></div>';
        } else {
            $html .= '<div style="background:linear-gradient(135deg,'.$pc.'11,'.$sc.'11);border:1px solid '.$pc.'22;border-radius:14px;padding:1.25rem;display:flex;align-items:center;gap:1.25rem;flex-wrap:wrap;">';
            if ($img) $html .= '<div style="flex-shrink:0;"><img src="'.$img.'" alt="'.$title.'" style="width:120px;height:80px;object-fit:cover;border-radius:10px;"></div>';
            $html .= '<div style="flex:1;min-width:200px;">';
            if ($headline) $html .= '<h5 style="font-weight:700;margin-bottom:.25rem;">'.$headline.'</h5>';
            if ($desc) $html .= '<p style="font-size:.85rem;color:#64748b;margin-bottom:.5rem;">'.$desc.'</p>';
            if ($btnText && $btnUrl !== '#') $html .= '<a href="'.$btnUrl.'" class="wqs-ad-click" data-id="'.$id.'" data-placement="'.$safePlacement.'" style="display:inline-flex;align-items:center;gap:4px;padding:6px 20px;border-radius:50px;background:linear-gradient(135deg,'.$pc.','.$sc.');color:white;font-weight:600;font-size:.8rem;text-decoration:none;">'.$btnText.'</a>';
            $html .= '</div></div>';
        }

        $html .= '</div>';

        $html .= '<script>(function(){';
        $html .= 'document.querySelectorAll(".wqs-ad-click[data-id=\''.$id.'\']").forEach(function(el){el.addEventListener("click",function(){fetch("'.$tUrl.'",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},"body":"action=track&ad_id='.$id.'&event_type=click&placement="+encodeURIComponent("'.$safePlacement.'")}).catch(function(){});});});';
        $html .= 'var slot=document.querySelector(".wqs-ad-slot[data-ad-id=\''.$id.'\']");if(slot){var obs=new IntersectionObserver(function(entries){entries.forEach(function(e){if(e.isIntersecting){fetch("'.$tUrl.'",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},"body":"action=track&ad_id='.$id.'&event_type=view&placement="+encodeURIComponent("'.$safePlacement.'")}).catch(function(){});obs.disconnect();}});},{threshold:0.5});obs.observe(slot);}';
        $html .= '})();</script>';

        return $html;
    }
}
