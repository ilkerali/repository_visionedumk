<?php
/**
 * Edit User Page
 * 
 * Mevcut kullanıcıyı düzenleme formu
 * Form to edit existing user information
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
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Kullanıcı bilgilerini getir (Fetch user data)
try {
    $stmt = $pdo->prepare("
        SELECT user_id, username, full_name, email, department, role,
               phone, office_location, bio, is_active, created_at, last_login
        FROM users 
        WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: users.php?error=user_not_found");
        exit();
    }
} catch (PDOException $e) {
    error_log("User fetch error: " . $e->getMessage());
    header("Location: users.php?error=database_error");
    exit();
}

// Form gönderildi mi? (Form submitted?)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = cleanInput($_POST['full_name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $department = cleanInput($_POST['department'] ?? '');
    $role = cleanInput($_POST['role'] ?? 'faculty');
    $phone = cleanInput($_POST['phone'] ?? '');
    $officeLocation = cleanInput($_POST['office_location'] ?? '');
    $bio = cleanInput($_POST['bio'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Şifre değişikliği (Password change)
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validasyon (Validation)
    if (empty($fullName) || empty($email) || empty($department)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!in_array($role, ['admin', 'faculty'])) {
        $error = "Invalid role selected.";
    } elseif (!empty($newPassword) && strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Email benzersizlik kontrolü (Check email uniqueness)
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = :email AND user_id != :user_id");
            $stmtCheck->execute([':email' => $email, ':user_id' => $userId]);
            
            if ($stmtCheck->fetch()['count'] > 0) {
                $error = "Email already exists for another user!";
            } else {
                // Kullanıcıyı güncelle (Update user)
                $sql = "
                    UPDATE users SET
                        full_name = :full_name,
                        email = :email,
                        department = :department,
                        role = :role,
                        phone = :phone,
                        office_location = :office_location,
                        bio = :bio,
                        is_active = :is_active
                ";
                
                $params = [
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':department' => $department,
                    ':role' => $role,
                    ':phone' => $phone,
                    ':office_location' => $officeLocation,
                    ':bio' => $bio,
                    ':is_active' => $isActive,
                    ':user_id' => $userId
                ];
                
                // Şifre güncelleme varsa (If password update exists)
                if (!empty($newPassword)) {
                    $sql .= ", password_hash = :password_hash";
                    $params[':password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                }
                
                $sql .= " WHERE user_id = :user_id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                // Log activity
                logActivity($pdo, $_SESSION['user_id'], 'user_update', 'users', $userId);
                
                $success = "User updated successfully!";
                
                // Güncellenmiş verileri al (Refresh user data)
                $user['full_name'] = $fullName;
                $user['email'] = $email;
                $user['department'] = $department;
                $user['role'] = $role;
                $user['phone'] = $phone;
                $user['office_location'] = $officeLocation;
                $user['bio'] = $bio;
                $user['is_active'] = $isActive;
            }
            
        } catch (PDOException $e) {
            error_log("User update error: " . $e->getMessage());
            $error = "Error updating user. Please try again.";
        }
    }
}

// Sayfa başlığı (Page title)
$page_title = "Edit User";
include 'admin_header.php';
?>

<!-- Sayfa Başlığı (Page header) -->
<div class="page-header">
    <div>
        <h1>Edit User</h1>
        <p class="text-muted">Update user information and settings</p>
    </div>
    <div style="display: flex; gap: var(--spacing-sm);">
        <a href="view_user.php?id=<?php echo $userId; ?>" class="btn btn-secondary">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            View Profile
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

<!-- Kullanıcı Düzenleme Formu (Edit user form) -->
<form method="POST" action="edit_user.php?id=<?php echo $userId; ?>">
    <!-- Temel Bilgiler (Basic Information) -->
    <div class="form-section">
        <h2 class="form-section-title">
            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle cx="12" cy="7" r="4"></circle>
            </svg>
            Basic Information
        </h2>

        <div class="form-row">
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    value="<?php echo sanitize($user['username']); ?>"
                    disabled
                >
                <small class="form-help">Username cannot be changed</small>
            </div>

            <div class="form-group">
                <label for="full_name">
                    Full Name <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="full_name" 
                    name="full_name" 
                    value="<?php echo sanitize($user['full_name']); ?>"
                    required
                    placeholder="John Doe"
                >
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="email">
                    Email Address <span class="required">*</span>
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?php echo sanitize($user['email']); ?>"
                    required
                    placeholder="john.doe@uvt.edu.mk"
                >
            </div>

            <div class="form-group">
                <label for="department">
                    Department <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="department" 
                    name="department" 
                    value="<?php echo sanitize($user['department']); ?>"
                    required
                    placeholder="Computer Science"
                >
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input 
                    type="text" 
                    id="phone" 
                    name="phone" 
                    value="<?php echo sanitize($user['phone'] ?? ''); ?>"
                    placeholder="+389 XX XXX XXX"
                >
            </div>

            <div class="form-group">
                <label for="office_location">Office Location</label>
                <input 
                    type="text" 
                    id="office_location" 
                    name="office_location" 
                    value="<?php echo sanitize($user['office_location'] ?? ''); ?>"
                    placeholder="Building A, Room 201"
                >
            </div>
        </div>

        <div class="form-group">
            <label for="bio">Biography</label>
            <textarea 
                id="bio" 
                name="bio" 
                rows="4"
                placeholder="Brief description about the user's academic background and research interests..."
            ><?php echo sanitize($user['bio'] ?? ''); ?></textarea>
        </div>
    </div>

    <!-- Hesap Ayarları (Account Settings) -->
    <div class="form-section">
        <h2 class="form-section-title">
            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M12 1v6m0 6v6m9-9h-6m-6 0H3"></path>
            </svg>
            Account Settings
        </h2>

        <div class="form-row">
            <div class="form-group">
                <label for="role">
                    Role <span class="required">*</span>
                </label>
                <select id="role" name="role" required>
                    <option value="faculty" <?php echo ($user['role'] === 'faculty') ? 'selected' : ''; ?>>
                        Faculty Member
                    </option>
                    <option value="admin" <?php echo ($user['role'] === 'admin') ? 'selected' : ''; ?>>
                        Administrator
                    </option>
                </select>
                <small class="form-help">Administrators have full system access</small>
            </div>

            <div class="form-group">
                <label>&nbsp;</label>
                <div class="checkbox-label">
                    <input 
                        type="checkbox" 
                        id="is_active" 
                        name="is_active"
                        <?php echo $user['is_active'] ? 'checked' : ''; ?>
                    >
                    <span>Account is Active</span>
                </div>
                <small class="form-help">Inactive users cannot login to the system</small>
            </div>
        </div>

        <div style="padding: var(--spacing-md); background: var(--gray-100); border-radius: var(--radius-md); margin-top: var(--spacing-md);">
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: var(--spacing-md);">
                <div>
                    <strong style="color: var(--gray-700);">Member Since:</strong><br>
                    <span style="color: var(--gray-600);"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                </div>
                <div>
                    <strong style="color: var(--gray-700);">Last Login:</strong><br>
                    <span style="color: var(--gray-600);">
                        <?php echo $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Şifre Değiştirme (Change Password) -->
    <div class="form-section">
        <h2 class="form-section-title">
            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            Change Password (Optional)
        </h2>

        <div class="alert alert-info">
            <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            Leave password fields empty if you don't want to change the password.
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input 
                    type="password" 
                    id="new_password" 
                    name="new_password" 
                    minlength="6"
                    placeholder="Enter new password"
                >
                <small class="form-help">Minimum 6 characters</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    minlength="6"
                    placeholder="Confirm new password"
                >
            </div>
        </div>
    </div>

    <!-- Form İşlemleri (Form actions) -->
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            Save Changes
        </button>
        <a href="users.php" class="btn btn-secondary">Cancel</a>
        <button type="reset" class="btn btn-secondary">Reset Form</button>
    </div>
</form>

<?php include 'admin_footer.php'; ?>
