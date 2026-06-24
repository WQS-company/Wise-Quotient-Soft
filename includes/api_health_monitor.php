<?php
/**
 * API Health Monitor — detects and reports API failures to admins.
 * Logs failures to DB, sends admin notifications, provides health status.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../firebase_config.php';

class APIHealthMonitor {
    private static $instance = null;

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log an API failure and notify admins (with cooldown to prevent spam).
     */
    public static function reportFailure($service, $error, $details = '', $severity = 'critical') {
        global $pdo;

        // Cooldown: don't re-alert for the same service within 1 hour
        $cooldownKey = "api_fail_" . md5($service);
        $lastAlert = $_SESSION[$cooldownKey] ?? 0;
        if ((time() - $lastAlert) < 3600) {
            return; // Still in cooldown
        }

        try {
            // Log to database
            $stmt = $pdo->prepare("INSERT INTO api_health_logs (service, error_message, details, severity, status, checked_at) VALUES (?, ?, ?, ?, 'fail', NOW())");
            $stmt->execute([$service, $error, $details, $severity]);

            // Notify all admins
            $severityIcon = $severity === 'critical' ? '🔴' : ($severity === 'warning' ? '🟡' : '🔵');
            $notifTitle = "$severityIcon API Alert: $service";
            $notifMsg = "An issue has been detected with the $service API.\n\nError: $error\nSeverity: " . ucfirst($severity) . "\nTime: " . date('M d, Y \a\t g:i A');
            if (!empty($details)) $notifMsg .= "\n\nDetails: $details";

            add_notification_to_admins($notifTitle, $notifMsg, 'warning', '../admin/api-health.php');

            // Set cooldown
            $_SESSION[$cooldownKey] = time();

            error_log("[APIHealthMonitor] FAILURE: $service — $error");
        } catch (Exception $e) {
            error_log("[APIHealthMonitor] Failed to log failure: " . $e->getMessage());
        }
    }

    /**
     * Log a successful API check.
     */
    public static function reportSuccess($service) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("INSERT INTO api_health_logs (service, error_message, details, severity, status, checked_at) VALUES (?, '', '', 'info', 'ok', NOW())");
            $stmt->execute([$service]);
        } catch (Exception $e) {}
    }

    /**
     * Check Firebase service account validity.
     */
    public static function checkFirebase() {
        $result = ['service' => 'Firebase', 'status' => 'ok', 'message' => '', 'details' => []];

        // Check service account file
        $saPath = null;
        $candidates = [
            __DIR__ . '/../firebase-service-account.json',
            __DIR__ . '/../service-account.json',
            __DIR__ . '/../wqsnotification-firebase-adminsdk.json',
        ];
        $envPath = getenv('FIREBASE_SERVICE_ACCOUNT_PATH');
        if ($envPath && file_exists($envPath)) $saPath = $envPath;
        if (!$saPath) {
            foreach ($candidates as $path) {
                if (file_exists($path)) { $saPath = $path; break; }
            }
        }

        if (!$saPath) {
            $result['status'] = 'warning';
            $result['message'] = 'No Firebase service account JSON found. Running in simulation mode.';
            self::reportFailure('Firebase', 'Service account file not found', 'Push notifications are simulated, not delivered.', 'warning');
            return $result;
        }

        // Validate service account JSON
        $sa = json_decode(file_get_contents($saPath), true);
        if (!$sa) {
            $result['status'] = 'fail';
            $result['message'] = 'Firebase service account JSON is invalid or corrupted.';
            self::reportFailure('Firebase', 'Service account JSON is invalid', "File: $saPath", 'critical');
            return $result;
        }

        $requiredFields = ['type', 'project_id', 'private_key', 'client_email'];
        $missing = [];
        foreach ($requiredFields as $f) {
            if (empty($sa[$f])) $missing[] = $f;
        }
        if (!empty($missing)) {
            $result['status'] = 'fail';
            $result['message'] = 'Firebase service account is missing required fields: ' . implode(', ', $missing);
            self::reportFailure('Firebase', 'Missing service account fields', implode(', ', $missing), 'critical');
            return $result;
        }

        $result['details'] = [
            'project_id' => $sa['project_id'],
            'client_email' => $sa['client_email'],
            'file' => basename($saPath),
        ];

        // Try to get access token
        $ch = curl_init('https://oauth2.googleapis.com/token');
        $header = self::base64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $now = time();
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

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $result['status'] = 'fail';
            $result['message'] = 'Failed to connect to Firebase OAuth: ' . $curlErr;
            self::reportFailure('Firebase', 'OAuth connection failed', $curlErr, 'critical');
            return $result;
        }

        $data = json_decode($resp, true);
        if (!isset($data['access_token'])) {
            $result['status'] = 'fail';
            $errMsg = $data['error_description'] ?? $data['error'] ?? 'Unknown error';
            $result['message'] = 'Firebase authentication failed: ' . $errMsg;
            self::reportFailure('Firebase', 'OAuth token request failed', $errMsg, 'critical');
            return $result;
        }

        $result['message'] = 'Firebase credentials valid. Service account: ' . $sa['client_email'];
        self::reportSuccess('Firebase');
        return $result;
    }

    /**
     * Check SMTP email configuration.
     */
    public static function checkSMTP() {
        global $pdo;
        $result = ['service' => 'SMTP Email', 'status' => 'ok', 'message' => '', 'details' => []];

        $host = get_setting($pdo, 'broadcast_smtp_host');
        if (empty($host)) {
            $result['status'] = 'warning';
            $result['message'] = 'SMTP host not configured. Falling back to PHP mail().';
            return $result;
        }

        $port = get_setting($pdo, 'broadcast_smtp_port', '587');
        $secure = get_setting($pdo, 'broadcast_smtp_secure', 'tls');
        $user = get_setting($pdo, 'broadcast_smtp_user');

        $result['details'] = [
            'host' => $host,
            'port' => $port,
            'security' => $secure,
            'user' => $user ? '(configured)' : '(not set)',
        ];

        // Try to connect
        $timeout = 5;
        $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
        $sock_host = ($secure === 'ssl') ? "ssl://$host" : $host;
        $socket = @stream_socket_client("$sock_host:$port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$socket) {
            $result['status'] = 'fail';
            $result['message'] = "Cannot connect to SMTP server $host:$port — $errstr ($errno)";
            self::reportFailure('SMTP', "Connection failed to $host:$port", $errstr, 'critical');
            return $result;
        }

        // Read greeting
        fgets($socket);
        fclose($socket);

        $result['message'] = "SMTP server $host:$port is reachable.";
        self::reportSuccess('SMTP');
        return $result;
    }

    /**
     * Check Firebase client-side config.
     */
    public static function checkFirebaseConfig() {
        $result = ['service' => 'Firebase Client Config', 'status' => 'ok', 'message' => '', 'details' => []];
        $config = get_firebase_js_config();

        $result['details'] = [
            'apiKey' => substr($config['apiKey'] ?? '', 0, 8) . '...',
            'projectId' => $config['projectId'] ?? 'N/A',
            'appId' => substr($config['appId'] ?? '', 0, 15) . '...',
        ];

        $placeholders = ['YOUR_', 'xxx', 'placeholder', 'changeme'];
        foreach ($config as $key => $val) {
            foreach ($placeholders as $ph) {
                if (stripos($val, $ph) !== false) {
                    $result['status'] = 'warning';
                    $result['message'] = "Firebase config key '$key' appears to be a placeholder value.";
                    return $result;
                }
            }
        }

        if (empty($config['apiKey']) || empty($config['projectId'])) {
            $result['status'] = 'fail';
            $result['message'] = 'Firebase client config has empty required fields.';
            self::reportFailure('Firebase Config', 'Empty required fields', 'apiKey or projectId missing', 'critical');
            return $result;
        }

        $result['message'] = 'Firebase client configuration is valid.';
        self::reportSuccess('Firebase Config');
        return $result;
    }

    /**
     * Check database connectivity.
     */
    public static function checkDatabase() {
        global $pdo;
        $result = ['service' => 'Database', 'status' => 'ok', 'message' => '', 'details' => []];

        try {
            $start = microtime(true);
            $pdo->query("SELECT 1");
            $latency = round((microtime(true) - $start) * 1000, 2);
            $result['details'] = ['latency_ms' => $latency];
            $result['message'] = "Database connected. Latency: {$latency}ms";
            self::reportSuccess('Database');
        } catch (Exception $e) {
            $result['status'] = 'fail';
            $result['message'] = 'Database connection failed: ' . $e->getMessage();
            self::reportFailure('Database', $e->getMessage(), '', 'critical');
        }

        return $result;
    }

    /**
     * Run all health checks.
     */
    public static function runAllChecks() {
        $results = [];
        try { $results[] = self::checkDatabase(); } catch (Throwable $e) {
            $results[] = ['service' => 'Database', 'status' => 'fail', 'message' => 'Check failed: ' . $e->getMessage(), 'details' => []];
        }
        try { $results[] = self::checkFirebase(); } catch (Throwable $e) {
            $results[] = ['service' => 'Firebase', 'status' => 'fail', 'message' => 'Check failed: ' . $e->getMessage(), 'details' => []];
        }
        try { $results[] = self::checkFirebaseConfig(); } catch (Throwable $e) {
            $results[] = ['service' => 'Firebase Client Config', 'status' => 'fail', 'message' => 'Check failed: ' . $e->getMessage(), 'details' => []];
        }
        try { $results[] = self::checkSMTP(); } catch (Throwable $e) {
            $results[] = ['service' => 'SMTP Email', 'status' => 'fail', 'message' => 'Check failed: ' . $e->getMessage(), 'details' => []];
        }
        return $results;
    }

    /**
     * Get recent health logs.
     */
    public static function getRecentLogs($limit = 50, $service = '') {
        global $pdo;
        try {
            $where = '';
            $params = [];
            if (!empty($service)) {
                $where = ' WHERE service = ? ';
                $params[] = $service;
            }
            $limitInt = (int) $limit;
            $stmt = $pdo->prepare("SELECT * FROM api_health_logs $where ORDER BY checked_at DESC LIMIT $limitInt");
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get current status summary.
     */
    public static function getStatusSummary() {
        global $pdo;
        try {
            // Get latest status per service
            $stmt = $pdo->query("SELECT service, status, error_message, checked_at FROM api_health_logs WHERE id IN (SELECT MAX(id) FROM api_health_logs GROUP BY service) ORDER BY checked_at DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    private static function base64url($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
