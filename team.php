<?php
$page_title = 'Our Team - Meet the Innovation Leaders | Wise Quotient Soft';
$seo = [
    'title'       => 'Our Team - Meet the Innovation Leaders | Wise Quotient Soft',
    'description' => 'Meet the talented team behind Wise Quotient Soft — software engineers, designers, and tech leaders building innovative solutions from Nigeria to the world.',
    'keywords'    => 'WQS team, Wise Quotient Soft team, software engineers Nigeria, tech team Kaduna, meet our team, leadership',
    'canonical'   => 'https://wisequotientsoft.com/team.php',
    'og_image'    => 'https://wisequotientsoft.com/images/og-team.jpg',
    'breadcrumb'  => [
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Our Team', 'url' => '/team.php'],
    ],
];
require_once __DIR__ . '/includes/public_header.php';

// Fetch active team members with their full profile data
$teamMembers = [];
try {
    $stmt = $pdo->query("
        SELECT tm.*, u.name, u.email, u.phone, u.picture, u.bio, u.profession, u.company,
               u.linkedin_url, u.twitter_url, u.github_url, u.facebook_url, u.instagram_url, u.website_url
        FROM team_members tm
        JOIN users u ON tm.user_id = u.id
        WHERE tm.is_active = 1
        ORDER BY tm.display_order ASC
    ");
    $teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>
<style>
    .team-hero {
        background: linear-gradient(135deg, #030712 0%, #0A2D5E 50%, #1a3f80 100%);
        padding: 5.5rem 0 4rem;
        color: white;
        text-align: center;
        position: relative;
        overflow: hidden;
    }
    .team-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        opacity: 0.5;
    }
    .team-hero-glow {
        position: absolute; width: 600px; height: 600px; border-radius: 50%;
        background: radial-gradient(circle, rgba(225, 85, 1, 0.2) 0%, transparent 70%);
        top: -200px; right: -100px; filter: blur(100px); pointer-events: none;
    }
    .team-hero h1 {
        font-size: 2.8rem;
        font-weight: 800;
        margin-bottom: 0.75rem;
        letter-spacing: -0.02em;
        position: relative;
    }
    .team-hero p {
        font-size: 1.1rem;
        color: rgba(255,255,255,0.6);
        max-width: 600px;
        margin: 0 auto;
        position: relative;
    }

    .team-member-card {
        background: white;
        border-radius: 20px;
        border: 1px solid rgba(0,0,0,0.06);
        box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        overflow: hidden;
        transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .team-member-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 40px rgba(0,0,0,0.1);
        border-color: rgba(10,45,94,0.15);
    }

    .team-card-top {
        position: relative;
        height: 120px;
        background: linear-gradient(135deg, #0A2D5E, #1a3f80);
        overflow: hidden;
    }
    .team-card-top::after {
        content: '';
        position: absolute;
        bottom: 0; left: 0; right: 0;
        height: 40px;
        background: linear-gradient(to top, white, transparent);
    }
    .team-card-top .pattern {
        position: absolute;
        inset: 0;
        opacity: 0.1;
        background: radial-gradient(circle at 20% 50%, white 1px, transparent 1px);
        background-size: 20px 20px;
    }

    .team-avatar-wrapper {
        position: relative;
        margin: -50px auto 0;
        width: 90px;
        height: 90px;
        z-index: 2;
    }
    .team-avatar {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        background: #f1f5f9;
    }
    .team-avatar-placeholder {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        background: linear-gradient(135deg, #0A2D5E, #2563eb);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
        font-weight: 700;
    }

    .team-card-body {
        padding: 1.25rem 1.5rem 1.5rem;
        flex: 1;
        display: flex;
        flex-direction: column;
        text-align: center;
    }
    .team-name {
        font-size: 1.1rem;
        font-weight: 800;
        color: #0A2D5E;
        margin-bottom: 0.15rem;
    }
    .team-designation {
        font-size: 0.82rem;
        color: #E15501;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 0.75rem;
    }
    .team-bio {
        font-size: 0.85rem;
        color: #64748b;
        line-height: 1.6;
        flex: 1;
    }
    .team-social {
        display: flex;
        justify-content: center;
        gap: 0.35rem;
        padding-top: 1rem;
        border-top: 1px solid #f1f5f9;
        margin-top: 1rem;
    }
    .team-social a {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        transition: all 0.25s;
        text-decoration: none;
    }
    .team-social a:hover {
        transform: translateY(-2px);
    }

    .team-contact {
        display: flex;
        justify-content: center;
        gap: 1rem;
        padding-top: 0.75rem;
        font-size: 0.78rem;
        color: #94a3b8;
    }
    .team-contact a {
        color: #64748b;
        text-decoration: none;
    }
    .team-contact a:hover {
        color: #0A2D5E;
    }

    .section-header {
        text-align: center;
        margin-bottom: 3rem;
    }
    .section-header h2 {
        font-size: 2rem;
        font-weight: 800;
        color: #0A2D5E;
        margin-bottom: 0.75rem;
    }
    .section-header p {
        color: #64748b;
        max-width: 600px;
        margin: 0 auto;
        font-size: 1rem;
    }

    @media (max-width: 576px) {
        .team-hero h1 { font-size: 2rem; }
        .team-card-body { padding: 1rem; }
    }
</style>

<!-- Hero -->
<section class="team-hero">
    <div class="team-hero-glow"></div>
    <div class="container">
        <h1>Meet Our Team</h1>
        <p>The talented people behind Wise Quotient Soft — building solutions that drive digital transformation across industries.</p>
    </div>
</section>

<!-- Team Grid -->
<section class="py-5" style="background:#f8fafc;">
    <div class="container">
        <?php if (empty($teamMembers)): ?>
            <div class="text-center py-5">
                <div style="font-size:4rem;margin-bottom:1rem;">👥</div>
                <h4 class="fw-bold">Our Team Is Growing</h4>
                <p class="text-muted">Team profiles are being set up. Check back soon!</p>
            </div>
        <?php else: ?>
            <div class="section-header">
                <h2>Our Leadership & Team</h2>
                <p>Get to know the experts who make our vision a reality.</p>
            </div>
            <div class="row g-4">
                <?php foreach ($teamMembers as $m): ?>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <div class="team-member-card">
                            <div class="team-card-top">
                                <div class="pattern"></div>
                            </div>

                            <!-- Avatar -->
                            <div class="team-avatar-wrapper">
                                <?php if (!empty($m['picture'])): ?>
                                    <img src="<?= htmlspecialchars($m['picture']) ?>" class="team-avatar" alt="<?= htmlspecialchars($m['name']) ?>">
                                <?php else: ?>
                                    <div class="team-avatar-placeholder">
                                        <?= strtoupper(substr($m['name'] ?? 'U', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="team-card-body">
                                <div class="team-name"><?= htmlspecialchars($m['name']) ?></div>
                                <div class="team-designation"><?= htmlspecialchars($m['designation']) ?></div>

                                <?php if (!empty($m['bio'])): ?>
                                    <div class="team-bio"><?= nl2br(htmlspecialchars(substr($m['bio'], 0, 150))) ?><?= strlen($m['bio'] ?? '') > 150 ? '…' : '' ?></div>
                                <?php elseif (!empty($m['profession'])): ?>
                                    <div class="team-bio"><?= htmlspecialchars($m['profession']) ?></div>
                                <?php else: ?>
                                    <div class="team-bio text-muted" style="font-style:italic;">Team member</div>
                                <?php endif; ?>

                                <!-- Social links from user profile -->
                                <?php
                                $socialLinks = [];
                                if (!empty($m['linkedin_url']) && $m['linkedin_url'] !== '#') $socialLinks[] = ['icon' => 'fab fa-linkedin-in', 'url' => $m['linkedin_url'], 'color' => '#0077b5', 'bg' => '#e8f4f9'];
                                if (!empty($m['twitter_url']) && $m['twitter_url'] !== '#') $socialLinks[] = ['icon' => 'fab fa-twitter', 'url' => $m['twitter_url'], 'color' => '#1da1f2', 'bg' => '#e8f5fe'];
                                if (!empty($m['github_url']) && $m['github_url'] !== '#') $socialLinks[] = ['icon' => 'fab fa-github', 'url' => $m['github_url'], 'color' => '#24292e', 'bg' => '#f0f0f0'];
                                if (!empty($m['facebook_url']) && $m['facebook_url'] !== '#') $socialLinks[] = ['icon' => 'fab fa-facebook-f', 'url' => $m['facebook_url'], 'color' => '#1877f2', 'bg' => '#eaf1fd'];
                                if (!empty($m['instagram_url']) && $m['instagram_url'] !== '#') $socialLinks[] = ['icon' => 'fab fa-instagram', 'url' => $m['instagram_url'], 'color' => '#e4405f', 'bg' => '#fdeef0'];
                                if (!empty($m['website_url']) && $m['website_url'] !== '#') $socialLinks[] = ['icon' => 'fas fa-globe', 'url' => $m['website_url'], 'color' => '#0A2D5E', 'bg' => '#eef2f7'];
                                ?>
                                <?php if (!empty($socialLinks)): ?>
                                    <div class="team-social">
                                        <?php foreach ($socialLinks as $s): ?>
                                            <a href="<?= htmlspecialchars($s['url']) ?>" target="_blank" rel="noopener" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>;" title="<?= strtoupper(str_replace(['fab fa-','fas fa-'], '', $s['icon'])) ?>">
                                                <i class="<?= $s['icon'] ?>"></i>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Contact -->
                                <div class="team-contact">
                                    <?php if (!empty($m['email'])): ?>
                                        <a href="mailto:<?= htmlspecialchars($m['email']) ?>"><i class="fas fa-envelope"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($m['phone'])): ?>
                                        <a href="tel:<?= htmlspecialchars($m['phone']) ?>"><i class="fas fa-phone"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Join Us CTA -->
<section class="py-5" style="background:linear-gradient(135deg, #030712, #0A2D5E);color:white;text-align:center;">
    <div class="container">
        <h2 class="fw-bold mb-3">Want to Join the Team?</h2>
        <p style="color:rgba(255,255,255,0.6);max-width:500px;margin:0 auto 1.5rem;">
            We're always looking for talented people who share our passion for technology and innovation.
        </p>
        <a href="contact.php" class="btn rounded-pill px-5 py-2 fw-bold" style="background:#E15501;border:none;color:white;">
            <i class="fas fa-paper-plane me-2"></i>Get in Touch
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/public_footer.php'; ?>
