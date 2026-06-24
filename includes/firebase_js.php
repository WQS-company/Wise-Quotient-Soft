<?php
// Firebase JS SDK and Client-side Analytics Loader Template
require_once __DIR__ . '/../firebase_config.php';
$firebaseConfig = get_firebase_js_config();
$vapidKey = getenv('FIREBASE_VAPID_KEY') ?: '';
?>
<!-- Include Cookie Consent check helper -->
<script>
if (typeof getWQSCookie !== 'function') {
    function getWQSCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) == ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) == 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
        }
        return null;
    }
}
</script>

<!-- Firebase Client-Side SDK Integration (v10 modular CDN) -->
<script type="module">
    import { initializeApp } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-app.js";
    import { getAnalytics, logEvent } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-analytics.js";
    import { getMessaging, getToken, onMessage } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-messaging.js";
    import { getRemoteConfig, fetchAndActivate, getString, getBoolean, getNumber } from "https://www.gstatic.com/firebasejs/10.8.0/firebase-remote-config.js";

    const firebaseConfig = <?php echo json_encode($firebaseConfig); ?>;
    const vapidKey = <?php echo json_encode($vapidKey); ?>;

    // Initialize Firebase
    const app = initializeApp(firebaseConfig);
    
    // Parse GDPR cookie preferences
    let consent = { analytics: true, marketing: true };
    const consentRaw = getWQSCookie('wqs_cookie_consent');
    if (consentRaw) {
        try {
            consent = JSON.parse(consentRaw);
        } catch(e) {}
    }

    // Dynamically initialize Firebase Cloud Analytics if consent was granted
    let analytics = null;
    if (consent.analytics) {
        try {
            analytics = getAnalytics(app);
        } catch (e) {
            console.warn("[Firebase Analytics] Init failed: ", e);
        }
    }

    // Initialize Firebase Cloud Messaging if supported
    let messaging = null;
    try {
        messaging = getMessaging(app);
    } catch (e) {
        console.warn("[FCM] Messaging not supported or blocked in this browser:", e);
    }

    // Global custom logging function to synchronise events between Local database and Firebase
    window.wqsLogEvent = function(eventName, eventValue = '') {
        console.log(`[WQS Event Log] event_name: ${eventName} | event_value: ${eventValue}`);
        
        // Log to Cloud GA4 Analytics
        if (analytics) {
            try {
                logEvent(analytics, eventName, { value: eventValue });
            } catch (err) {
                console.error("Firebase logEvent error: ", err);
            }
        }

        // Log to Local database for Admin analytics dashboard
        if (consent.analytics) {
            fetch('<?= isset($path_to_root) ? $path_to_root : './' ?>api/log_analytics_event.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    event_name: eventName,
                    event_value: eventValue,
                    referrer: document.referrer || 'Direct',
                    page_url: window.location.href
                })
            }).catch(err => console.error("Local analytics write failed: ", err));
        }
    };

    // Global registration function that can be triggered when permission is granted
    window.wqsRegisterMessaging = function() {
        if (!messaging) return;
        const configParams = new URLSearchParams(firebaseConfig).toString();
        const swPath = '<?= isset($path_to_root) ? $path_to_root : './' ?>firebase-messaging-sw.js';
        navigator.serviceWorker.register(swPath + '?' + configParams)
            .then((registration) => {
                console.log('[FCM SW] Service Worker registered scope: ', registration.scope);
                requestNotificationToken(registration);
            })
            .catch((err) => {
                console.error('[FCM SW] Service Worker registration failed: ', err);
            });
    };

    // Service Worker Registration and Notification Permissions Ask Flow
    if (messaging && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        window.wqsRegisterMessaging();
    }

    function requestNotificationToken(registration) {
        // Build getToken VAPID parameters
        const tokenParams = { serviceWorkerRegistration: registration };
        if (vapidKey) {
            tokenParams.vapidKey = vapidKey;
        }

        getToken(messaging, tokenParams)
        .then((currentToken) => {
            if (currentToken) {
                console.log('[FCM Token] Registration Token acquired: ', currentToken);
                // Register token to our database mapping
                sendTokenToServer(currentToken);
            } else {
                console.log('[FCM Token] Permission not granted or token retrieval blocked.');
            }
        })
        .catch((err) => {
            console.warn('[FCM Token] Error retrieving registration token: ', err);
        });
    }

    function sendTokenToServer(token) {
        const deviceType = /mobile/i.test(navigator.userAgent) ? 'mobile' : (/tablet|ipad/i.test(navigator.userAgent) ? 'tablet' : 'desktop');
        fetch('<?= isset($path_to_root) ? $path_to_root : './' ?>api/register_fcm_token.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                token: token,
                device_type: deviceType
            })
        })
        .then(res => res.json())
        .then(resData => {
            if (resData.success) {
                console.log('[FCM Server] Token registered successfully.');
            }
        })
        .catch(err => console.error('[FCM Server] Token transmission failed:', err));
    }

    // Foreground Push Notification Alert Handler using SweetAlert2 toasts
    if (messaging) {
        onMessage(messaging, (payload) => {
            console.log('[FCM Foreground Notification] Payload received: ', payload);
            
            // Update notification badge in real-time
            const badge = document.getElementById('notif-badge');
            if (badge) {
                const current = parseInt(badge.textContent) || 0;
                badge.textContent = current + 1;
                badge.classList.remove('d-none');
            }
            const unreadLabel = document.getElementById('notif-unread-count');
            if (unreadLabel) {
                const badge2 = document.getElementById('notif-badge');
                const c = parseInt(badge2?.textContent) || 1;
                unreadLabel.textContent = '(' + c + ' unread)';
            }
            
            // Show toast notification
            const clickUrl = payload.data?.click_action || '<?= isset($path_to_root) ? $path_to_root : './' ?>';
            
            if (window.Swal) {
                Swal.fire({
                    title: payload.notification.title || "Alert",
                    text: payload.notification.body || "",
                    icon: 'info',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 6000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.style.cursor = 'pointer';
                        toast.addEventListener('click', () => {
                            window.location.href = clickUrl;
                        });
                    }
                });
            } else {
                const n = new Notification(payload.notification.title || "Wise Quotient Soft", {
                    body: payload.notification.body || "",
                    icon: payload.notification.icon || '<?= isset($path_to_root) ? $path_to_root : './' ?>LOGO W.png'
                });
                n.onclick = () => { window.location.href = clickUrl; };
            }
        });
    }

    // Log the current page visit
    const filename = window.location.pathname.split('/').pop() || 'index.php';
    wqsLogEvent('page_view', filename);

    // ===== Remote Config & A/B Testing Integration =====
    (async function() {
        const RC_API = '<?= isset($path_to_root) ? $path_to_root : './' ?>api/firebase_remote_config.php';

        // Get or generate session ID for A/B test assignment
        let sessionId = localStorage.getItem('wqs_session_id');
        if (!sessionId) {
            sessionId = 'sess_' + Math.random().toString(36).substring(2, 15) + Date.now().toString(36);
            localStorage.setItem('wqs_session_id', sessionId);
        }

        // Fetch Remote Config (public endpoint — no auth needed)
        try {
            const rcRes = await fetch(RC_API + '?action=get_config&session_id=' + encodeURIComponent(sessionId));
            const rcData = await rcRes.json();

            if (rcData.success && rcData.config) {
                // Build the global config object (A/B variant overrides already applied server-side)
                window.wqsRemoteConfig = rcData.config;
                window.wqsABVariants = rcData.ab_tests || {};

                console.log('[WQS Remote Config] Loaded:', Object.keys(window.wqsRemoteConfig).length, 'params');
                console.log('[WQS A/B Assignments]', window.wqsABVariants);

                // Apply config values to DOM
                applyRemoteConfig(window.wqsRemoteConfig);

                // Dispatch event for pages that want to react to config
                document.dispatchEvent(new CustomEvent('wqsRemoteConfigLoaded', { detail: window.wqsRemoteConfig }));
            }
        } catch (err) {
            console.warn('[WQS Remote Config] Failed to load:', err);
            window.wqsRemoteConfig = {};
            window.wqsABVariants = {};
        }

        // Apply Remote Config values to the page
        function applyRemoteConfig(config) {
            // Site announcement banner
            if (config.announcement_enabled === 'true' || config.announcement_enabled === true) {
                const announcement = config.site_announcement || '';
                if (announcement) {
                    let banner = document.getElementById('wqs-announcement-banner');
                    if (!banner) {
                        banner = document.createElement('div');
                        banner.id = 'wqs-announcement-banner';
                        banner.style.cssText = 'padding:10px 20px;text-align:center;font-size:0.85rem;font-weight:600;z-index:9999;position:relative;';
                        const firstChild = document.body.firstElementChild;
                        if (firstChild) {
                            firstChild.parentNode.insertBefore(banner, firstChild);
                        } else {
                            document.body.prepend(banner);
                        }
                    }
                    banner.style.background = config.announcement_bg_color || '#6366f1';
                    banner.style.color = config.announcement_text_color || '#ffffff';
                    banner.innerHTML = '<div style="max-width:1200px;margin:0 auto;">' + escapeHtml(announcement) + '</div>';
                }
            }

            // Maintenance mode
            if (config.maintenance_mode === 'true' || config.maintenance_mode === true) {
                const msg = config.maintenance_message || 'We are currently performing maintenance. Please check back soon.';
                // Don't redirect admin users
                const isAdmin = document.body.classList.contains('admin-body') || window.location.pathname.includes('/admin/');
                if (!isAdmin) {
                    document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center;padding:2rem;background:#f8fafc;"><div><h1 style="font-size:1.5rem;font-weight:800;color:#1e293b;margin-bottom:0.5rem;">Under Maintenance</h1><p style="color:#64748b;max-width:400px;">' + escapeHtml(msg) + '</p></div></div>';
                }
            }

            // Chat widget toggle
            if (config.enable_chat_widget === 'false' || config.enable_chat_widget === false) {
                const chatWidget = document.getElementById('wiseBotToggle') || document.querySelector('[data-wqs-chat]');
                if (chatWidget) chatWidget.style.display = 'none';
            }

            // Popup ads toggle
            if (config.enable_popup_ads === 'false' || config.enable_popup_ads === false) {
                window.wqsPopupAdsDisabled = true;
            }

            // Footer text
            if (config.footer_text) {
                const footerEl = document.querySelector('.wqs-footer-text');
                if (footerEl) footerEl.textContent = config.footer_text;
            }

            // Max upload size
            if (config.max_upload_size) {
                window.wqsMaxUploadSize = parseInt(config.max_upload_size) || 10;
            }
        }

        // Helper to escape HTML
        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Log A/B conversion event
        window.wqsLogABConversion = function(testParamKey) {
            fetch(RC_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=convert_test&test_param_key=' + encodeURIComponent(testParamKey) + '&session_id=' + encodeURIComponent(sessionId)
            }).catch(() => {});
        };
    })();
</script>
