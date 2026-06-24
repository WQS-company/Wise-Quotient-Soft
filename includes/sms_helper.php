<?php
// Termii SMS Helper

if (!function_exists('get_setting')) {
    function get_setting($pdo, $key, $default = '') {
        $stmt = $pdo->prepare("SELECT setting_value FROM footer_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    }
}

if (!function_exists('send_termii_sms')) {
    function send_termii_sms($phone, $message, $pdo) {
        $api_key = get_setting($pdo, 'broadcast_termii_api_key');
        $sender_id = get_setting($pdo, 'broadcast_termii_sender_id');
        $base_url = get_setting($pdo, 'broadcast_termii_base_url', 'https://api.ng.termii.com');
        $channel = get_setting($pdo, 'broadcast_termii_channel', 'generic');

        if (empty($api_key) || empty($sender_id)) {
            return ['success' => false, 'message' => 'Termii credentials not configured.'];
        }

        // Normalize phone number (basic normalization for Nigeria: 080... -> 23480...)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strpos($phone, '0') === 0) {
            $phone = '234' . substr($phone, 1);
        }

        $payload = [
            "to" => $phone,
            "from" => $sender_id,
            "sms" => $message,
            "type" => "plain",
            "channel" => $channel,
            "api_key" => $api_key
        ];

        $ch = curl_init("$base_url/api/sms/send");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'cURL Error: ' . $error];
        }

        $resData = json_decode($response, true);
        if (isset($resData['message_id'])) {
            return ['success' => true, 'message_id' => $resData['message_id']];
        } else {
            return ['success' => false, 'message' => 'Termii API Error: ' . ($resData['message'] ?? 'Unknown error')];
        }
    }
}
