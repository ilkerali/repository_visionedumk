<?php
/**
 * Admin Dashboard
 * 
 * Sistem yöneticilerinin tüm yayınları ve kullanıcıları yönetebileceği panel
 * System administrators can manage all publications and users
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');
requireAdmin('../dashboard.php');

$pdo = getDBConnection();

// Sistem geneli istatistikler (System-wide statistics)
try {
    // Toplam kullanıcı sayısı (Total user count)
    $stmtUsers = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = TRUE");
    $totalUsers = $stmtUsers->fetch()['total'];
    
    // Toplam yayın sayısı (Total publication count)
    $stmtPublications = $pdo->query("SELECT COUNT(*) as total FROM publications");
    $totalPublications = $stmtPublications->fetch()['total'];
    
    // Bu ayki yeni yayınlar (This month's new publications)
    $stmtThisMonth = $pdo->query("
        SELECT COUNT(*) as total 
        FROM publications 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $thisMonthPublications = $stmtThisMonth->fetch()['total'];
    
    // Yayın türlerine göre dağılım (Distribution by publication type)
    $stmtByType = $pdo->query("
        SELECT pt.type_name_en, pt.type_code, COUNT(*) as count
        FROM publications p
        JOIN publication_types pt ON p.type_id = pt.type_id
        GROUP BY pt.type_id, pt.type_name_en, pt.type_code
        ORDER BY count DESC
    ");
    $publicationsByType = $stmtByType->fetchAll();
    
    // En aktif kullanıcılar (Top 5) (Most active users)
    $stmtTopUsers = $pdo->query("
        SELECT u.full_name, u.department, COUNT(p.publication_id) as pub_count
        FROM users u
        LEFT JOIN publications p ON u.user_id = p.user_id
        WHERE u.role = 'faculty'
        GROUP BY u.user_id, u.full_name, u.department
        ORDER BY pub_count DESC
        LIMIT 5
    ");
    $topUsers = $stmtTopUsers->fetchAll();
    
    // Son eklenen yayınlar (tüm kullanıcılar) (Recently added publications - all users)
    $stmtRecent = $pdo->query("
        SELECT p.*, pt.type_name_en, u.full_name as author_name
        FROM publications p
        JOIN publication_types pt ON p.type_id = pt.type_id
        JOIN users u ON p.user_id = u.user_id
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $recentPublications = $stmtRecent->fetchAll();
    
    // Yıllık yayın trendi (son 5 yıl) (Annual publication trend - last 5 years)
    $stmtYearTrend = $pdo->query("
        SELECT publication_year, COUNT(*) as count
        FROM publications
        WHERE publication_year >= YEAR(CURDATE()) - 5
        GROUP BY publication_year
        ORDER BY publication_year ASC
    ");
    $yearTrend = $stmtYearTrend->fetchAll();
    
} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $totalUsers = $totalPublications = $thisMonthPublications = 0;
    $publicationsByType = $topUsers = $recentPublications = $yearTrend = [];
}

// Sayfa başlığı (Page title)
$page_title = "Dashboard";
include 'admin_header.php';
?>

<!-- Hoş Geldin Mesajı (Welcome message) -->
<div class="welcome-section">
    <h1>Welcome, <?php echo sanitize(explode(' ', $_SESSION['full_name'])[0]); ?>!</h1>
    <p class="text-muted">Welcome to the system management panel. All statistics and management tools are here.</p>
</div>

<!-- Ana İstatistik Kartları (Main statistics cards) -->
<div class="stats-grid stats-grid-4">
    <div class="stat-card stat-primary">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $totalUsers; ?></h3>
            <p>Total Users</p>
        </div>
        <a href="users.php" class="stat-link">View Details →</a>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $totalPublications; ?></h3>
            <p>Total Publications</p>
        </div>
        <a href="publications.php" class="stat-link">View Details →</a>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="12 6 12 12 16 14"></polyline>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $thisMonthPublications; ?></h3>
            <p>New This Month</p>
        </div>
        <a href="publications.php?month=current" class="stat-link">View Details →</a>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <line x1="18" y1="20" x2="18" y2="10"></line>
                <line x1="12" y1="20" x2="12" y2="4"></line>
                <line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo count($publicationsByType); ?></h3>
            <p>Publication Types</p>
        </div>
        <a href="publication_types.php" class="stat-link">View Details →</a>
    </div>
</div>

<!-- İki Kolonlu Layout (Two column layout) -->
<div class="dashboard-row">
    <!-- Sol Kolon: Yayın Türleri Dağılımı (Left column: Publication type distribution) -->
    <div class="dashboard-col-6">
        <div class="card">
            <div class="card-header">
                <h2>Publication Type Distribution</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($publicationsByType)): ?>
                    <div class="type-distribution">
                        <?php 
                        $totalCount = array_sum(array_column($publicationsByType, 'count'));
                        foreach ($publicationsByType as $type): 
                            $percentage = $totalCount > 0 ? ($type['count'] / $totalCount) * 100 : 0;
                        ?>
                        <div class="distribution-item">
                            <div class="distribution-label">
                                <span class="type-badge badge-<?php echo strtolower($type['type_code']); ?>">
                                    <?php echo sanitize($type['type_name_en']); ?>
                                </span>
                                <span class="type-count"><?php echo $type['count']; ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                            <span class="type-percentage"><?php echo number_format($percentage, 1); ?>%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="text-align: center;">No publications yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sağ Kolon: En Aktif Kullanıcılar (Right column: Most active users) -->
    <div class="dashboard-col-6">
        <div class="card">
            <div class="card-header">
                <h2>Most Active Users</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($topUsers)): ?>
                    <div class="top-users-list">
                        <?php foreach ($topUsers as $index => $user): ?>
                        <div class="top-user-item">
                            <div class="user-rank rank-<?php echo $index + 1; ?>">
                                <?php echo $index + 1; ?>
                            </div>
                            <div class="user-details">
                                <h4><?php echo sanitize($user['full_name']); ?></h4>
                                <p class="text-muted"><?php echo sanitize($user['department']); ?></p>
                            </div>
                            <div class="user-stat">
                                <span class="stat-number"><?php echo $user['pub_count']; ?></span>
                                <span class="stat-label">publications</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="text-align: center;">No users yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Yıllık Trend Grafiği (Annual trend chart) -->
<?php if (!empty($yearTrend)): ?>
<div class="card">
    <div class="card-header">
        <h2>Annual Publication Trend</h2>
    </div>
    <div class="card-body">
        <div class="chart-container">
            <?php 
            $maxCount = max(array_column($yearTrend, 'count'));
            ?>
            <div class="bar-chart">
                <?php foreach ($yearTrend as $year): 
                    $height = $maxCount > 0 ? ($year['count'] / $maxCount) * 100 : 0;
                ?>
                <div class="bar-item">
                    <div class="bar-value"><?php echo $year['count']; ?></div>
                    <div class="bar" style="height: <?php echo $height; ?>%">
                        <div class="bar-fill"></div>
                    </div>
                    <div class="bar-label"><?php echo $year['publication_year']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Son Eklenen Yayınlar (Recently added publications) -->
<div class="card">
    <div class="card-header">
        <h2>Recently Added Publications</h2>
        <a href="publications.php" class="btn btn-sm btn-secondary">View All</a>
    </div>
    <div class="card-body">
        <?php if (!empty($recentPublications)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Year</th>
                            <th>Added Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPublications as $pub): ?>
                        <tr>
                            <td>
                                <span class="type-badge-sm badge-<?php echo strtolower($pub['type_name_en']); ?>">
                                    <?php echo sanitize($pub['type_name_en']); ?>
                                </span>
                            </td>
                            <td class="title-cell">
                                <strong><?php echo sanitize(substr($pub['title'], 0, 60)); ?><?php echo strlen($pub['title']) > 60 ? '...' : ''; ?></strong>
                            </td>
                            <td><?php echo sanitize($pub['author_name']); ?></td>
                            <td><?php echo $pub['publication_year']; ?></td>
                            <td><?php echo formatDate($pub['created_at']); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_publication.php?id=<?php echo $pub['publication_id']; ?>" 
                                       class="btn-icon" title="View">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </a>
                                    <a href="edit_publication.php?id=<?php echo $pub['publication_id']; ?>" 
                                       class="btn-icon" title="Edit">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <h3>No Publications Yet</h3>
                <p>There are no publications in the system yet. Faculty members can start adding publications.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
