<?php
/**
 * Edit User Page
 * 
 * Form to edit existing users with department dropdown
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

// Get user ID from URL
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($userId <= 0) {
    header("Location: users.php");
    exit();
}

// Get departments list for dropdown
try {
    $stmtDepts = $pdo->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY display_order, department_name_en");
    $departments = $stmtDepts->fetchAll();
} catch (PDOException $e) {
    error_log("Get departments error: " . $e->getMessage());
    $departments = [];
}

// Get user data
try {
    // IMPORTANT: Join with departments table to get current department name
    $stmt = $pdo->prepare("
        SELECT u.*, d.department_name_en, d.department_name_tr 
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: users.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Get user error: " . $e->getMessage());
    $error = "Error loading user data.";
}

// Form submitted?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = cleanInput($_POST['full_name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $departmentId = !empty($_POST['department_id']) ? intval($_POST['department_id']) : null;
    $role = cleanInput($_POST['role'] ?? 'faculty');
    $phone = cleanInput($_POST['phone'] ?? '');
    $officeLocation = cleanInput($_POST['office_location'] ?? '');
    $bio = cleanInput($_POST['bio'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Change password if provided
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($fullName) || empty($email)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (!empty($newPassword) && strlen($newPassword) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif (!in_array($role, ['admin', 'faculty'])) {
        $error = "Invalid role selected.";
    } else {
        try {
            // Check if email exists for other users
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = :email AND user_id != :user_id");
            $stmtCheck->execute([':email' => $email, ':user_id' => $userId]);
            
            if ($stmtCheck->fetch()['count'] > 0) {
                $error = "Email already exists for another user!";
            } else {
                // Update user
                if (!empty($newPassword)) {
                    // Update with new password
                    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE users SET
                            full_name = :full_name,
                            email = :email,
                            department_id = :department_id,
                            role = :role,
                            phone = :phone,
                            office_location = :office_location,
                            bio = :bio,
                            is_active = :is_active,
                            password_hash = :password_hash
                        WHERE user_id = :user_id
                    ");
                    $stmt->execute([
                        ':full_name' => $fullName,
                        ':email' => $email,
                        ':department_id' => $departmentId,
                        ':role' => $role,
                        ':phone' => $phone,
                        ':office_location' => $officeLocation,
                        ':bio' => $bio,
                        ':is_active' => $isActive,
                        ':password_hash' => $passwordHash,
                        ':user_id' => $userId
                    ]);
                } else {
                    // Update without password
                    $stmt = $pdo->prepare("
                        UPDATE users SET
                            full_name = :full_name,
                            email = :email,
                            department_id = :department_id,
                            role = :role,
                            phone = :phone,
                            office_location = :office_location,
                            bio = :bio,
                            is_active = :is_active
                        WHERE user_id = :user_id
                    ");
                    $stmt->execute([
                        ':full_name' => $fullName,
                        ':email' => $email,
                        ':department_id' => $departmentId,
                        ':role' => $role,
                        ':phone' => $phone,
                        ':office_location' => $officeLocation,
                        ':bio' => $bio,
                        ':is_active' => $isActive,
                        ':user_id' => $userId
                    ]);
                }
                
                // Log activity
                logActivity($pdo, $_SESSION['user_id'], 'user_update', 'users', $userId);
                
                $success = "User updated successfully!";
                
                // Refresh user data
                $stmt = $pdo->prepare("
                    SELECT u.*, d.department_name_en, d.department_name_tr 
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.department_id
                    WHERE u.user_id = ?
                ");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            }
            
        } catch (PDOException $e) {
            error_log("User update error: " . $e->getMessage());
            $error = "Error updating user. Please try again.";
        }
    }
}

$page_title = "Edit User";
include 'admin_header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>Edit User</h1>
        <p class="text-muted">Update user information and settings</p>
    </div>
    <a href="users.php" class="btn btn-secondary">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Back to Users
    </a>
</div>

<!-- Alert Messages -->
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

<!-- Edit User Form -->
<form method="POST" action="edit_user.php?id=<?php echo $userId; ?>">
    <!-- Basic Information -->
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
                    name="username" 
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
                >
            </div>

            <div class="form-group">
                <label for="department_id">Department</label>
                <select id="department_id" name="department_id">
                    <option value="">-- Select Department --</option>
                    <?php 
                    $current_faculty = '';
                    foreach ($departments as $dept): 
                        // Group by faculty
                        if ($dept['faculty_name'] && $dept['faculty_name'] != $current_faculty) {
                            if ($current_faculty != '') echo '</optgroup>';
                            echo '<optgroup label="' . htmlspecialchars($dept['faculty_name']) . '">';
                            $current_faculty = $dept['faculty_name'];
                        }
                        
                        // CRITICAL: Check if this department is the user's current department
                        $selected = ($user['department_id'] == $dept['department_id']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $dept['department_id']; ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($dept['department_name_en']); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if ($current_faculty != '') echo '</optgroup>'; ?>
                </select>
                <?php if (!empty($user['department_name_en'])): ?>
                    <small class="form-help">Current: <?php echo sanitize($user['department_name_en']); ?></small>
                <?php endif; ?>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input 
                    type="text" 
                    id="phone" 
                    name="phone" 
                    value="<?php echo sanitize($user['phone']); ?>"
                    placeholder="+389 XX XXX XXX"
                >
            </div>

            <div class="form-group">
                <label for="office_location">Office Location</label>
                <input 
                    type="text" 
                    id="office_location" 
                    name="office_location" 
                    value="<?php echo sanitize($user['office_location']); ?>"
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
                placeholder="Brief description..."
            ><?php echo sanitize($user['bio']); ?></textarea>
        </div>
    </div>

    <!-- Account Settings -->
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
            </div>
        </div>
    </div>

    <!-- Change Password (Optional) -->
    <div class="form-section">
        <h2 class="form-section-title">
            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            Change Password (Optional)
        </h2>

        <div class="form-row">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input 
                    type="password" 
                    id="new_password" 
                    name="new_password"
                    minlength="6"
                    placeholder="Leave blank to keep current password"
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

    <!-- Form Actions -->
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"></path>
                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                <polyline points="7 3 7 8 15 8"></polyline>
            </svg>
            Update User
        </button>
        <a href="users.php" class="btn btn-secondary">Cancel</a>
        <a href="view_user.php?id=<?php echo $userId; ?>" class="btn btn-secondary">View Details</a>
    </div>
</form>

<script>
// Password confirmation validation
document.querySelector('form').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (newPassword && newPassword !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return false;
    }
    
    if (newPassword && newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
    }
});
</script>

<?php include 'admin_footer.php'; ?>