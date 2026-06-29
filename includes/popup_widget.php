<?php
// Floating Popup Widget — included on every page
// Fetches active popups and renders glassmorphism floating popup
if (session_status() === PHP_SESSION_NONE) session_start();
$popupUserId = $_SESSION['user']['id'] ?? null;
$popupScript = $_SERVER['SCRIPT_NAME'] ?? '/';
if (strpos($popupScript, '/admin/') !== false) {
    $popupPath = '../';
} elseif (strpos($popupScript, '/user/') !== false) {
    $popupPath = '../';
} else {
    $popupPath = './';
}
?>
<script>
(function() {
    var _popupSessionId = 'sess_' + Math.random().toString(36).substr(2, 12) + '_' + Date.now();
    var _popupPath = <?= json_encode($popupPath) ?>;
    var _popupUserId = <?= json_encode($popupUserId) ?>;
    var _activeOverlays = {};

    /* ─── localStorage helpers ─── */
    function getDismissed() {
        try { return JSON.parse(localStorage.getItem('wqs_popup_dismissed') || '{}'); } catch(e) { return {}; }
    }
    function setDismissed(id, type) {
        var d = getDismissed();
        d[id] = { type: type, time: Date.now(), permanent: true };
        localStorage.setItem('wqs_popup_dismissed', JSON.stringify(d));
    }
    function isDismissed(id, type) {
        var d = getDismissed();
        if (!d[id]) return false;
        if (d[id].permanent) return true;
        if (type === 'once_user' || type === 'once_daily') return true;
        if (type === 'once_daily') {
            var dismissed = new Date(d[id].time);
            var now = new Date();
            return dismissed.toDateString() === now.toDateString();
        }
        return false;
    }

    /* ─── API: Check if popup is dismissed server-side ─── */
    function checkDismissedServer(popupId, callback) {
        if (!_popupUserId) { callback(false); return; }
        fetch(_popupPath + 'api/popup_api.php?action=check_dismissed&popup_id=' + encodeURIComponent(popupId) + '&user_id=' + encodeURIComponent(_popupUserId))
            .then(function(r) { return r.json(); })
            .then(function(data) { callback(data.dismissed === true); })
            .catch(function() { callback(false); });
    }

    /* ─── API: Dismiss popup server-side ─── */
    function dismissPopupServer(popupId) {
        if (!_popupUserId) return;
        var fd = new FormData();
        fd.append('action', 'dismiss_ad');
        fd.append('popup_id', popupId);
        fd.append('user_id', _popupUserId);
        fetch(_popupPath + 'api/popup_api.php', { method: 'POST', body: fd }).catch(function(){});
    }

    /* ─── Track event ─── */
    function trackPopup(popupId, eventType) {
        var fd = new FormData();
        fd.append('action', 'track');
        fd.append('popup_id', popupId);
        fd.append('event_type', eventType);
        fd.append('session_id', _popupSessionId);
        fetch(_popupPath + 'api/popup_api.php', { method: 'POST', body: fd }).catch(function(){});
    }

    /* ─── Smooth close animation ─── */
    function animateClose(popupId, callback) {
        var overlay = document.getElementById('wqs-float-popup-overlay-' + popupId);
        if (!overlay) { if (callback) callback(); return; }
        if (overlay._timerInterval) clearInterval(overlay._timerInterval);
        if (overlay._escHandler) document.removeEventListener('keydown', overlay._escHandler);

        var popupEl = overlay.querySelector('.wqs-float-popup');
        if (popupEl) {
            popupEl.style.transition = 'transform 0.35s cubic-bezier(0.4, 0, 0.2, 1), opacity 0.35s ease';
            popupEl.style.transform = popupEl._baseTransform.replace('scale(1)', 'scale(0.9)');
            popupEl.style.opacity = '0';
        }
        overlay.style.transition = 'opacity 0.35s ease';
        overlay.style.opacity = '0';

        trackPopup(popupId, 'close');

        setTimeout(function() {
            overlay.remove();
            if (callback) callback();
        }, 360);
    }

    /* ─── Close popup (global) ─── */
    window._wqsClosePopup = function(popupId) {
        animateClose(popupId);
    };

    /* ─── Cancel popup (no redirect, close only) ─── */
    window._wqsCancelPopup = function(popupId) {
        animateClose(popupId);
    };

    /* ─── Track click ─── */
    window._wqsPopupClick = function(popupId) {
        trackPopup(popupId, 'click');
    };

    /* ─── Build popup HTML ─── */
    function buildPopup(popup) {
        var sizeMap = { sm: '380px', md: '480px', lg: '600px', xl: '750px' };
        var maxWidth = sizeMap[popup.size] || '480px';
        var posStyles = '';
        var pos = popup.position || 'center';
        var baseTransform = '';

        if (pos === 'center') {
            posStyles = 'top:50%;left:50%;';
            baseTransform = 'translate(-50%,-50%) scale(0)';
        } else if (pos === 'top-left') {
            posStyles = 'top:24px;left:24px;';
            baseTransform = 'scale(0)';
        } else if (pos === 'top-right') {
            posStyles = 'top:24px;right:24px;';
            baseTransform = 'scale(0)';
        } else if (pos === 'bottom-left') {
            posStyles = 'bottom:24px;left:24px;';
            baseTransform = 'scale(0)';
        } else if (pos === 'bottom-right') {
            posStyles = 'bottom:24px;right:24px;';
            baseTransform = 'scale(0)';
        }

        var overlay = document.createElement('div');
        overlay.id = 'wqs-float-popup-overlay-' + popup.id;
        overlay.className = 'wqs-float-popup-overlay';
        overlay.setAttribute('data-popup-id', popup.id);

        var html = '<div class="wqs-float-popup" style="max-width:' + maxWidth + ';' + posStyles + 'transform:' + baseTransform + ';">';
        html += '<button class="wqs-float-popup-close" aria-label="Close advertisement">&times;</button>';
        if (popup.image_url) {
            var displayImgUrl = popup.image_url;
            if (displayImgUrl && !displayImgUrl.startsWith('http') && !displayImgUrl.startsWith('data:') && !displayImgUrl.startsWith('../')) {
                displayImgUrl = _popupPath + displayImgUrl;
            }
            html += '<div class="wqs-float-popup-img"><img src="' + displayImgUrl + '" alt="' + (popup.title || '') + '"></div>';
        }
        html += '<div class="wqs-float-popup-body">';
        html += '<h3 class="wqs-float-popup-title">' + (popup.title || '') + '</h3>';
        if (popup.description) {
            html += '<p class="wqs-float-popup-desc">' + popup.description + '</p>';
        }
        html += '<div class="wqs-float-popup-actions">';
        if (popup.button_text && popup.button_url) {
            var buttonUrl = popup.button_url;
            if (buttonUrl && buttonUrl !== '#' && !/^(https?:\/\/|mailto:|tel:|\/|#)/i.test(buttonUrl)) {
                buttonUrl = _popupPath + buttonUrl;
            }
            html += '<a href="' + buttonUrl + '" class="wqs-float-popup-btn" target="_blank" rel="noopener">' + popup.button_text + '</a>';
        }
        html += '<button class="wqs-float-popup-btn-cancel" type="button">Cancel</button>';
        html += '</div>';
        html += '<div class="wqs-float-popup-bottom">';
        html += '<label class="wqs-float-popup-dontshow">';
        html += '<input type="checkbox" class="wqs-float-popup-dontshow-input" data-popup-id="' + popup.id + '">';
        html += '<span class="wqs-float-popup-dontshow-checkmark"></span>';
        html += '<span class="wqs-float-popup-dontshow-text">Don\'t show this advertisement again</span>';
        html += '</label>';
        html += '<span class="wqs-float-popup-timer" id="wqs-float-timer-' + popup.id + '">Closing in ' + popup.timer_duration + 's</span>';
        html += '</div>';
        html += '</div></div>';

        overlay.innerHTML = html;
        document.body.appendChild(overlay);

        /* Store base transform for close animation */
        var popupEl = overlay.querySelector('.wqs-float-popup');
        popupEl._baseTransform = posStyles + 'transform:' + baseTransform.replace('scale(0)', 'scale(1)') + ';';

        /* Bind close button */
        var closeBtn = overlay.querySelector('.wqs-float-popup-close');
        closeBtn.addEventListener('click', function() {
            handleDismiss(popup.id, popup.trigger);
        });

        /* Bind cancel button */
        var cancelBtn = overlay.querySelector('.wqs-float-popup-btn-cancel');
        cancelBtn.addEventListener('click', function() {
            animateClose(popup.id);
        });

        /* Bind CTA button */
        var ctaBtn = overlay.querySelector('.wqs-float-popup-btn');
        if (ctaBtn) {
            ctaBtn.addEventListener('click', function() {
                trackPopup(popup.id, 'click');
            });
        }

        /* Bind "Don't show again" checkbox */
        var checkbox = overlay.querySelector('.wqs-float-popup-dontshow-input');
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                dismissPopupServer(popup.id);
                setDismissed(String(popup.id), 'permanent');
            }
        });

        /* ESC key handler */
        function escHandler(e) {
            if (e.key === 'Escape') {
                handleDismiss(popup.id, popup.trigger);
            }
        }
        document.addEventListener('keydown', escHandler);
        overlay._escHandler = escHandler;

        /* Outside click handler */
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                handleDismiss(popup.id, popup.trigger);
            }
        });

        /* Start countdown */
        var remaining = parseInt(popup.timer_duration) || 10;
        var timerEl = document.getElementById('wqs-float-timer-' + popup.id);
        var interval = setInterval(function() {
            remaining--;
            if (timerEl) timerEl.textContent = 'Closing in ' + remaining + 's';
            if (remaining <= 0) {
                clearInterval(interval);
                handleDismiss(popup.id, popup.trigger);
            }
        }, 1000);
        overlay._timerInterval = interval;

        /* Store reference */
        _activeOverlays[popup.id] = overlay;

        /* Animate in */
        requestAnimationFrame(function() {
            overlay.classList.add('visible');
            var el = overlay.querySelector('.wqs-float-popup');
            if (pos === 'center') {
                el.style.transform = 'translate(-50%,-50%) scale(1)';
            } else {
                el.style.transform = 'scale(1)';
            }
        });

        trackPopup(popup.id, 'view');
    }

    /* ─── Handle dismiss logic (checkbox check, then close) ─── */
    function handleDismiss(popupId, trigger) {
        var overlay = document.getElementById('wqs-float-popup-overlay-' + popupId);
        if (!overlay) return;
        var checkbox = overlay.querySelector('.wqs-float-popup-dontshow-input');
        if (checkbox && checkbox.checked) {
            dismissPopupServer(popupId);
            setDismissed(String(popupId), 'permanent');
        }
        animateClose(popupId);
    }

    /* ─── Check triggers ─── */
    function shouldShow(popup, callback) {
        if (isDismissed(String(popup.id), popup.trigger)) { callback(false); return; }
        if (_popupUserId) {
            checkDismissedServer(popup.id, function(serverDismissed) {
                if (serverDismissed) {
                    setDismissed(String(popup.id), 'permanent');
                    callback(false);
                } else {
                    callback(true);
                }
            });
        } else {
            callback(true);
        }
    }

    function showPopup(popup) {
        shouldShow(popup, function(show) {
            if (!show) return;
            var trigger = popup.trigger || 'immediate';
            var delay = (parseInt(popup.trigger_delay) || 3) * 1000;

            if (trigger === 'immediate') {
                buildPopup(popup);
            } else if (trigger === 'delay') {
                setTimeout(function() { buildPopup(popup); }, delay);
            } else if (trigger === 'scroll') {
                var shown = false;
                function onScroll() {
                    if (shown) return;
                    if (window.scrollY > 200) {
                        shown = true;
                        window.removeEventListener('scroll', onScroll);
                        buildPopup(popup);
                    }
                }
                window.addEventListener('scroll', onScroll, { passive: true });
            } else if (trigger === 'exit') {
                document.addEventListener('mouseout', function handler(e) {
                    if (e.clientY < 5) {
                        document.removeEventListener('mouseout', handler);
                        buildPopup(popup);
                    }
                });
            } else if (trigger === 'once_daily' || trigger === 'once_user') {
                buildPopup(popup);
            } else {
                buildPopup(popup);
            }
        });
    }

    /* ─── Fetch and show ─── */
    function initPopups() {
        fetch(_popupPath + 'api/popup_api.php?action=active_popups')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.popups) return;
            data.popups.forEach(function(popup) { showPopup(popup); });
        })
        .catch(function(){});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPopups);
    } else {
        initPopups();
    }
})();
</script>

<style>
:root {
    --wqs-popup-primary: #7c3aed;
    --wqs-popup-primary-light: #a855f7;
    --wqs-popup-text: #0f172a;
    --wqs-popup-text-secondary: #64748b;
    --wqs-popup-text-muted: #94a3b8;
    --wqs-popup-bg: transparent;
    --wqs-popup-border: rgba(255, 255, 255, 0.3);
    --wqs-popup-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
    --wqs-popup-close-bg: rgba(255, 255, 255, 0.85);
    --wqs-popup-close-text: #334155;
    --wqs-popup-cancel-border: rgba(255, 255, 255, 0.6);
    --wqs-popup-cancel-text: #ffffff;
}

@media (prefers-color-scheme: dark) {
    :root {
        --wqs-popup-text: #ffffff;
        --wqs-popup-text-secondary: rgba(255, 255, 255, 0.85);
        --wqs-popup-text-muted: rgba(255, 255, 255, 0.6);
        --wqs-popup-bg: transparent;
        --wqs-popup-border: rgba(255, 255, 255, 0.15);
        --wqs-popup-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        --wqs-popup-close-bg: rgba(255, 255, 255, 0.85);
        --wqs-popup-close-text: #1e293b;
        --wqs-popup-cancel-border: rgba(255, 255, 255, 0.4);
        --wqs-popup-cancel-text: #ffffff;
    }
}

/* ─── Overlay ─── */
.wqs-float-popup-overlay {
    position: fixed;
    inset: 0;
    z-index: 10000;
    pointer-events: none;
    background: rgba(0, 0, 0, 0.45);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    transition: opacity 0.35s ease;
    opacity: 0;
}
.wqs-float-popup-overlay.visible {
    pointer-events: all;
    opacity: 1;
}

/* ─── Popup card — fully transparent ─── */
.wqs-float-popup {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 92%;
    max-width: 520px;
    background: transparent;
    border: none;
    border-radius: 20px;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.35);
    overflow: visible;
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.35s ease;
}

/* ─── Close button ─── */
.wqs-float-popup-close {
    position: absolute;
    top: -8px;
    right: -8px;
    z-index: 10;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.6);
    background: var(--wqs-popup-close-bg);
    color: var(--wqs-popup-close-text);
    font-size: 1.35rem;
    line-height: 1;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.25s ease;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
.wqs-float-popup-close:hover {
    background: rgba(239, 68, 68, 0.9);
    color: #fff;
    border-color: rgba(239, 68, 68, 0.9);
    transform: rotate(90deg) scale(1.1);
}
.wqs-float-popup-close:focus-visible {
    outline: 2px solid var(--wqs-popup-primary);
    outline-offset: 2px;
}

/* ─── Image — centered, fully visible ─── */
.wqs-float-popup-img {
    width: 100%;
    background: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 20px 20px 0 0;
    overflow: hidden;
}
.wqs-float-popup-img img {
    width: 100%;
    height: auto;
    max-height: 320px;
    object-fit: contain;
    display: block;
}

/* ─── Body — transparent overlay on image ─── */
.wqs-float-popup-body {
    padding: 1.5rem 1.75rem 1.25rem;
    background: transparent;
}

.wqs-float-popup-title {
    font-size: 1.3rem;
    font-weight: 800;
    color: var(--wqs-popup-text);
    margin: 0 0 0.5rem;
    line-height: 1.3;
    text-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
}

.wqs-float-popup-desc {
    font-size: 0.9rem;
    color: var(--wqs-popup-text-secondary);
    line-height: 1.6;
    margin: 0 0 1.25rem;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* ─── Actions ─── */
.wqs-float-popup-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 1rem;
}

.wqs-float-popup-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 11px 28px;
    border-radius: 50px;
    background: linear-gradient(135deg, var(--wqs-popup-primary), var(--wqs-popup-primary-light));
    color: #fff;
    font-weight: 700;
    font-size: 0.88rem;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.25s ease;
    box-shadow: 0 4px 16px rgba(124, 58, 237, 0.35);
    white-space: nowrap;
    flex-shrink: 0;
}
.wqs-float-popup-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(124, 58, 237, 0.45);
    color: #fff;
}
.wqs-float-popup-btn:focus-visible {
    outline: 2px solid var(--wqs-popup-primary);
    outline-offset: 2px;
}

.wqs-float-popup-btn-cancel {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 22px;
    border-radius: 50px;
    background: rgba(255, 255, 255, 0.75);
    color: var(--wqs-popup-cancel-text);
    font-weight: 600;
    font-size: 0.82rem;
    border: 1.5px solid var(--wqs-popup-cancel-border);
    cursor: pointer;
    transition: all 0.25s ease;
    white-space: nowrap;
    flex-shrink: 0;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}
.wqs-float-popup-btn-cancel:hover {
    background: rgba(255, 255, 255, 0.9);
    border-color: var(--wqs-popup-text-secondary);
    color: var(--wqs-popup-text-secondary);
}
.wqs-float-popup-btn-cancel:focus-visible {
    outline: 2px solid var(--wqs-popup-primary);
    outline-offset: 2px;
}

/* ─── Bottom row (checkbox + timer) ─── */
.wqs-float-popup-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

/* ─── Don't show again checkbox ─── */
.wqs-float-popup-dontshow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    user-select: none;
}
.wqs-float-popup-dontshow-input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}
.wqs-float-popup-dontshow-checkmark {
    width: 18px;
    height: 18px;
    border-radius: 5px;
    border: 2px solid rgba(255, 255, 255, 0.6);
    background: rgba(255, 255, 255, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    flex-shrink: 0;
    position: relative;
}
.wqs-float-popup-dontshow-checkmark::after {
    content: '';
    width: 5px;
    height: 9px;
    border: solid #fff;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg) scale(0);
    transition: transform 0.2s ease;
    position: absolute;
    top: 1px;
    left: 4.5px;
}
.wqs-float-popup-dontshow-input:checked + .wqs-float-popup-dontshow-checkmark {
    background: var(--wqs-popup-primary);
    border-color: var(--wqs-popup-primary);
}
.wqs-float-popup-dontshow-input:checked + .wqs-float-popup-dontshow-checkmark::after {
    transform: rotate(45deg) scale(1);
}
.wqs-float-popup-dontshow-text {
    font-size: 0.78rem;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 500;
    line-height: 1.3;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

/* ─── Timer ─── */
.wqs-float-popup-timer {
    font-size: 0.75rem;
    color: rgba(255, 255, 255, 0.7);
    font-weight: 500;
    white-space: nowrap;
    flex-shrink: 0;
    text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

/* ─── Mobile responsive ─── */
@media (max-width: 575.98px) {
    .wqs-float-popup { width: 94%; border-radius: 16px; }
    .wqs-float-popup-img { border-radius: 16px 16px 0 0; }
    .wqs-float-popup-img img { max-height: 240px; }
    .wqs-float-popup-body { padding: 1.25rem 1.25rem 1rem; }
    .wqs-float-popup-title { font-size: 1.1rem; }
    .wqs-float-popup-desc { font-size: 0.82rem; }

    .wqs-float-popup-actions {
        flex-direction: column;
        align-items: stretch;
    }
    .wqs-float-popup-btn,
    .wqs-float-popup-btn-cancel {
        width: 100%;
        justify-content: center;
    }

    .wqs-float-popup-bottom {
        flex-direction: column;
        align-items: flex-start;
    }

    .wqs-float-popup-close {
        width: 32px;
        height: 32px;
        font-size: 1.2rem;
        top: -6px;
        right: -6px;
    }
}
</style>
