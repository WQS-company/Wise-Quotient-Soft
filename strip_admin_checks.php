<?php
$dir = __DIR__ . '/admin';
$files = glob($dir . '/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    // There are a few variations of the admin check
    $content = preg_replace('/if\s*\(\$user_role\s*!==\s*\'admin\'\)\s*\{\s*header\("Location:\s*\.\.\/login\.php"\);\s*exit;\s*\}/is', '', $content);
    $content = preg_replace('/if\s*\(\$user_role\s*!==\s*\'admin\'\)\s*\{\s*\}/is', '', $content); // In case it's empty
    // specifically handle:
    // if ($user_role !== 'admin') {
    //     header("Location: ../login.php"); exit;
    // }
    $content = preg_replace("/if \(\\$user_role !== 'admin'\) \{\s*header\(\"Location: \.\.\/login\.php\"\); exit;\s*\}/s", "", $content);
    file_put_contents($file, $content);
}
echo "Removed hardcoded admin checks.";
?>
