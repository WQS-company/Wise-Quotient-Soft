<?php
// ======= ADVANCED SIGNUP & AI-READY SMART MATCHING SYSTEM =======

require_once __DIR__ . '/config.php';
$conn = $pdo;

function cleanInput($input) {
    return trim(htmlspecialchars($input));
}

function getIP() {
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

function getFingerprint() {
    return substr(md5($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 32);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $data = array_map('cleanInput', $_POST);

    $required = ['full_name', 'school_type', 'institution_name', 'year', 'gender'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            echo json_encode(['status' => 'error', 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required.']);
            exit;
        }
    }

    if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
        exit;
    }

    if (!is_numeric($data['year']) || (int)$data['year'] < 1900 || (int)$data['year'] > date('Y') + 5) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid academic year.']);
        exit;
    }

    $schoolTypes = ['primary', 'secondary', 'college_of_education', 'polytechnic', 'diploma', 'university'];
    if (!in_array($data['school_type'], $schoolTypes)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid school type.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ? AND institution_name = ? AND year = ?");
    $stmt->execute([$data['full_name'], $data['institution_name'], $data['year']]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'User already registered.']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO users (full_name, school_type, institution_name, department, level, class_name, section, year, gender, email, phone, state, country, city, interests, device_fingerprint, ip_address)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['full_name'], $data['school_type'], $data['institution_name'],
        $data['department'] ?? '', $data['level'] ?? '', $data['class_name'] ?? '',
        $data['section'] ?? '', $data['year'], $data['gender'],
        $data['email'] ?? '', $data['phone'] ?? '',
        $data['state'] ?? '', $data['country'] ?? '', $data['city'] ?? '',
        $data['interests'] ?? '', getFingerprint(), getIP()
    ]);

    $userId = $conn->lastInsertId();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $keyElements = [
        strtolower(preg_replace('/\s+/', '_', $user['institution_name'])),
        $user['school_type']
    ];

    switch ($user['school_type']) {
        case 'primary':
        case 'secondary':
            array_push($keyElements, strtolower($user['class_name']), strtolower($user['section']));
            break;
        default:
            array_push($keyElements, strtolower($user['department']), strtolower($user['level']));
            break;
    }

    $keyElements[] = $user['year'];
    $groupKey = implode('_', array_filter($keyElements));
    $groupHash = md5($groupKey);

    $stmt = $conn->prepare("SELECT id FROM groups WHERE group_hash = ?");
    $stmt->execute([$groupHash]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        $stmt = $conn->prepare("INSERT INTO groups (group_hash, group_name, school_type, institution_name, member_count)
                                VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$groupHash, ucfirst(str_replace('_', ' ', $groupKey)), $user['school_type'], $user['institution_name']]);
        $groupId = $conn->lastInsertId();
    } else {
        $groupId = $group['id'];
        $conn->prepare("UPDATE groups SET member_count = member_count + 1 WHERE id = ?")->execute([$groupId]);
    }

    $stmt = $conn->prepare("INSERT IGNORE INTO group_members (group_id, user_id) VALUES (?, ?)");
    $stmt->execute([$groupId, $userId]);

    $stmt = $conn->prepare("SELECT * FROM users WHERE school_type = ? AND institution_name = ? AND id != ?");
    $stmt->execute([$user['school_type'], $user['institution_name'], $userId]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $matches = [];

    foreach ($candidates as $c) {
        $score = 0;

        if ($user['institution_name'] == $c['institution_name']) $score += 20;
        if ($user['year'] == $c['year']) $score += 10;
        if ($user['gender'] == $c['gender']) $score += 5;

        switch ($user['school_type']) {
            case 'primary':
            case 'secondary':
                $score += ($user['class_name'] == $c['class_name']) ? 30 : 0;
                $score += ($user['section'] == $c['section']) ? 20 : 0;
                break;
            default:
                similar_text($user['department'], $c['department'], $deptSim);
                $score += ($deptSim > 60) ? 25 : 0;
                $score += ($user['level'] == $c['level']) ? 15 : 0;
                break;
        }

        if (!empty($user['interests']) && !empty($c['interests'])) {
            $common = array_intersect(
                array_map('trim', explode(',', strtolower($user['interests']))),
                array_map('trim', explode(',', strtolower($c['interests']))),
            );
            $score += count($common) * 5;
        }

        if ($score >= 25) {
            $c['score'] = $score;
            $c['match_quality'] = $score >= 80 ? 'High' : ($score >= 50 ? 'Medium' : 'Low');
            $matches[] = $c;
        }
    }

    usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);

    $conn->prepare("DELETE FROM suggested_mates WHERE user_id = ?")->execute([$userId]);
    $stmt = $conn->prepare("INSERT INTO suggested_mates (user_id, match_id, score, match_quality, created_at) VALUES (?, ?, ?, ?, NOW())");

    foreach (array_slice($matches, 0, 10) as $m) {
        $stmt->execute([$userId, $m['id'], $m['score'], $m['match_quality']]);
    }

    $conn->prepare("INSERT INTO audit_logs (user_id, action, description, created_at)
                    VALUES (?, 'signup', ?)")->execute([$userId, "User assigned to group: $groupKey"]);

    echo json_encode(['status' => 'success', 'message' => 'Signup complete. Grouped and matched successfully.']);
    exit;
}
?>
