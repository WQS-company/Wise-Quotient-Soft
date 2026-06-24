<?php
/**
 * WQS SEO Helper — Generates meta tags, Open Graph, Twitter Cards, JSON-LD, canonical URLs
 * Include this AFTER setting $seo array variables in each page, BEFORE </head>
 *
 * Usage in any page:
 *   $seo = [
 *       'title'       => 'Page Title - WQS',
 *       'description' => 'Page description for search engines (150-160 chars)',
 *       'keywords'    => 'keyword1, keyword2, keyword3',
 *       'canonical'   => 'https://wisequotientsoft.com/services.php',
 *       'og_type'     => 'website', // website, article, product, profile
 *       'og_image'    => 'https://wisequotientsoft.com/images/og-default.jpg',
 *       'og_width'    => 1200,
 *       'og_height'   => 630,
 *       'article'     => [ // Only for blog posts
 *           'published_time' => '2025-01-15T10:00:00+01:00',
 *           'modified_time'  => '2025-01-20T14:30:00+01:00',
 *           'author'         => 'Wise Quotient Soft',
 *           'section'        => 'Technology',
 *           'tags'           => ['AI', 'Software', 'Innovation'],
 *       ],
 *       'breadcrumb'  => [ // Optional breadcrumbs
 *           ['name' => 'Home', 'url' => '/'],
 *           ['name' => 'Services', 'url' => '/services.php'],
 *           ['name' => 'Current Page'],
 *       ],
 *       'schema'      => [], // Additional JSON-LD schemas to merge
 *   ];
 */

if (!isset($seo)) $seo = [];

// Defaults
$siteName = 'Wise Quotient Soft';
$siteUrl  = 'https://wisequotientsoft.com';
$siteDesc = 'Wise Quotient Soft builds intelligent, scalable software solutions — custom apps, AI/ML, cloud architecture, and digital transformation for businesses worldwide.';
$defaultImage = $siteUrl . '/images/og-default.jpg';
$pageUrl = $siteUrl . ltrim($_SERVER['REQUEST_URI'] ?? '/', '/');

$title       = $seo['title'] ?? ($page_title ? $page_title . ' - ' . $siteName : $siteName . ' - Intelligent Software, Crafted for Scale');
$description = $seo['description'] ?? $siteDesc;
$keywords    = $seo['keywords'] ?? 'software development, custom software, AI solutions, machine learning, mobile app development, web development, cloud architecture, digital transformation, IT consulting, Wise Quotient Soft, WQS, Nigeria software company';
$canonical   = $seo['canonical'] ?? $pageUrl;
$ogType      = $seo['og_type'] ?? 'website';
$ogImage     = $seo['og_image'] ?? $defaultImage;
$ogWidth     = $seo['og_width'] ?? 1200;
$ogHeight    = $seo['og_height'] ?? 630;
$articleData = $seo['article'] ?? null;
$breadcrumbs = $seo['breadcrumb'] ?? null;
$extraSchema = $seo['schema'] ?? [];

// Clean description to 160 chars max for meta
if (strlen($description) > 160) {
    $description = rtrim(substr($description, 0, 157)) . '...';
}

// ===== OUTPUT META TAGS =====
?>
<!-- SEO Meta Tags -->
<title><?= htmlspecialchars($title) ?></title>
<meta name="description" content="<?= htmlspecialchars($description) ?>">
<meta name="keywords" content="<?= htmlspecialchars($keywords) ?>">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">
<meta name="author" content="<?= $siteName ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="<?= $ogType ?>">
<meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
<meta property="og:title" content="<?= htmlspecialchars($title) ?>">
<meta property="og:description" content="<?= htmlspecialchars($description) ?>">
<meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
<meta property="og:image:width" content="<?= $ogWidth ?>">
<meta property="og:image:height" content="<?= $ogHeight ?>">
<meta property="og:site_name" content="<?= $siteName ?>">
<meta property="og:locale" content="en_US">
<?php if ($articleData): ?>
<meta property="article:published_time" content="<?= htmlspecialchars($articleData['published_time'] ?? '') ?>">
<meta property="article:modified_time" content="<?= htmlspecialchars($articleData['modified_time'] ?? '') ?>">
<meta property="article:author" content="<?= htmlspecialchars($articleData['author'] ?? $siteName) ?>">
<?php if (!empty($articleData['section'])): ?>
<meta property="article:section" content="<?= htmlspecialchars($articleData['section']) ?>">
<?php endif; ?>
<?php if (!empty($articleData['tags'])): ?>
<?php foreach ($articleData['tags'] as $tag): ?>
<meta property="article:tag" content="<?= htmlspecialchars($tag) ?>">
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:site" content="@Wise_Quotient_Soft">
<meta name="twitter:creator" content="@Wise_Quotient_Soft">
<meta name="twitter:title" content="<?= htmlspecialchars($title) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
<meta name="twitter:image:alt" content="<?= htmlspecialchars($title) ?>">

<!-- Additional Meta -->
<meta name="theme-color" content="#0A2D5E">
<meta name="msapplication-TileColor" content="#0A2D5E">

<?php
// ===== OUTPUT JSON-LD STRUCTURED DATA =====
$schemas = [];

// 1. Organization Schema (always present on all pages)
$orgSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    '@id' => $siteUrl . '#organization',
    'name' => $siteName,
    'legalName' => 'Wise Quotient Soft Nigeria',
    'alternateName' => 'WQS',
    'url' => $siteUrl,
    'logo' => [
        '@type' => 'ImageObject',
        'url' => $siteUrl . '/tech.png',
        'width' => 512,
        'height' => 512,
    ],
    'image' => $siteUrl . '/tech.png',
    'description' => 'Wise Quotient Soft is a leading software development company specializing in custom software, AI/ML solutions, mobile and web applications, cloud architecture, and digital transformation.',
    'foundingDate' => '2023',
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => 'No.1 Ibadan Street',
        'addressLocality' => 'Kaduna',
        'addressRegion' => 'Kaduna State',
        'postalCode' => '700001',
        'addressCountry' => 'NG',
    ],
    'contactPoint' => [
        '@type' => 'ContactPoint',
        'telephone' => '+2348077416106',
        'contactType' => 'customer service',
        'email' => 'info@wisequotient.com',
        'availableLanguage' => ['English'],
    ],
    'sameAs' => array_filter([
        'https://www.facebook.com/share/1B3LW3nV7T/',
        'https://www.instagram.com/wise_quotient_soft',
        'https://www.linkedin.com/in/wise-quotient-soft-51933a376',
        'https://x.com/Wise_Quotient_Soft',
        'https://github.com/WQS-company',
        'https://www.youtube.com/channel/UCnpqd2bn7N5DZl1W2lhen3w',
    ]),
    'areaServed' => [
        '@type' => 'Country',
        'name' => 'Nigeria',
    ],
    'knowsAbout' => [
        'Custom Software Development',
        'Mobile Application Development',
        'Web Application Development',
        'Artificial Intelligence',
        'Machine Learning',
        'Cloud Architecture',
        'IT Consulting',
        'Digital Transformation',
    ],
];
$schemas[] = $orgSchema;

// 2. WebSite Schema (homepage only or when explicitly requested)
if (($current_page ?? '') === 'index.php' || !empty($seo['website_schema'])) {
    $websiteSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        '@id' => $siteUrl . '#website',
        'name' => $siteName,
        'url' => $siteUrl,
        'description' => $siteDesc,
        'publisher' => ['@id' => $siteUrl . '#organization'],
        'inLanguage' => 'en-US',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $siteUrl . '/search.php?q={search_term_string}',
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];
    $schemas[] = $websiteSchema;
}

// 3. BreadcrumbList Schema
if ($breadcrumbs && count($breadcrumbs) > 1) {
    $bcItems = [];
    foreach ($breadcrumbs as $i => $bc) {
        $bcItem = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $bc['name'],
        ];
        if (!empty($bc['url'])) {
            $bcItem['item'] = $siteUrl . $bc['url'];
        }
        $bcItems[] = $bcItem;
    }
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $bcItems,
    ];
    $schemas[] = $breadcrumbSchema;
}

// 4. Blog/Article Schema (blog_detail.php)
if ($articleData && ($current_page ?? '') === 'blog_detail.php') {
    $articleSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BlogPosting',
        'headline' => $title,
        'description' => $description,
        'image' => $ogImage,
        'url' => $canonical,
        'datePublished' => $articleData['published_time'] ?? date('c'),
        'dateModified' => $articleData['modified_time'] ?? date('c'),
        'author' => [
            '@type' => 'Organization',
            'name' => $articleData['author'] ?? $siteName,
            'url' => $siteUrl,
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => $siteName,
            'logo' => [
                '@type' => 'ImageObject',
                'url' => $siteUrl . '/tech.png',
            ],
        ],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => $canonical,
        ],
    ];
    if (!empty($articleData['tags'])) {
        $articleSchema['keywords'] = implode(', ', $articleData['tags']);
    }
    $schemas[] = $articleSchema;
}

// 5. Service Schema (services.php)
if (($current_page ?? '') === 'services.php') {
    $serviceSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'serviceType' => 'Software Development',
        'provider' => ['@id' => $siteUrl . '#organization'],
        'areaServed' => [
            '@type' => 'Country',
            'name' => 'Nigeria',
        ],
        'hasOfferCatalog' => [
            '@type' => 'OfferCatalog',
            'name' => 'WQS Software Services',
            'itemListElement' => [
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'Custom Software Development']],
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'Mobile App Development']],
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'Web Application Development']],
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'AI & Machine Learning']],
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'Cloud Architecture']],
                ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => 'IT Consulting & Strategy']],
            ],
        ],
    ];
    $schemas[] = $serviceSchema;
}

// 6. LocalBusiness Schema (contact.php)
if (($current_page ?? '') === 'contact.php') {
    $localBiz = [
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
        '@id' => $siteUrl . '#localbusiness',
        'name' => $siteName,
        'image' => $siteUrl . '/tech.png',
        'url' => $siteUrl,
        'telephone' => '+2348077416106',
        'email' => 'info@wisequotient.com',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => 'No.1 Ibadan Street',
            'addressLocality' => 'Kaduna',
            'addressRegion' => 'Kaduna State',
            'postalCode' => '700001',
            'addressCountry' => 'NG',
        ],
        'geo' => [
            '@type' => 'GeoCoordinates',
            'latitude' => 10.5222,
            'longitude' => 7.4383,
        ],
        'openingHoursSpecification' => [
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'opens' => '09:00',
            'closes' => '18:00',
        ],
        'priceRange' => '$$',
    ];
    $schemas[] = $localBiz;
}

// 7. ItemList for portfolio items
if (($current_page ?? '') === 'portforlio.php' && !empty($seo['portfolio_items'])) {
    $portfolioSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => 'WQS Software Portfolio',
        'description' => 'Showcase of software projects delivered by Wise Quotient Soft',
        'numberOfItems' => count($seo['portfolio_items']),
        'itemListElement' => [],
    ];
    foreach ($seo['portfolio_items'] as $pi => $item) {
        $portfolioSchema['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $pi + 1,
            'url' => $siteUrl . '/portforlio.php#project-' . ($item['id'] ?? $pi),
            'name' => $item['title'] ?? '',
        ];
    }
    $schemas[] = $portfolioSchema;
}

// Merge any extra schemas from page
$schemas = array_merge($schemas, $extraSchema);

// Output all JSON-LD
echo "\n<!-- JSON-LD Structured Data -->\n";
foreach ($schemas as $schema) {
    echo '<script type="application/ld+json">' . "\n";
    echo json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo '</script>' . "\n";
}

// ===== OUTPUT BREADCRUMB NAVIGATION (HTML) =====
if ($breadcrumbs && count($breadcrumbs) > 1) {
    echo '<nav aria-label="Breadcrumb" class="seo-breadcrumbs" itemscope itemtype="https://schema.org/BreadcrumbList">';
    echo '<ol itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" style="list-style:none;padding:0.5rem 0;margin:0;display:flex;flex-wrap:wrap;gap:0.35rem;font-size:0.82rem;color:#64748b;">';
    foreach ($breadcrumbs as $i => $bc) {
        echo '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" style="display:inline-flex;align-items:center;gap:0.35rem;">';
        if (!empty($bc['url']) && $i < count($breadcrumbs) - 1) {
            echo '<a itemprop="item" href="' . htmlspecialchars($bc['url']) . '" itemprop="name" style="color:#2563eb;text-decoration:none;font-weight:500;">' . htmlspecialchars($bc['name']) . '</a>';
        } else {
            echo '<span itemprop="name" style="font-weight:600;color:#0f172a;">' . htmlspecialchars($bc['name']) . '</span>';
        }
        echo '<meta itemprop="position" content="' . ($i + 1) . '">';
        if ($i < count($breadcrumbs) - 1) {
            echo '<span style="color:#cbd5e1;">/</span>';
        }
        echo '</li>';
    }
    echo '</ol>';
    echo '</nav>';
}
?>
