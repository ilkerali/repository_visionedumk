<?php
/**
 * Admin Users Management Page
 * 
 * Kullanıcı yönetimi sayfası - Tüm kullanıcıları listele, ekle, düzenle, sil
 * User management page - List, add, edit, delete all users
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');
requireAdmin('../dashboard.php');

$pdo = getDBConnection();
$error = '';
$success = '';

// Kullanıcı silme işlemi (Delete user)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    
    // Admin kendini silemez (Admin cannot delete themselves)
    if ($userId === $_SESSION['user_id']) {
        $error = "You cannot delete your own account!";
    } else {
        try {
            // Önce kullanıcının yayınlarını kontrol et (Check user's publications first)
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) as count FROM publications WHERE user_id = :user_id");
            $stmtCheck->execute([':user_id' => $userId]);
            $pubCount = $stmtCheck->fetch()['count'];
            
            if ($pubCount > 0) {
                // Kullanıcıyı pasif yap, silme (Deactivate user instead of deleting)
                $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $success = "User has been deactivated (user has $pubCount publications).";
            } else {
                // Yayını yoksa tamamen sil (Delete completely if no publications)
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $success = "User deleted successfully!";
            }
            
            // Log activity
            logActivity($pdo, $_SESSION['user_id'], 'user_delete', 'users', $userId);
            
        } catch (PDOException $e) {
            error_log("User delete error: " . $e->getMessage());
            $error = "Error deleting user. Please try again.";
        }
    }
}

// Kullanıcı aktif/pasif yapma (Activate/Deactivate user)
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $userId = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        
        logActivity($pdo, $_SESSION['user_id'], 'user_status_toggle', 'users', $userId);
        $success = "User status updated successfully!";
        
    } catch (PDOException $e) {
        error_log("User status toggle error: " . $e->getMessage());
        $error = "Error updating user status.";
    }
}

// Filtreleme ve arama (Filtering and search)
$searchTerm = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? cleanInput($_GET['role']) : '';
$statusFilter = isset($_GET['status']) ? cleanInput($_GET['status']) : '';

// Kullanıcıları getir (Fetch users)
try {
    $sql = "
        SELECT u.*, 
               COUNT(DISTINCT p.publication_id) as publication_count,
               MAX(p.created_at) as last_publication_date
        FROM users u
        LEFT JOIN publications p ON u.user_id = p.user_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Arama filtresi (Search filter)
    if (!empty($searchTerm)) {
        $sql .= " AND (u.full_name LIKE :search OR u.username LIKE :search OR u.email LIKE :search OR u.department LIKE :search)";
        $params[':search'] = "%$searchTerm%";
    }
    
    // Rol filtresi (Role filter)
    if (!empty($roleFilter)) {
        $sql .= " AND u.role = :role";
        $params[':role'] = $roleFilter;
    }
    
    // Durum filtresi (Status filter)
    if ($statusFilter !== '') {
        $sql .= " AND u.is_active = :status";
        $params[':status'] = ($statusFilter === '1') ? 1 : 0;
    }
    
    $sql .= " GROUP BY u.user_id ORDER BY u.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    // İstatistikler (Statistics)
    $stmtStats = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
            SUM(CASE WHEN role = 'faculty' THEN 1 ELSE 0 END) as faculty_count,
            SUM(CASE WHEN is_active = TRUE THEN 1 ELSE 0 END) as active_count
        FROM users
    ");
    $stats = $stmtStats->fetch();
    
} catch (PDOException $e) {
    error_log("Users fetch error: " . $e->getMessage());
    $users = [];
    $stats = ['total_users' => 0, 'admin_count' => 0, 'faculty_count' => 0, 'active_count' => 0];
}

// Sayfa başlığı (Page title)
$page_title = "User Management";
include 'admin_header.php';
?>

<!-- Sayfa Başlığı (Page header) -->
<div class="page-header">
    <div>
        <h1>User Management</h1>
        <p class="text-muted">Manage all system users and their permissions</p>
    </div>
    <a href="add_user.php" class="btn btn-primary">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="8.5" cy="7" r="4"></circle>
            <line x1="20" y1="8" x2="20" y2="14"></line>
            <line x1="23" y1="11" x2="17" y2="11"></line>
        </svg>
        Add New User
    </a>
</div>

<!-- Alert Mesajları (Alert messages) -->
<?php if ($error): ?>
    <div class="alert alert-error">
        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <?php echo sanitize($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
            <polyline points="22 4 12 14.01 9 11.01"></polyline>
        </svg>
        <?php echo sanitize($success); ?>
    </div>
<?php endif; ?>

<!-- İstatistik Kartları (Statistics cards) -->
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
            <h3><?php echo $stats['total_users']; ?></h3>
            <p>Total Users</p>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['faculty_count']; ?></h3>
            <p>Faculty Members</p>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M12 6v6l4 2"></path>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['admin_count']; ?></h3>
            <p>Administrators</p>
        </div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['active_count']; ?></h3>
            <p>Active Users</p>
        </div>
    </div>
</div>

<!-- Filtreleme Bölümü (Filter section) -->
<div class="filter-section">
    <form method="GET" action="users.php">
        <div class="filter-grid">
            <div class="form-group">
                <label for="search">Search</label>
                <input 
                    type="text" 
                    id="search" 
                    name="search" 
                    placeholder="Name, username, email, or department..."
                    value="<?php echo sanitize($searchTerm); ?>"
                >
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role">
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                    <option value="faculty" <?php echo $roleFilter === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">All Status</option>
                    <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div class="form-group" style="display: flex; align-items: flex-end; gap: var(--spacing-sm);">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                    Filter
                </button>
                <a href="users.php" class="btn btn-secondary">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                    </svg>
                    Clear
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Kullanıcılar Tablosu (Users table) -->
<div class="card">
    <div class="card-header">
        <h2>All Users (<?php echo count($users); ?> results)</h2>
    </div>
    <div class="card-body">
        <?php if (!empty($users)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Publications</th>
                            <th>Last Activity</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <strong><?php echo sanitize($user['full_name']); ?></strong>
                                    <small style="color: var(--gray-600);"><?php echo sanitize($user['email']); ?></small>
                                </div>
                            </td>
                            <td>
                                <code style="background: var(--gray-100); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm);">
                                    <?php echo sanitize($user['username']); ?>
                                </code>
                            </td>
                            <td><?php echo sanitize($user['department']); ?></td>
                            <td>
                                <?php if ($user['role'] === 'admin'): ?>
                                    <span class="badge-admin">Administrator</span>
                                <?php else: ?>
                                    <span style="display: inline-block; padding: 0.25rem 0.75rem; background: rgba(37, 99, 235, 0.1); color: var(--primary-color); border-radius: var(--radius-md); font-size: 0.8125rem; font-weight: 500;">
                                        Faculty
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: var(--primary-color);"><?php echo $user['publication_count']; ?></strong>
                                <?php if ($user['last_publication_date']): ?>
                                    <br>
                                    <small style="color: var(--gray-600);">
                                        Last: <?php echo date('M j, Y', strtotime($user['last_publication_date'])); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['last_login']): ?>
                                    <?php echo date('M j, Y H:i', strtotime($user['last_login'])); ?>
                                <?php else: ?>
                                    <span style="color: var(--gray-400);">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($user['is_active']): ?>
                                    <span class="status-badge status-active">Active</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_user.php?id=<?php echo $user['user_id']; ?>" 
                                       class="btn-icon" 
                                       title="View Details">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </a>
                                    
                                    <a href="edit_user.php?id=<?php echo $user['user_id']; ?>" 
                                       class="btn-icon" 
                                       title="Edit User">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </a>
                                    
                                    <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                        <a href="users.php?action=toggle_status&id=<?php echo $user['user_id']; ?>" 
                                           class="btn-icon" 
                                           title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                           onclick="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?');">
                                            <?php if ($user['is_active']): ?>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--warning-color);">
                                                    <circle cx="12" cy="12" r="10"></circle>
                                                    <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                                </svg>
                                            <?php else: ?>
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="color: var(--success-color);">
                                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                </svg>
                                            <?php endif; ?>
                                        </a>
                                        
                                        <a href="users.php?action=delete&id=<?php echo $user['user_id']; ?>" 
                                           class="btn-icon" 
                                           title="Delete User"
                                           onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');"
                                           style="color: var(--error-color);">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <span class="btn-icon" style="opacity: 0.3; cursor: not-allowed;" title="Cannot modify own account">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                            </svg>
                                        </span>
                                    <?php endif; ?>
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
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <h3>No Users Found</h3>
                <p>No users match your search criteria. Try adjusting your filters.</p>
                <a href="users.php" class="btn btn-secondary">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
