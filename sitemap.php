<?php
/**
 * sitemap.php — Dynamic XML Sitemap for Wise Quotient Soft
 * Generates a proper XML sitemap with all public pages and dynamic content
 * Access at: https://wisequotientsoft.com/sitemap.php
 */

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex'); // Sitemap itself shouldn't be indexed

$siteUrl = 'https://wisequotientsoft.com';
$lastmod = date('c');

// Static pages
$staticPages = [
    ['loc' => '/',                          'changefreq' => 'daily',   'priority' => '1.0'],
    ['loc' => '/services.php',              'changefreq' => 'weekly',  'priority' => '0.9'],
    ['loc' => '/about.php',                 'changefreq' => 'monthly', 'priority' => '0.8'],
    ['loc' => '/team.php',                  'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => '/portforlio.php',            'changefreq' => 'weekly',  'priority' => '0.9'],
    ['loc' => '/blog.php',                  'changefreq' => 'daily',   'priority' => '0.9'],
    ['loc' => '/contact.php',               'changefreq' => 'monthly', 'priority' => '0.7'],
    ['loc' => '/scholarships.php',          'changefreq' => 'weekly',  'priority' => '0.8'],
    ['loc' => '/privacy.php',               'changefreq' => 'yearly',  'priority' => '0.3'],
    ['loc' => '/terms.php',                 'changefreq' => 'yearly',  'priority' => '0.3'],
    ['loc' => '/login.php',                 'changefreq' => 'monthly', 'priority' => '0.4'],
    ['loc' => '/register.php',              'changefreq' => 'monthly', 'priority' => '0.5'],
];

// Dynamic pages from database
$dynamicPages = [];

try {
    require_once __DIR__ . '/config.php';

    // Published blog posts
    try {
        $blogStmt = $pdo->query("SELECT id, slug, updated_at, created_at FROM blog_posts WHERE status = 'published' ORDER BY created_at DESC");
        $blogPosts = $blogStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($blogPosts as $post) {
            $slug = !empty($post['slug']) ? $post['slug'] : $post['id'];
            $modDate = !empty($post['updated_at']) ? date('c', strtotime($post['updated_at'])) : date('c', strtotime($post['created_at']));
            $dynamicPages[] = [
                'loc' => '/blog_detail.php?id=' . $slug,
                'lastmod' => $modDate,
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }
    } catch (Exception $e) {}

    // Active scholarships
    try {
        $schStmt = $pdo->query("SELECT id, slug, updated_at, created_at FROM scholarships WHERE status IN ('active','published') ORDER BY created_at DESC");
        $scholarships = $schStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($scholarships as $sch) {
            $slug = !empty($sch['slug']) ? $sch['slug'] : $sch['id'];
            $modDate = !empty($sch['updated_at']) ? date('c', strtotime($sch['updated_at'])) : date('c', strtotime($sch['created_at']));
            $dynamicPages[] = [
                'loc' => '/scholarship.php?id=' . $slug,
                'lastmod' => $modDate,
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }
    } catch (Exception $e) {}

    // Active portfolio items
    try {
        $portStmt = $pdo->query("SELECT id, updated_at, created_at FROM portfolio WHERE status = 'active' ORDER BY created_at DESC");
        $portfolios = $portStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($portfolios as $item) {
            $modDate = !empty($item['updated_at']) ? date('c', strtotime($item['updated_at'])) : date('c', strtotime($item['created_at']));
            $dynamicPages[] = [
                'loc' => '/portforlio.php#project-' . $item['id'],
                'lastmod' => $modDate,
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];
        }
    } catch (Exception $e) {}

} catch (Exception $e) {
    // Database not available, just output static pages
}

// Merge static and dynamic pages
$allPages = array_merge($staticPages, $dynamicPages);

// Output XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
echo '        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
echo '        xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9' . "\n";
echo '        http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">' . "\n";

foreach ($allPages as $page) {
    $url = $siteUrl . $page['loc'];
    $mod = $page['lastmod'] ?? $lastmod;
    $freq = $page['changefreq'] ?? 'monthly';
    $pri = $page['priority'] ?? '0.5';

    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($url) . '</loc>' . "\n";
    echo '    <lastmod>' . $mod . '</lastmod>' . "\n";
    echo '    <changefreq>' . $freq . '</changefreq>' . "\n";
    echo '    <priority>' . $pri . '</priority>' . "\n";
    echo '  </url>' . "\n";
}

echo '</urlset>';
?>
