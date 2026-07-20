<?php
$page_title = $page_title ?? 'Admin';
$active_nav = $active_nav ?? '';
$breadcrumb = $breadcrumb ?? [];

function admin_nav_class(string $key, string $active): string
{
    return $key === $active ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CPVIA Admin - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/webp" href="../assets/images/cpvia-fav-icon.webp">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <div class="admin-sidebar-overlay" id="adminSidebarOverlay"></div>

        <aside class="admin-sidebar" id="adminSidebar">
            <div class="admin-sidebar-brand">
                <img src="../assets/images/header-logo.png" alt="CPVIA Logo">
            </div>

            <nav class="admin-nav">
                <p class="admin-nav-section-label">Main</p>
                <a href="index.php" class="<?php echo admin_nav_class('dashboard', $active_nav); ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"></rect><rect x="14" y="3" width="7" height="5"></rect><rect x="14" y="12" width="7" height="9"></rect><rect x="3" y="16" width="7" height="5"></rect></svg></span>
                    Dashboard
                </a>
                <a href="applications.php" class="<?php echo admin_nav_class('applications', $active_nav); ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg></span>
                    Applications
                </a>
                <a href="jobs.php" class="<?php echo admin_nav_class('jobs', $active_nav); ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg></span>
                    Jobs
                </a>
                <a href="add_job.php" class="<?php echo admin_nav_class('add_job', $active_nav); ?>">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg></span>
                    Add Job
                </a>

                <div class="admin-nav-divider"></div>
                <p class="admin-nav-section-label">General</p>
                <a href="../careers.php" target="_blank" rel="noopener">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></span>
                    Back to Website
                </a>
                <a href="logout.php" onclick="return confirm('Are you sure you want to logout?');">
                    <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg></span>
                    Logout
                </a>
            </nav>

            <div class="admin-sidebar-footer">
                <a href="../careers.php" target="_blank" rel="noopener" style="color: rgba(255,255,255,0.5); font-size: 0.72rem; text-decoration: none;">
                    &copy; <?php echo date('Y'); ?> CPVIA
                </a>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-topbar">
                <div class="admin-topbar-left">
                    <button class="admin-hamburger" id="adminHamburger" aria-label="Toggle Menu">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </button>
                    <div class="admin-page-heading">
                        <h1><?php echo htmlspecialchars($page_title); ?></h1>
                        <?php if (!empty($breadcrumb)): ?>
                        <div class="admin-breadcrumb">
                            <?php foreach ($breadcrumb as $i => $crumb): ?>
                                <?php if ($i > 0): ?><span>/</span><?php endif; ?>
                                <?php if (!empty($crumb['url'])): ?>
                                    <a href="<?php echo htmlspecialchars($crumb['url']); ?>"><?php echo htmlspecialchars($crumb['label']); ?></a>
                                <?php else: ?>
                                    <span class="current"><?php echo htmlspecialchars($crumb['label']); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="admin-topbar-right">
                    <button class="admin-icon-btn" title="Notifications" aria-label="Notifications">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <span class="dot"></span>
                    </button>
                    <?php
                        $admin_display_name = function_exists('cpvia_current_admin_name') ? cpvia_current_admin_name() : 'Admin';
                        $admin_initial = strtoupper(substr($admin_display_name, 0, 1));
                    ?>
                    <div class="admin-profile">
                        <div class="avatar"><?php echo htmlspecialchars($admin_initial ?: 'A'); ?></div>
                        <div class="profile-text">
                            <span class="name">Welcome, <?php echo htmlspecialchars($admin_display_name); ?></span>
                            <span class="role">CPVIA Team</span>
                        </div>
                    </div>
                </div>
            </header>

            <main class="admin-content">
