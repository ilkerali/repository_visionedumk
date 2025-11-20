<?php
/**
 * View User Details Page
 * 
 * Kullanıcı detay görüntüleme sayfası
 * Detailed view of user profile and statistics
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');
requireAdmin('../dashboard.php');

$pdo = getDBConnection();
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Kullanıcı bilgilerini getir (Fetch user data)
try {
    $stmt = $pdo->prepare("
        SELECT user_id, username, full_name, email, department, role,
               phone, office_location, bio, is_active, 
               created_at, last_login, profile_image
        FROM users 
        WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: users.php?error=user_not_found");
        exit();
    }
    
    // Kullanıcının yayın istatistiklerini getir (Fetch user's publication statistics)
    $stmtStats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_publications,
            MIN(publication_year) as first_publication_year,
            MAX(publication_year) as last_publication_year,
            MAX(created_at) as last_added
        FROM publications 
        WHERE user_id = :user_id
    ");
    $stmtStats->execute([':user_id' => $userId]);
    $stats = $stmtStats->fetch();
    
    // Yayın türlerine göre dağılım (Distribution by publication type)
    $stmtByType = $pdo->prepare("
        SELECT pt.type_name_en, pt.type_code, COUNT(*) as count
        FROM publications p
        JOIN publication_types pt ON p.type_id = pt.type_id
        WHERE p.user_id = :user_id
        GROUP BY pt.type_id, pt.type_name_en, pt.type_code
        ORDER BY count DESC
    ");
    $stmtByType->execute([':user_id' => $userId]);
    $publicationsByType = $stmtByType->fetchAll();
    
    // Son yayınlar (Recent publications)
    $stmtRecent = $pdo->prepare("
        SELECT p.*, pt.type_name_en
        FROM publications p
        JOIN publication_types pt ON p.type_id = pt.type_id
        WHERE p.user_id = :user_id
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmtRecent->execute([':user_id' => $userId]);
    $recentPublications = $stmtRecent->fetchAll();
    
    // Aktivite geçmişi (Activity history)
    $stmtActivity = $pdo->prepare("
        SELECT action, table_name, record_id, created_at, ip_address
        FROM activity_log
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmtActivity->execute([':user_id' => $userId]);
    $activities = $stmtActivity->fetchAll();
    
} catch (PDOException $e) {
    error_log("User view error: " . $e->getMessage());
    header("Location: users.php?error=database_error");
    exit();
}

// Sayfa başlığı (Page title)
$page_title = "View User - " . $user['full_name'];
include 'admin_header.php';
?>

<!-- Sayfa Başlığı (Page header) -->
<div class="page-header">
    <div>
        <h1><?php echo sanitize($user['full_name']); ?></h1>
        <p class="text-muted">
            <?php echo sanitize($user['department']); ?> • 
            <?php echo $user['role'] === 'admin' ? 'Administrator' : 'Faculty Member'; ?>
        </p>
    </div>
    <div style="display: flex; gap: var(--spacing-sm);">
        <a href="edit_user.php?id=<?php echo $userId; ?>" class="btn btn-primary">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
            </svg>
            Edit User
        </a>
        <a href="users.php" class="btn btn-secondary">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Back to Users
        </a>
    </div>
</div>

<!-- Kullanıcı Detay İçeriği (User detail content) -->
<div class="dashboard-row">
    <!-- Sol Kolon: Kullanıcı Bilgileri (Left column: User information) -->
    <div class="dashboard-col-6">
        <!-- Temel Bilgiler (Basic Information) -->
        <div class="card">
            <div class="card-header">
                <h2>User Information</h2>
                <?php if ($user['is_active']): ?>
                    <span class="status-badge status-active">Active</span>
                <?php else: ?>
                    <span class="status-badge status-inactive">Inactive</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: var(--spacing-lg);">
                    <div>
                        <label style="color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: var(--spacing-xs);">
                            Username
                        </label>
                        <code style="background: var(--gray-100); padding: 0.375rem 0.75rem; border-radius: var(--radius-md); font-size: 0.9375rem;">
                            <?php echo sanitize($user['username']); ?>
                        </code>
                    </div>

                    <div>
                        <label style="color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: var(--spacing-xs);">
                            Email Address
                        </label>
                        <a href="mailto:<?php echo sanitize($user['email']); ?>" style="font-size: 0.9375rem;">
                            <?php echo sanitize($user['email']); ?>
                        </a>
                    </div>

                    <div>
                        <label style="color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: var(--spacing-xs);">
                            Department
                        </label>
                        <strong style="font-size: 0.9375rem;"><?php echo sanitize($user['department']); ?></strong>
                    </div>

                    <div>
                        <label style="color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: var(--spacing-xs);">
                            Role
                        </label>
                        <?php if ($user['role'] === 'admin'): ?>
                            <span class="badge-admin">Administrator</span>
                        <?php else: ?>
                            <span style="display: inline-block; padding: 0.375rem 0.75rem; background: rgba(37, 99, 235, 0.1); color: var(--primary-color); border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 500;">
                                Faculty Member
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($user['phone']): ?>
                    <div>
                        <label style="color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: var(--spacing-xs);">
                            Phone Number
                        </label>
                        <a href="tel:<?php echo sanitize($user['phone']); ?>" style="font-size: 0.9375rem;">
                            <?php echo sanitize($user['phone']); ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <?php if ($user['office_location']): ?>
                    <div>
                        <label style="color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: var(--spacing-xs);">
                            Office Location
                        </label>
                        <span style="font-size: 0.9375rem;"><?php echo sanitize($user['office_location']); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($user['bio']): ?>
                    <div>
                        <label style="color: var(--gray-600); font-size: 0.875rem; display: block; margin-bottom: var(--spacing-xs);">
                            Biography
                        </label>
                        <p style="font-size: 0.9375rem; line-height: 1.6; color: var(--gray-700);">
                            <?php echo nl2br(sanitize($user['bio'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Hesap İstatistikleri (Account Statistics) -->
        <div class="card">
            <div class="card-header">
                <h2>Account Statistics</h2>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: var(--spacing-md);">
                    <div style="display: flex; justify-content: space-between; padding: var(--spacing-sm) 0; border-bottom: 1px solid var(--gray-200);">
                        <span style="color: var(--gray-600);">Member Since</span>
                        <strong><?php echo date('F j, Y', strtotime($user['created_at'])); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: var(--spacing-sm) 0; border-bottom: 1px solid var(--gray-200);">
                        <span style="color: var(--gray-600);">Last Login</span>
                        <strong>
                            <?php echo $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                        </strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: var(--spacing-sm) 0; border-bottom: 1px solid var(--gray-200);">
                        <span style="color: var(--gray-600);">Total Publications</span>
                        <strong style="color: var(--primary-color); font-size: 1.125rem;">
                            <?php echo $stats['total_publications']; ?>
                        </strong>
                    </div>
                    <?php if ($stats['first_publication_year']): ?>
                    <div style="display: flex; justify-content: space-between; padding: var(--spacing-sm) 0; border-bottom: 1px solid var(--gray-200);">
                        <span style="color: var(--gray-600);">Publication Years</span>
                        <strong>
                            <?php echo $stats['first_publication_year']; ?> - <?php echo $stats['last_publication_year']; ?>
                        </strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: var(--spacing-sm) 0;">
                        <span style="color: var(--gray-600);">Last Publication Added</span>
                        <strong><?php echo date('M j, Y', strtotime($stats['last_added'])); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sağ Kolon: Yayınlar ve Aktiviteler (Right column: Publications and activities) -->
    <div class="dashboard-col-6">
        <!-- Yayın Türleri Dağılımı (Publication type distribution) -->
        <?php if (!empty($publicationsByType)): ?>
        <div class="card">
            <div class="card-header">
                <h2>Publications by Type</h2>
            </div>
            <div class="card-body">
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
            </div>
        </div>
        <?php endif; ?>

        <!-- Son Yayınlar (Recent publications) -->
        <?php if (!empty($recentPublications)): ?>
        <div class="card">
            <div class="card-header">
                <h2>Recent Publications</h2>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: var(--spacing-md);">
                    <?php foreach ($recentPublications as $pub): ?>
                    <div style="padding: var(--spacing-md); background: var(--gray-50); border-radius: var(--radius-md); border-left: 3px solid var(--primary-color);">
                        <div style="display: flex; align-items: start; justify-content: space-between; gap: var(--spacing-sm); margin-bottom: var(--spacing-xs);">
                            <span class="type-badge-sm badge-<?php echo strtolower($pub['type_name_en']); ?>">
                                <?php echo sanitize($pub['type_name_en']); ?>
                            </span>
                            <span style="color: var(--gray-600); font-size: 0.8125rem;">
                                <?php echo $pub['publication_year']; ?>
                            </span>
                        </div>
                        <h4 style="font-size: 0.9375rem; margin-bottom: var(--spacing-xs); color: var(--gray-900);">
                            <?php echo sanitize($pub['title']); ?>
                        </h4>
                        <div style="display: flex; gap: var(--spacing-sm); margin-top: var(--spacing-sm);">
                            <a href="../faculty/view_publication.php?id=<?php echo $pub['publication_id']; ?>" 
                               class="btn-icon btn-sm" 
                               title="View">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Son Aktiviteler (Recent activities) -->
        <?php if (!empty($activities)): ?>
        <div class="card">
            <div class="card-header">
                <h2>Recent Activity</h2>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                    <?php foreach (array_slice($activities, 0, 10) as $activity): ?>
                    <div style="display: flex; align-items: center; gap: var(--spacing-sm); padding: var(--spacing-sm); border-bottom: 1px solid var(--gray-200);">
                        <svg style="width: 16px; height: 16px; color: var(--primary-color); flex-shrink: 0;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                        <div style="flex: 1; min-width: 0;">
                            <span style="font-size: 0.875rem; color: var(--gray-700);">
                                <?php 
                                $actionText = str_replace('_', ' ', $activity['action']);
                                echo ucwords(sanitize($actionText)); 
                                ?>
                            </span>
                            <br>
                            <small style="color: var(--gray-600); font-size: 0.75rem;">
                                <?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
