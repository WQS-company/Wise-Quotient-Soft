<?php
// Centralized FCM Push Notification Helper — V1 API + Legacy fallback
require_once __DIR__ . '/../config.php';

class FCMHelper {
    private static $serviceAccountPath = null;

    /**
     * Locate the Firebase service account JSON file.
     */
    private static function getServiceAccountPath() {
        if (self::$serviceAccountPath !== null) return self::$serviceAccountPath;

        $candidates = [
            __DIR__ . '/../firebase-service-account.json',
            __DIR__ . '/../service-account.json',
            __DIR__ . '/../wqsnotification-firebase-adminsdk.json',
        ];

        // Also check env var
        $envPath = getenv('FIREBASE_SERVICE_ACCOUNT_PATH');
        if ($envPath && file_exists($envPath)) {
            self::$serviceAccountPath = $envPath;
            return self::$serviceAccountPath;
        }

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                self::$serviceAccountPath = $path;
                return self::$serviceAccountPath;
            }
        }

        return null;
    }

    /**
     * Generate an OAuth2 access token from the service account private key.
     */
    private static function getAccessToken() {
        $saPath = self::getServiceAccountPath();
        if (!$saPath) return null;

        $sa = json_decode(file_get_contents($saPath), true);
        if (!$sa || empty($sa['client_email']) || empty($sa['private_key'])) return null;

        $now = time();
        $header = self::base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claimSet = self::base64url(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));
        $signingInput = $header . '.' . $claimSet;
        openssl_sign($signingInput, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256);
        $jwt = $signingInput . '.' . self::base64url($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);

        return $data['access_token'] ?? null;
    }

    private static function base64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Check if V1 API is available (service account file exists).
     */
    private static function isV1Available() {
        return self::getServiceAccountPath() !== null;
    }

    /**
     * Sends a push notification to all active tokens for a specific user.
     */
    public static function sendNotificationToUser($userId, $title, $body, $data = []) {
        global $pdo;
        try {
            // Check user preferences
            $stmt = $pdo->prepare("SELECT enable_push_notifications FROM user_notification_settings WHERE user_id = ?");
            $stmt->execute([$userId]);
            $pref = $stmt->fetch();
            if ($pref && (int)$pref['enable_push_notifications'] === 0) {
                return ['success' => false, 'message' => 'User disabled push notifications.'];
            }

            // Get user tokens
            $tokenStmt = $pdo->prepare("SELECT fcm_token FROM user_fcm_tokens WHERE user_id = ?");
            $tokenStmt->execute([$userId]);
            $tokens = $tokenStmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($tokens)) {
                self::logFCMDispatch("No Tokens", [], $title, $body, $data, false, "No FCM tokens for user $userId");
                return ['success' => false, 'message' => 'No FCM tokens found.'];
            }

            return self::sendNotification($tokens, $title, $body, $data);
        } catch (Exception $e) {
            error_log("FCM sendNotificationToUser error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Broadcasts a notification to all registered FCM tokens.
     */
    public static function sendNotificationToAll($title, $body, $data = []) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT fcm_token FROM user_fcm_tokens");
            $stmt->execute();
            $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($tokens)) {
                return ['success' => false, 'message' => 'No registered FCM tokens.'];
            }

            return self::sendNotification($tokens, $title, $body, $data);
        } catch (Exception $e) {
            error_log("FCM sendNotificationToAll error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Dispatches push notifications. Uses V1 API if service account exists,
     * otherwise falls back to simulation mode.
     */
    public static function sendNotification($tokens, $title, $body, $data = []) {
        if (!is_array($tokens)) $tokens = [$tokens];
        $tokens = array_filter(array_unique($tokens));
        if (empty($tokens)) return ['success' => false, 'message' => 'Empty tokens list'];

        // If no service account, run in simulation mode
        if (!self::isV1Available()) {
            $count = count($tokens);
            $responseRaw = 'SIMULATED: No firebase-service-account.json found. Dispatched to ' . $count . ' token(s).';
            self::logFCMDispatch("Simulation", $tokens, $title, $body, $data, true, $responseRaw);
            return [
                'success' => true,
                'message' => 'Push notifications simulated (no service account configured).',
                'success_count' => $count,
                'failure_count' => 0,
            ];
        }

        // Get OAuth2 access token
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            self::logFCMDispatch("V1 Auth Failed", $tokens, $title, $body, $data, false, "Failed to obtain OAuth2 access token");
            // Report to health monitor
            if (file_exists(__DIR__ . '/api_health_monitor.php')) {
                require_once __DIR__ . '/api_health_monitor.php';
                APIHealthMonitor::reportFailure('Firebase', 'Failed to obtain OAuth2 access token', 'The service account credentials may be invalid or expired.', 'critical');
            }
            return ['success' => false, 'message' => 'Failed to authenticate with Firebase V1 API.'];
        }

        $projectId = getenv('FIREBASE_PROJECT_ID') ?: 'wise-quotient-soft-cfe28';
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ];

        $successCount = 0;
        $failedCount = 0;
        $responseRaw = '';

        // V1 API sends one message at a time (no batch)
        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token' => $token,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'webpush' => [
                        'notification' => [
                            'title' => $title,
                            'body'  => $body,
                            'icon'  => '/dashboard/wqs/LOGO W.png',
                            'badge' => '/dashboard/wqs/LOGO W.png',
                            'sound' => 'default',
                            'click_action' => $data['click_action'] ?? '/dashboard/wqs/',
                        ],
                        'fcm_options' => [
                            'link' => $data['click_action'] ?? '/dashboard/wqs/',
                        ],
                    ],
                    'data' => $data,
                ],
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_POSTFIELDS     => json_encode($payload),
            ]);
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpcode === 200) {
                $successCount++;
            } else {
                $failedCount++;
                $responseRaw .= "Token: ..." . substr($token, -10) . " | HTTP $httpcode: $response\n";
                // Report on 401/403 (auth issues) but not on individual token errors
                if (in_array($httpcode, [401, 403]) && $failedCount === 1) {
                    if (file_exists(__DIR__ . '/api_health_monitor.php')) {
                        require_once __DIR__ . '/api_health_monitor.php';
                        APIHealthMonitor::reportFailure('Firebase', "HTTP $httpcode authentication error", substr($response, 0, 200), 'critical');
                    }
                }
            }
        }

        $success = ($successCount > 0);
        self::logFCMDispatch("V1 API", $tokens, $title, $body, $data, $success, trim($responseRaw));

        return [
            'success'      => $success,
            'success_count' => $successCount,
            'failure_count' => $failedCount,
            'message'       => $success ? "FCM V1 dispatched: $successCount ok, $failedCount failed" : 'FCM V1 dispatch failed',
        ];
    }

    /**
     * Logs dispatch records to fcm_notification_history.
     */
    private static function logFCMDispatch($method, $tokens, $title, $body, $data, $success, $responseRaw = '') {
        global $pdo;
        try {
            $stmt = $pdo->prepare("INSERT INTO `fcm_notification_history` (`title`, `message`, `recipient_count`, `status`, `response_log`) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $title,
                $body,
                count($tokens),
                $success ? 'success' : 'failed',
                "Method: $method | Tokens: [" . implode(', ', array_map('substr', $tokens, array_fill(0, count($tokens), -10))) . "...] | Data: " . json_encode($data) . "\nLog: " . $responseRaw,
            ]);
        } catch (Exception $e) {
            error_log("FCM logFCMDispatch failed: " . $e->getMessage());
        }
    }
}
