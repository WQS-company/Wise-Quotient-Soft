<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$_adScript = $_SERVER['SCRIPT_NAME'] ?? '/';
if (strpos($_adScript, '/admin/') !== false) {
    $_adWebPath = '../';
} elseif (strpos($_adScript, '/user/') !== false) {
    $_adWebPath = '../';
} else {
    $_adWebPath = './';
}

$userIdForAds = $_SESSION['user']['id'] ?? null;
$userRoleForAds = null;
$userAdsEnabled = true;
$activeAds = [];
$closedAds = $_SESSION['closed_ads'] ?? [];

try {
    if ($userIdForAds) {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userIdForAds]);
        $userRoleForAds = $stmt->fetchColumn();

        $checkTable = $pdo->query("SHOW TABLES LIKE 'user_notification_settings'");
        if ($checkTable->fetch()) {
            $checkSettings = $pdo->prepare("SELECT id FROM user_notification_settings WHERE user_id = ?");
            $checkSettings->execute([$userIdForAds]);
            if (!$checkSettings->fetch()) {
                $pdo->prepare("INSERT INTO user_notification_settings (user_id) VALUES (?)")->execute([$userIdForAds]);
            }
            $prefsStmt = $pdo->prepare("SELECT enable_ads FROM user_notification_settings WHERE user_id = ?");
            $prefsStmt->execute([$userIdForAds]);
            $prefs = $prefsStmt->fetch(PDO::FETCH_ASSOC);
            $userAdsEnabled = $prefs ? (bool)$prefs['enable_ads'] : true;
        }

        $userPrefs = [];
        try {
            $prefStmt = $pdo->prepare("SELECT preferences_json FROM user_preferences WHERE user_id = ? AND survey_completed = 1");
            $prefStmt->execute([$userIdForAds]);
            $prefRow = $prefStmt->fetch(PDO::FETCH_ASSOC);
            if ($prefRow && $prefRow['preferences_json']) {
                $userPrefs = json_decode($prefRow['preferences_json'], true) ?: [];
            }
        } catch (Exception $e) { /* fail-safe */ }
    }

    if ($userAdsEnabled && class_exists('AdPlacer')) {
        $allCandidateAds = AdPlacer::getInstance()->getAdsByDisplayType('modal', 10);

        if (!empty($userPrefs) && !empty($allCandidateAds)) {
            $scoredAds = [];
            $industry = strtolower($userPrefs['what_industry_are_you_in'] ?? '');
            $softwareType = strtolower($userPrefs['what_type_of_software_are_you_looking_for'] ?? '');
            $budget = strtolower($userPrefs['what_is_your_estimated_budget_range'] ?? '');
            $role = strtolower($userPrefs['what_best_describes_your_role'] ?? '');
            $timeline = strtolower($userPrefs['what_is_your_timeline_expectation'] ?? '');

            foreach ($allCandidateAds as $ad) {
                $score = 0;
                $searchText = strtolower(($ad['title'] ?? '') . ' ' . ($ad['description'] ?? '') . ' ' . ($ad['headline'] ?? '') . ' ' . ($ad['subtitle'] ?? ''));

                if ($industry) {
                    $industryKeywords = [
                        'fintech' => ['fintech','finance','payment','security','compliance','banking','transaction'],
                        'e-commerce' => ['e-commerce','ecommerce','retail','shop','store','inventory','checkout'],
                        'education' => ['education','elearning','lms','learning','school','portal','student'],
                        'healthcare' => ['healthcare','health','medical','telemedicine','hipaa','patient','clinic'],
                        'real estate' => ['real estate','property','propTech','rental','mortgage','tenant'],
                    ];
                    foreach ($industryKeywords as $key => $kws) {
                        if (strpos($industry, $key) !== false || strpos($key, $industry) !== false) {
                            foreach ($kws as $kw) {
                                if (strpos($searchText, $kw) !== false) { $score += 5; break; }
                            }
                        }
                    }
                    if (strpos($searchText, $industry) !== false) $score += 8;
                }

                if ($softwareType && strpos($searchText, $softwareType) !== false) $score += 8;

                if ($budget) {
                    $budgetKeywords = [
                        'low' => ['affordable','budget','starter','value','cost-effective','basic'],
                        'mid' => ['mid','standard','balanced','custom','flexible'],
                        'high' => ['premium','enterprise','dedicated','white-glove','end-to-end','enterprise-grade'],
                    ];
                    foreach ($budgetKeywords as $level => $kws) {
                        if (strpos($budget, $level) !== false) {
                            foreach ($kws as $kw) {
                                if (strpos($searchText, $kw) !== false) { $score += 5; break; }
                            }
                        }
                    }
                }

                if ($role) {
                    if (strpos($role, 'startup') !== false && (strpos($searchText, 'mvp') !== false || strpos($searchText, 'startup') !== false)) $score += 6;
                    if (strpos($role, 'business') !== false && (strpos($searchText, 'roi') !== false || strpos($searchText, 'business') !== false || strpos($searchText, 'growth') !== false)) $score += 6;
                    if (strpos($role, 'developer') !== false && (strpos($searchText, 'api') !== false || strpos($searchText, 'scalab') !== false || strpos($searchText, 'code') !== false)) $score += 6;
                    if (strpos($role, 'executive') !== false && (strpos($searchText, 'roi') !== false || strpos($searchText, 'enterprise') !== false)) $score += 6;
                }

                if ($timeline) {
                    if (strpos($timeline, 'asap') !== false || strpos($timeline, 'urgent') !== false) {
                        if (strpos($searchText, 'weeks') !== false || strpos($searchText, 'fast') !== false || strpos($searchText, 'rapid') !== false) $score += 5;
                    }
                    if ((strpos($timeline, 'month') !== false) && (strpos($searchText, 'agile') !== false || strpos($searchText, 'sprint') !== false)) $score += 3;
                }

                if (!empty($ad['generation_context'])) {
                    $genCtx = @json_decode($ad['generation_context'], true);
                    if (!empty($genCtx)) {
                        foreach ($userPrefs as $uk => $uv) {
                            if (isset($genCtx[$uk]) && strtolower($genCtx[$uk]) === strtolower($uv)) {
                                $score += 10;
                            }
                        }
                    }
                }

                if ($ad['priority']) $score += (int)$ad['priority'];
                $scoredAds[] = ['ad' => $ad, 'score' => $score];
            }

            usort($scoredAds, fn($a, $b) => $b['score'] - $a['score']);
            $activeAds = array_map(fn($sa) => $sa['ad'], array_slice($scoredAds, 0, 3));
        } else {
            $activeAds = array_slice($allCandidateAds, 0, 3);
        }
    }
} catch (Exception $e) {
    $activeAds = [];
    $userAdsEnabled = true;
}
?>

<style>
.wqs-ad-modal-backdrop {
    position: fixed; inset: 0; background: rgba(0,0,0,0.55);
    backdrop-filter: blur(8px); display: none; justify-content: center;
    align-items: center; z-index: 9999;
    opacity: 0; transition: opacity 0.3s ease;
}
.wqs-ad-modal-backdrop.show { display: flex; opacity: 1; }
.wqs-ad-modal-backdrop.wqs-fade-out { opacity: 0; }

.wqs-ad-modal {
    max-width: 760px; width: 92%; border-radius: 24px;
    overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.35);
    position: relative;
    transform: translateY(0) scale(1); opacity: 1;
    transition: transform 0.35s cubic-bezier(0.4,0,0.2,1), opacity 0.3s ease;
    display: flex;
    flex-direction: row;
    align-items: stretch;
}
.wqs-ad-modal-backdrop.wqs-fade-out .wqs-ad-modal {
    transform: translateY(30px) scale(0.95); opacity: 0;
}

/* Light theme defaults */
.wqs-ad-modal.wqs-ad-light {
    background: #ffffff;
    color: #1e293b;
}
.wqs-ad-modal.wqs-ad-light .wqs-ad-subtitle {
    color: #64748b;
}
.wqs-ad-modal.wqs-ad-light .wqs-ad-description {
    color: #475569;
}
.wqs-ad-modal.wqs-ad-light .wqs-ad-cancel {
    border-color: #cbd5e1;
    color: #64748b;
}
.wqs-ad-modal.wqs-ad-light .wqs-ad-cancel:hover {
    border-color: #94a3b8;
    color: #334155;
    background: #f8fafc;
}
.wqs-ad-modal.wqs-ad-light .wqs-ad-dismiss-row label {
    color: #64748b;
}

/* Dark theme overrides */
.wqs-ad-modal.wqs-ad-dark {
    background: #0b0f19;
    color: #f8fafc;
}
.wqs-ad-modal.wqs-ad-dark .wqs-ad-subtitle {
    color: #94a3b8;
}
.wqs-ad-modal.wqs-ad-dark .wqs-ad-description {
    color: #cbd5e1;
}
.wqs-ad-modal.wqs-ad-dark .wqs-ad-cancel {
    border-color: #334155;
    color: #cbd5e1;
}
.wqs-ad-modal.wqs-ad-dark .wqs-ad-cancel:hover {
    border-color: #475569;
    color: #f8fafc;
    background: rgba(255, 255, 255, 0.08);
}
.wqs-ad-modal.wqs-ad-dark .wqs-ad-dismiss-row label {
    color: #94a3b8;
}

.wqs-ad-image-container {
    width: 48%;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: transparent;
    padding: 24px;
}

.wqs-ad-image { width: 100%; height: 100%; max-height: 340px; object-fit: contain; display: block; }

.wqs-ad-close {
    position: absolute; top: 16px; right: 16px; width: 34px; height: 34px;
    border-radius: 50%; border: none;
    font-size: 1.1rem; cursor: pointer; display: flex; align-items: center;
    justify-content: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.12);
    transition: all 0.25s ease; z-index: 10;
}
.wqs-ad-modal.wqs-ad-light .wqs-ad-close {
    background: rgba(0, 0, 0, 0.05);
    color: #475569;
}
.wqs-ad-modal.wqs-ad-dark .wqs-ad-close {
    background: rgba(255, 255, 255, 0.08);
    color: #cbd5e1;
    border: 1px solid rgba(255,255,255,0.1);
}
.wqs-ad-close:hover {
    background: #fee2e2 !important; color: #dc2626 !important;
    transform: rotate(90deg);
}
.wqs-ad-close:active { transform: rotate(90deg) scale(0.9); }

.wqs-ad-content-side {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 32px;
}

.wqs-ad-body { padding: 0; background: transparent !important; }
.wqs-ad-headline {
    margin: 0 0 8px; font-size: 1.35rem; font-weight: 800; line-height: 1.3;
}
.wqs-ad-subtitle {
    margin: 0 0 10px; font-size: 0.95rem; font-weight: 600; line-height: 1.45;
}
.wqs-ad-description {
    margin: 0 0 20px; font-size: 0.88rem; font-weight: 500; line-height: 1.6;
}

.wqs-ad-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.wqs-ad-cta {
    display: block; width: 100%; padding: 12px 16px; border: none; border-radius: 10px;
    font-size: 0.95rem; font-weight: 700; cursor: pointer; color: #fff;
    text-decoration: none; text-align: center;
    transition: all 0.25s ease; letter-spacing: 0.01em;
}
.wqs-ad-cta:hover { filter: brightness(1.08); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(0,0,0,0.18); }
.wqs-ad-cta:active { transform: translateY(0); }

.wqs-ad-cancel {
    display: block; width: 100%; padding: 10px 16px; margin-top: 0px;
    border: 1.5px solid #cbd5e1; border-radius: 10px; background: transparent;
    font-size: 0.85rem; font-weight: 600; cursor: pointer; color: #64748b;
    text-align: center; transition: all 0.2s ease;
}
.wqs-ad-cancel:hover { border-color: #94a3b8; color: #334155; background: #f8fafc; }
.wqs-ad-cancel:active { background: #f1f5f9; }

.wqs-ad-dismiss-row {
    display: flex; align-items: center; gap: 8px;
    padding: 16px 0 0; background: transparent !important;
    border-top: 1px solid rgba(148, 163, 184, 0.12);
    margin-top: auto;
}
.wqs-ad-dismiss-row input[type="checkbox"] {
    width: 15px; height: 15px; margin: 0; cursor: pointer;
    accent-color: var(--ad-primary, #3b82f6); flex-shrink: 0;
}
.wqs-ad-dismiss-row label {
    font-size: 0.8rem; cursor: pointer; user-select: none;
    line-height: 1.4;
}

@media (max-width: 767px) {
    .wqs-ad-modal { flex-direction: column; max-width: 440px; }
    .wqs-ad-image-container { width: 100%; padding: 24px 24px 0; }
    .wqs-ad-image { max-height: 200px; }
    .wqs-ad-content-side { padding: 24px; }
    .wqs-ad-dismiss-row { padding: 12px 0 0; }
    .wqs-ad-close { top: 12px; right: 12px; }
}

@media (max-width: 480px) {
    .wqs-ad-modal { width: 95%; border-radius: 20px; }
    .wqs-ad-image-container { border-radius: 20px; }
    .wqs-ad-image { border-radius: 20px; }
    .wqs-ad-headline { font-size: 1.15rem; }
}

.wqs-notification-prompt {
    position: fixed; bottom: 20px; left: 20px; background: white; border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15); max-width: 360px;
    width: calc(100% - 40px); z-index: 9998; display: none;
    animation: slideInLeft 0.4s ease;
}
@keyframes slideInLeft { from { transform: translateX(-50px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.wqs-notification-prompt.show { display: block; }
</style>

<?php if (!empty($activeAds)): ?>
    <?php
    $currentAd = null;
    foreach ($activeAds as $ad) {
        if (!in_array($ad['id'], $closedAds)) {
            $currentAd = $ad;
            break;
        }
    }
    ?>

    <?php if ($currentAd): ?>
        <?php
        $isDarkTheme = false;
        if ($currentAd && $currentAd['image_url']) {
            if (strpos($currentAd['image_url'], 'ai_ad') !== false || strpos($currentAd['image_url'], 'banking_ad') !== false) {
                $isDarkTheme = true;
            }
        }
        $themeClass = $isDarkTheme ? 'wqs-ad-dark' : 'wqs-ad-light';
        ?>
        <div class="wqs-ad-modal-backdrop" id="wqsAdBackdrop" data-ad-id="<?= $currentAd['id'] ?>" style="--ad-primary: <?= $currentAd['primary_color'] ?>;">
            <div class="wqs-ad-modal <?= $themeClass ?>">
                <button class="wqs-ad-close" id="wqsAdClose" aria-label="Close advertisement"><i class="fas fa-times"></i></button>

                <?php if ($currentAd['image_url']): 
                    $adImgUrl = $currentAd['image_url'];
                    if ($adImgUrl && !preg_match('/^https?:\/\//i', $adImgUrl) && !preg_match('/^data:/i', $adImgUrl) && !str_starts_with($adImgUrl, '../')) {
                        $adImgUrl = $_adWebPath . $adImgUrl;
                    }
                ?>
                    <div class="wqs-ad-image-container">
                        <img src="<?= htmlspecialchars($adImgUrl) ?>" class="wqs-ad-image" alt="<?= htmlspecialchars($currentAd['title']) ?>">
                    </div>
                <?php endif; ?>

                <div class="wqs-ad-content-side">
                    <div class="wqs-ad-body">
                        <?php if ($currentAd['headline']): ?>
                            <h4 class="wqs-ad-headline" style="color: <?= $isDarkTheme ? '#ffffff' : $currentAd['primary_color'] ?>;"><?= htmlspecialchars($currentAd['headline']) ?></h4>
                        <?php endif; ?>
                        <?php if ($currentAd['subtitle']): ?>
                            <p class="wqs-ad-subtitle"><?= htmlspecialchars($currentAd['subtitle']) ?></p>
                        <?php endif; ?>
                        <?php if ($currentAd['description']): ?>
                            <p class="wqs-ad-description"><?= htmlspecialchars($currentAd['description']) ?></p>
                        <?php endif; ?>

                        <div class="wqs-ad-actions">
                            <a href="<?= htmlspecialchars($currentAd['button_url']) ?>" class="wqs-ad-cta" id="wqsAdCta"
                               style="background: linear-gradient(135deg, <?= $currentAd['primary_color'] ?>, <?= $currentAd['secondary_color'] ?>);"
                               onclick="trackAdClick(<?= $currentAd['id'] ?>)">
                                <?= htmlspecialchars($currentAd['button_text']) ?>
                            </a>

                            <button class="wqs-ad-cancel" id="wqsAdCancel" type="button">Cancel</button>
                        </div>
                    </div>

                    <div class="wqs-ad-dismiss-row">
                        <input type="checkbox" id="wqsAdDismissCheck">
                        <label for="wqsAdDismissCheck">Don't show this advertisement again</label>
                    </div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var backdrop = document.getElementById('wqsAdBackdrop');
            var closeBtn = document.getElementById('wqsAdClose');
            var cancelBtn = document.getElementById('wqsAdCancel');
            var dismissCheck = document.getElementById('wqsAdDismissCheck');
            var adId = backdrop ? backdrop.dataset.adId : null;
            var webPath = '<?= $_adWebPath ?>';
            var isDismissing = false;

            function closeModal() {
                if (!backdrop || isDismissing) return;
                isDismissing = true;
                backdrop.classList.add('wqs-fade-out');
                setTimeout(function() {
                    backdrop.classList.remove('show');
                    backdrop.classList.remove('wqs-fade-out');
                    isDismissing = false;
                }, 350);

                if (adId) {
                    fetch(webPath + 'api/track_ad.php', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'close', ad_id: adId })
                    });
                }

                if (dismissCheck && dismissCheck.checked && adId) {
                    var userId = <?= json_encode($userIdForAds) ?>;
                    if (userId) {
                        fetch(webPath + 'api/popup_api.php?action=dismiss_ad', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'ad_id=' + encodeURIComponent(adId) + '&ad_type=modal'
                        });
                    }
                    try { localStorage.setItem('wqs_ad_dismissed_' + adId, '1'); } catch(e) {}
                }
            }

            closeBtn.addEventListener('click', closeModal);
            cancelBtn.addEventListener('click', closeModal);

            backdrop.addEventListener('click', function(e) {
                if (e.target === backdrop) closeModal();
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) closeModal();
            });

            function trackAdView(id) {
                fetch(webPath + 'api/track_ad.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'view', ad_id: id })
                });
            }
            window.trackAdView = trackAdView;

            function trackAdClick(id) {
                fetch(webPath + 'api/track_ad.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'click', ad_id: id })
                });
            }
            window.trackAdClick = trackAdClick;

            function initAd() {
                if (!adId || !backdrop) return;

                try {
                    if (localStorage.getItem('wqs_ad_dismissed_' + adId) === '1') return;
                } catch(e) {}

                var userId = <?= json_encode($userIdForAds) ?>;
                if (userId) {
                    fetch(webPath + 'api/popup_api.php?action=check_dismissed&ad_id=' + encodeURIComponent(adId))
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            if (data.dismissed) return;
                            showAd();
                        })
                        .catch(function() { showAd(); });
                } else {
                    showAd();
                }
            }

            function showAd() {
                setTimeout(function() {
                    backdrop.classList.add('show');
                    trackAdView(parseInt(adId, 10));
                }, 1500);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initAd);
            } else {
                initAd();
            }
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>

<?php if ($userIdForAds && !isset($_SESSION['notif_prompt_shown'])): ?>
    <div class="wqs-notification-prompt p-4" id="wqsNotifPrompt">
        <div class="d-flex align-items-start gap-3 mb-3">
            <div class="flex-shrink-0" style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg, #3b82f6, #8b5cf6);display:flex;align-items:center;justify-content:center;color:white;font-size:1.5rem;">
                <i class="fas fa-bell"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="mb-1" style="font-weight:700;color:#0f172a;">Get Important Updates</h5>
                <p class="text-muted mb-0 small">Stay informed about new services, offers, and updates!</p>
            </div>
            <button class="btn-close p-2" onclick="dismissNotifPrompt()"></button>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm flex-grow-1" onclick="enableNotifications()">
                <i class="fas fa-check me-1"></i>Enable Notifications
            </button>
            <button class="btn btn-outline-secondary btn-sm flex-grow-1" onclick="dismissNotifPrompt()">Not Now</button>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(() => {
            document.getElementById('wqsNotifPrompt').classList.add('show');
        }, 3000);
    });

    function enableNotifications() {
        if ('Notification' in window) {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    fetch('<?= $_adWebPath ?>api/update_notification_prefs.php', {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ enable_push: 1 })
                    }).then(() => {
                        if (typeof window.wqsRegisterMessaging === 'function') {
                            window.wqsRegisterMessaging();
                        }
                    });
                }
            });
        }
        dismissNotifPrompt();
    }

    function dismissNotifPrompt() {
        document.getElementById('wqsNotifPrompt').style.display = 'none';
        <?php $_SESSION['notif_prompt_shown'] = true; ?>
    }
    </script>
<?php endif; ?>
