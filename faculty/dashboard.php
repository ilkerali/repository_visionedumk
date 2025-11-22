<?php
/**
 * Faculty Dashboard - Main Page
 * 
 * This page displays statistics and quick actions for faculty members.
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();

// Login check
requireLogin('../login.php');

// Verify faculty role
if ($_SESSION['role'] !== 'faculty') {
    header("Location: ../dashboard.php");
    exit();
}

// Get database connection
$pdo = getDBConnection();

// Get user ID
$user_id = $_SESSION['user_id'];

// Get statistics
try {
    // Total publications count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM publications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_publications = $stmt->fetch()['total'];
    
    // Publications by year
    $stmt = $pdo->prepare("
        SELECT publication_year, COUNT(*) as count 
        FROM publications 
        WHERE user_id = ? 
        GROUP BY publication_year 
        ORDER BY publication_year DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $publications_by_year = $stmt->fetchAll();
    
    // Recent publications
    $stmt = $pdo->prepare("
        SELECT p.*, pt.type_name_en 
        FROM publications p
        LEFT JOIN publication_types pt ON p.type_id = pt.type_id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_publications = $stmt->fetchAll();
    
    // Publications by type
    $stmt = $pdo->prepare("
        SELECT pt.type_name_en, COUNT(*) as count
        FROM publications p
        LEFT JOIN publication_types pt ON p.type_id = pt.type_id
        WHERE p.user_id = ?
        GROUP BY pt.type_id, pt.type_name_en
        ORDER BY count DESC
    ");
    $stmt->execute([$user_id]);
    $publications_by_type = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error = "Error loading statistics: " . $e->getMessage();
}

// Set page title for header
$page_title = "Dashboard";

// Include header
include 'faculty_header.php';
?>

<style>
/* Dashboard Icon Size Protection */
.stat-icon svg,
.stat-card svg {
    width: 28px !important;
    height: 28px !important;
    max-width: 28px !important;
    max-height: 28px !important;
}

.action-card svg {
    width: 40px !important;
    height: 40px !important;
    max-width: 40px !important;
    max-height: 40px !important;
}

.stat-icon {
    width: 50px !important;
    height: 50px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
}

.empty-state svg {
    width: 64px !important;
    height: 64px !important;
}
</style>

<!-- Welcome Section -->
<div class="welcome-section">
    <h2>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
    <p class="welcome-text">Manage your academic publications and track your research output.</p>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #3498db;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
            </svg>
        </div>
        <div class="stat-content">
            <h3>Total Publications</h3>
            <p class="stat-number"><?php echo number_format($total_publications); ?></p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #2ecc71;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </div>
        <div class="stat-content">
            <h3>This Year</h3>
            <p class="stat-number">
                <?php 
                $current_year = date('Y');
                $this_year_count = 0;
                foreach ($publications_by_year as $year_data) {
                    if ($year_data['publication_year'] == $current_year) {
                        $this_year_count = $year_data['count'];
                        break;
                    }
                }
                echo number_format($this_year_count);
                ?>
            </p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #e74c3c;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
        </div>
        <div class="stat-content">
            <h3>Publication Types</h3>
            <p class="stat-number"><?php echo count($publications_by_type); ?></p>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon" style="background: #f39c12;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
            </svg>
        </div>
        <div class="stat-content">
            <h3>Avg. per Year</h3>
            <p class="stat-number">
                <?php 
                if (count($publications_by_year) > 0) {
                    $total = array_sum(array_column($publications_by_year, 'count'));
                    $avg = $total / count($publications_by_year);
                    echo number_format($avg, 1);
                } else {
                    echo "0";
                }
                ?>
            </p>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="dashboard-section">
    <h3 class="section-title">Quick Actions</h3>
    <div class="quick-actions">
        <a href="add_publication.php" class="action-card action-primary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="16"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
            <span>Add New Publication</span>
        </a>
        
        <a href="my_publications.php" class="action-card action-secondary">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            <span>View All Publications</span>
        </a>
        
        <a href="profile.php" class="action-card action-info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            <span>Edit Profile</span>
        </a>
    </div>
</div>

<!-- Two Column Layout -->
<div class="dashboard-grid">
    <!-- Recent Publications -->
    <div class="dashboard-section">
        <h3 class="section-title">Recent Publications</h3>
        <?php if (count($recent_publications) > 0): ?>
            <div class="publications-list">
                <?php foreach ($recent_publications as $pub): ?>
                    <div class="publication-item">
                        <div class="publication-type-badge">
                            <?php echo htmlspecialchars($pub['type_name_en'] ?? 'Unknown'); ?>
                        </div>
                        <h4 class="publication-title">
                            <a href="view_publication.php?id=<?php echo $pub['publication_id']; ?>">
                                <?php echo htmlspecialchars($pub['title']); ?>
                            </a>
                        </h4>
                        <p class="publication-meta">
                            <?php echo htmlspecialchars($pub['authors']); ?> 
                            (<?php echo $pub['publication_year']; ?>)
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
            <a href="my_publications.php" class="btn btn-secondary btn-sm">View All Publications →</a>
        <?php else: ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                <p>No publications yet</p>
                <a href="add_publication.php" class="btn btn-primary btn-sm">Add Your First Publication</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Publications by Type -->
    <div class="dashboard-section">
        <h3 class="section-title">Publications by Type</h3>
        <?php if (count($publications_by_type) > 0): ?>
            <div class="type-chart">
                <?php 
                $max_count = max(array_column($publications_by_type, 'count'));
                foreach ($publications_by_type as $type): 
                    $percentage = ($max_count > 0) ? ($type['count'] / $max_count) * 100 : 0;
                ?>
                    <div class="type-bar">
                        <div class="type-label">
                            <span><?php echo htmlspecialchars($type['type_name_en']); ?></span>
                            <span class="type-count"><?php echo $type['count']; ?></span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
                <p>No data available</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Publications by Year -->
<?php if (count($publications_by_year) > 0): ?>
<div class="dashboard-section">
    <h3 class="section-title">Publications by Year</h3>
    <div class="year-chart">
        <?php 
        $max_year_count = max(array_column($publications_by_year, 'count'));
        foreach ($publications_by_year as $year_data): 
            $percentage = ($max_year_count > 0) ? ($year_data['count'] / $max_year_count) * 100 : 0;
        ?>
            <div class="year-bar">
                <div class="year-label">
                    <span class="year"><?php echo $year_data['publication_year']; ?></span>
                    <span class="year-count"><?php echo $year_data['count']; ?> publications</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
// Include footer
include 'faculty_footer.php';
?>
