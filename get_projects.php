<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

try {

    // === Fetch all projects ===
    $projects = $pdo->query("SELECT * FROM projects WHERE is_visible = 1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($projects as &$p) {
        $imgs = $pdo->prepare("SELECT image_path FROM project_images WHERE project_id = ?");
        $imgs->execute([$p['id']]);
        $p['images'] = $imgs->fetchAll(PDO::FETCH_COLUMN);
    }

    echo json_encode($projects);

} catch (PDOException $e) {
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
}
?>
