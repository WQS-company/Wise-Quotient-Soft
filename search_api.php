<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$terms = array_filter(explode(' ', $q));

$results = [];

if (!empty($terms)) {
    // 1. Search Services
    $service_clauses = [];
    $service_params = [];
    foreach ($terms as $term) {
        $service_clauses[] = "(name LIKE ? OR description LIKE ? OR features LIKE ?)";
        $service_params[] = "%$term%";
        $service_params[] = "%$term%";
        $service_params[] = "%$term%";
    }
    $sql = "SELECT id, name, description, icon, category, border_color FROM services WHERE is_active = 1 AND (" . implode(" OR ", $service_clauses) . ") LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($service_params);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($services as $s) {
        $results[] = [
            'type' => 'Service',
            'title' => $s['name'],
            'desc' => $s['description'],
            'url' => 'services.php',
            'image' => null,
            'icon' => $s['icon'],
            'color' => $s['border_color']
        ];
    }

    // 2. Search Projects
    $project_clauses = [];
    $project_params = [];
    foreach ($terms as $term) {
        $project_clauses[] = "(p.title LIKE ? OR p.description LIKE ?)";
        $project_params[] = "%$term%";
        $project_params[] = "%$term%";
    }
    $sql = "SELECT p.id, p.title, p.description, 
            (SELECT image_path FROM project_images WHERE project_id = p.id ORDER BY id ASC LIMIT 1) as cover_image 
            FROM projects p WHERE p.is_visible = 1 AND (" . implode(" OR ", $project_clauses) . ") LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($project_params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($projects as $p) {
        $results[] = [
            'type' => 'Project',
            'title' => $p['title'],
            'desc' => strip_tags($p['description']),
            'url' => 'project_details.php?id=' . wqs_encrypt_id($p['id']),
            'image' => $p['cover_image'] ? 'admin/' . $p['cover_image'] : null,
            'icon' => 'fas fa-briefcase',
            'color' => '#ff6600'
        ];
    }

    // 3. Search Leadership Team
    $leadership_clauses = [];
    $leadership_params = [];
    foreach ($terms as $term) {
        $leadership_clauses[] = "(u.name LIKE ? OR lt.designation LIKE ? OR lt.bio LIKE ?)";
        $leadership_params[] = "%$term%";
        $leadership_params[] = "%$term%";
        $leadership_params[] = "%$term%";
    }
    $sql = "SELECT lt.id, lt.designation, lt.bio, u.name, u.picture FROM leadership_team lt 
            JOIN users u ON lt.user_id = u.id 
            WHERE (" . implode(" OR ", $leadership_clauses) . ") LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($leadership_params);
    $leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($leaders as $l) {
        $results[] = [
            'type' => 'Leadership',
            'title' => $l['name'],
            'desc' => $l['designation'] . ' - ' . $l['bio'],
            'url' => 'about.php',
            'image' => $l['picture'] ?: null,
            'icon' => 'fas fa-user',
            'color' => '#00264d'
        ];
    }
}

echo json_encode($results);
