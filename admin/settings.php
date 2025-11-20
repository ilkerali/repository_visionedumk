<?php
/**
 * Admin Settings Page
 * 
 * Sistem ayarları ve yapılandırma sayfası
 * System settings and configuration page
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

// Sistem ayarlarını getir veya varsayılanları kullan (Fetch system settings or use defaults)
try {
    $settings = [];
    $stmt = $pdo->query("
        SELECT setting_key, setting_value 
        FROM system_settings
    ");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Tablo yoksa varsayılan değerleri kullan (Use defaults if table doesn't exist)
    $settings = [];
}

// Varsayılan ayarlar (Default settings)
$defaultSettings = [
    'site_name' => 'International Vision University',
    'site_description' => 'Academic Publication Repository System',
    'admin_email' => 'admin@uvt.edu.mk',
    'items_per_page' => '20',
    'allow_registration' => '0',
    'require_email_verification' => '1',
    'maintenance_mode' => '0',
    'date_format' => 'Y-m-d',
    'timezone' => 'Europe/Istanbul',
    'session_timeout' => '7200',
    'password_min_length' => '6',
    'max_login_attempts' => '5',
    'login_lockout_time' => '900',
];

// Mevcut ayarları varsayılanlarla birleştir (Merge current with defaults)
$settings = array_merge($defaultSettings, $settings);

// Ayar güncelleme (Update settings)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_general') {
        $siteName = cleanInput($_POST['site_name'] ?? '');
        $siteDescription = cleanInput($_POST['site_description'] ?? '');
        $adminEmail = cleanInput($_POST['admin_email'] ?? '');
        $itemsPerPage = (int)($_POST['items_per_page'] ?? 20);
        
        if (empty($siteName) || empty($adminEmail)) {
            $error = "Site name and admin email are required!";
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format!";
        } else {
            try {
                $settingsToUpdate = [
                    'site_name' => $siteName,
                    'site_description' => $siteDescription,
                    'admin_email' => $adminEmail,
                    'items_per_page' => $itemsPerPage
                ];
                
                foreach ($settingsToUpdate as $key => $value) {
                    $stmt = $pdo->prepare("
                        INSERT INTO system_settings (setting_key, setting_value, updated_at)
                        VALUES (:key, :value, NOW())
                        ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = NOW()
                    ");
                    $stmt->execute([':key' => $key, ':value' => $value]);
                    $settings[$key] = $value;
                }
                
                logActivity($pdo, $_SESSION['user_id'], 'settings_update', 'system_settings', 0);
                $success = "General settings updated successfully!";
                
            } catch (PDOException $e) {
                error_log("Settings update error: " . $e->getMessage());
                $error = "Error updating settings. Please try again.";
            }
        }
    }
    
    if ($_POST['action'] === 'update_security') {
        $sessionTimeout = (int)($_POST['session_timeout'] ?? 7200);
        $passwordMinLength = (int)($_POST['password_min_length'] ?? 6);
        $maxLoginAttempts = (int)($_POST['max_login_attempts'] ?? 5);
        $loginLockoutTime = (int)($_POST['login_lockout_time'] ?? 900);
        
        try {
            $settingsToUpdate = [
                'session_timeout' => $sessionTimeout,
                'password_min_length' => $passwordMinLength,
                'max_login_attempts' => $maxLoginAttempts,
                'login_lockout_time' => $loginLockoutTime
            ];
            
            foreach ($settingsToUpdate as $key => $value) {
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, updated_at)
                    VALUES (:key, :value, NOW())
                    ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = NOW()
                ");
                $stmt->execute([':key' => $key, ':value' => $value]);
                $settings[$key] = $value;
            }
            
            logActivity($pdo, $_SESSION['user_id'], 'security_settings_update', 'system_settings', 0);
            $success = "Security settings updated successfully!";
            
        } catch (PDOException $e) {
            error_log("Security settings update error: " . $e->getMessage());
            $error = "Error updating security settings.";
        }
    }
    
    if ($_POST['action'] === 'update_system') {
        $maintenanceMode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $allowRegistration = isset($_POST['allow_registration']) ? 1 : 0;
        $requireEmailVerification = isset($_POST['require_email_verification']) ? 1 : 0;
        
        try {
            $settingsToUpdate = [
                'maintenance_mode' => $maintenanceMode,
                'allow_registration' => $allowRegistration,
                'require_email_verification' => $requireEmailVerification
            ];
            
            foreach ($settingsToUpdate as $key => $value) {
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, updated_at)
                    VALUES (:key, :value, NOW())
                    ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = NOW()
                ");
                $stmt->execute([':key' => $key, ':value' => $value]);
                $settings[$key] = $value;
            }
            
            logActivity($pdo, $_SESSION['user_id'], 'system_settings_update', 'system_settings', 0);
            $success = "System settings updated successfully!";
            
        } catch (PDOException $e) {
            error_log("System settings update error: " . $e->getMessage());
            $error = "Error updating system settings.";
        }
    }
}

// Sistem bilgileri (System information)
try {
    $stmtStats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM publications) as total_publications,
            (SELECT COUNT(*) FROM publication_types) as total_types
    ");
    $systemStats = $stmtStats->fetch();
    
    // Veritabanı boyutu (Database size)
    $stmtDbSize = $pdo->query("
        SELECT 
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.TABLES
        WHERE table_schema = '" . DB_NAME . "'
    ");
    $dbSize = $stmtDbSize->fetch();
    
} catch (PDOException $e) {
    $systemStats = ['total_users' => 0, 'total_publications' => 0, 'total_types' => 0];
    $dbSize = ['size_mb' => 0];
}

// Sayfa başlığı (Page title)
$page_title = "System Settings";
include 'admin_header.php';
?>

<!-- Sayfa Başlığı (Page header) -->
<div class="page-header">
    <div>
        <h1>System Settings</h1>
        <p class="text-muted">Configure system preferences and options</p>
    </div>
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

<!-- Sistem Bilgileri (System Information) -->
<div class="card" style="margin-bottom: var(--spacing-xl);">
    <div class="card-header">
        <h2>System Information</h2>
    </div>
    <div class="card-body">
        <div class="stats-grid stats-grid-4">
            <div style="text-align: center; padding: var(--spacing-lg);">
                <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color); margin-bottom: var(--spacing-xs);">
                    <?php echo $systemStats['total_users']; ?>
                </div>
                <div style="color: var(--gray-600); font-size: 0.875rem;">Total Users</div>
            </div>
            <div style="text-align: center; padding: var(--spacing-lg);">
                <div style="font-size: 2rem; font-weight: 700; color: var(--success-color); margin-bottom: var(--spacing-xs);">
                    <?php echo $systemStats['total_publications']; ?>
                </div>
                <div style="color: var(--gray-600); font-size: 0.875rem;">Total Publications</div>
            </div>
            <div style="text-align: center; padding: var(--spacing-lg);">
                <div style="font-size: 2rem; font-weight: 700; color: var(--info-color); margin-bottom: var(--spacing-xs);">
                    <?php echo $systemStats['total_types']; ?>
                </div>
                <div style="color: var(--gray-600); font-size: 0.875rem;">Publication Types</div>
            </div>
            <div style="text-align: center; padding: var(--spacing-lg);">
                <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color); margin-bottom: var(--spacing-xs);">
                    <?php echo $dbSize['size_mb'] ?? '0'; ?> MB
                </div>
                <div style="color: var(--gray-600); font-size: 0.875rem;">Database Size</div>
            </div>
        </div>
        
        <div style="margin-top: var(--spacing-lg); padding-top: var(--spacing-lg); border-top: 1px solid var(--gray-200);">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--spacing-md);">
                <div>
                    <strong style="color: var(--gray-700);">PHP Version:</strong>
                    <span style="color: var(--gray-600);"> <?php echo phpversion(); ?></span>
                </div>
                <div>
                    <strong style="color: var(--gray-700);">Server Software:</strong>
                    <span style="color: var(--gray-600);"> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></span>
                </div>
                <div>
                    <strong style="color: var(--gray-700);">Database:</strong>
                    <span style="color: var(--gray-600);"> MySQL <?php echo $pdo->query('SELECT VERSION()')->fetchColumn(); ?></span>
                </div>
                <div>
                    <strong style="color: var(--gray-700);">Max Upload Size:</strong>
                    <span style="color: var(--gray-600);"> <?php echo ini_get('upload_max_filesize'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ayar Formları (Settings Forms) -->
<div class="dashboard-row">
    <!-- Genel Ayarlar (General Settings) -->
    <div class="dashboard-col-6">
        <form method="POST" action="settings.php" class="form-section">
            <input type="hidden" name="action" value="update_general">
            
            <h2 class="form-section-title">
                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M12 1v6m0 6v6m9-9h-6m-6 0H3"></path>
                </svg>
                General Settings
            </h2>

            <div class="form-group">
                <label for="site_name">
                    Site Name <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="site_name" 
                    name="site_name" 
                    value="<?php echo sanitize($settings['site_name']); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="site_description">Site Description</label>
                <textarea 
                    id="site_description" 
                    name="site_description" 
                    rows="3"
                ><?php echo sanitize($settings['site_description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="admin_email">
                    Admin Email <span class="required">*</span>
                </label>
                <input 
                    type="email" 
                    id="admin_email" 
                    name="admin_email" 
                    value="<?php echo sanitize($settings['admin_email']); ?>"
                    required
                >
                <small class="form-help">System notifications will be sent to this email</small>
            </div>

            <div class="form-group">
                <label for="items_per_page">Items Per Page</label>
                <input 
                    type="number" 
                    id="items_per_page" 
                    name="items_per_page" 
                    value="<?php echo sanitize($settings['items_per_page']); ?>"
                    min="10"
                    max="100"
                >
                <small class="form-help">Number of items to display per page in lists</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Save General Settings
                </button>
            </div>
        </form>

        <!-- Güvenlik Ayarları (Security Settings) -->
        <form method="POST" action="settings.php" class="form-section">
            <input type="hidden" name="action" value="update_security">
            
            <h2 class="form-section-title">
                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                </svg>
                Security Settings
            </h2>

            <div class="form-group">
                <label for="session_timeout">Session Timeout (seconds)</label>
                <input 
                    type="number" 
                    id="session_timeout" 
                    name="session_timeout" 
                    value="<?php echo sanitize($settings['session_timeout']); ?>"
                    min="300"
                    max="86400"
                >
                <small class="form-help">Default: 7200 (2 hours)</small>
            </div>

            <div class="form-group">
                <label for="password_min_length">Minimum Password Length</label>
                <input 
                    type="number" 
                    id="password_min_length" 
                    name="password_min_length" 
                    value="<?php echo sanitize($settings['password_min_length']); ?>"
                    min="4"
                    max="32"
                >
            </div>

            <div class="form-group">
                <label for="max_login_attempts">Max Login Attempts</label>
                <input 
                    type="number" 
                    id="max_login_attempts" 
                    name="max_login_attempts" 
                    value="<?php echo sanitize($settings['max_login_attempts']); ?>"
                    min="3"
                    max="10"
                >
                <small class="form-help">Number of failed login attempts before lockout</small>
            </div>

            <div class="form-group">
                <label for="login_lockout_time">Login Lockout Time (seconds)</label>
                <input 
                    type="number" 
                    id="login_lockout_time" 
                    name="login_lockout_time" 
                    value="<?php echo sanitize($settings['login_lockout_time']); ?>"
                    min="60"
                    max="3600"
                >
                <small class="form-help">Default: 900 (15 minutes)</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Save Security Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Sağ Kolon (Right Column) -->
    <div class="dashboard-col-6">
        <!-- Sistem Ayarları (System Settings) -->
        <form method="POST" action="settings.php" class="form-section">
            <input type="hidden" name="action" value="update_system">
            
            <h2 class="form-section-title">
                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                System Options
            </h2>

            <div class="form-group">
                <div class="checkbox-label">
                    <input 
                        type="checkbox" 
                        id="maintenance_mode" 
                        name="maintenance_mode"
                        <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>
                    >
                    <span>Maintenance Mode</span>
                </div>
                <small class="form-help">When enabled, only administrators can access the system</small>
            </div>

            <div class="form-group">
                <div class="checkbox-label">
                    <input 
                        type="checkbox" 
                        id="allow_registration" 
                        name="allow_registration"
                        <?php echo $settings['allow_registration'] ? 'checked' : ''; ?>
                    >
                    <span>Allow User Registration</span>
                </div>
                <small class="form-help">Allow new users to register (currently disabled)</small>
            </div>

            <div class="form-group">
                <div class="checkbox-label">
                    <input 
                        type="checkbox" 
                        id="require_email_verification" 
                        name="require_email_verification"
                        <?php echo $settings['require_email_verification'] ? 'checked' : ''; ?>
                    >
                    <span>Require Email Verification</span>
                </div>
                <small class="form-help">New users must verify their email address</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Save System Options
                </button>
            </div>
        </form>

        <!-- Bakım ve Yönetim (Maintenance & Management) -->
        <div class="card">
            <div class="card-header">
                <h2>Maintenance & Management</h2>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-direction: column; gap: var(--spacing-md);">
                    <div style="padding: var(--spacing-md); background: var(--gray-50); border-radius: var(--radius-md);">
                        <h4 style="margin-bottom: var(--spacing-sm); color: var(--gray-900);">Clear Cache</h4>
                        <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: var(--spacing-md);">
                            Clear system cache to improve performance
                        </p>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Cache clearing functionality will be implemented')">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M3 6h18"></path>
                                <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                                <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                            </svg>
                            Clear Cache
                        </button>
                    </div>

                    <div style="padding: var(--spacing-md); background: var(--gray-50); border-radius: var(--radius-md);">
                        <h4 style="margin-bottom: var(--spacing-sm); color: var(--gray-900);">Database Backup</h4>
                        <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: var(--spacing-md);">
                            Create a backup of the database
                        </p>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="alert('Database backup functionality will be implemented')">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Create Backup
                        </button>
                    </div>

                    <div style="padding: var(--spacing-md); background: var(--gray-50); border-radius: var(--radius-md);">
                        <h4 style="margin-bottom: var(--spacing-sm); color: var(--gray-900);">Activity Logs</h4>
                        <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: var(--spacing-md);">
                            View system activity logs
                        </p>
                        <a href="activity_logs.php" class="btn btn-secondary btn-sm">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="16" y1="13" x2="8" y2="13"></line>
                                <line x1="16" y1="17" x2="8" y2="17"></line>
                                <polyline points="10 9 9 9 8 9"></polyline>
                            </svg>
                            View Logs
                        </a>
                    </div>

                    <div style="padding: var(--spacing-md); background: rgba(239, 68, 68, 0.05); border-radius: var(--radius-md); border: 1px solid rgba(239, 68, 68, 0.2);">
                        <h4 style="margin-bottom: var(--spacing-sm); color: var(--error-color);">⚠️ Danger Zone</h4>
                        <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: var(--spacing-md);">
                            These actions are irreversible. Use with caution.
                        </p>
                        <button type="button" class="btn btn-sm" style="background: var(--error-color); color: white;" 
                                onclick="if(confirm('Are you sure? This will delete ALL activity logs!')) alert('Activity logs cleared')">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                            Clear All Logs
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
