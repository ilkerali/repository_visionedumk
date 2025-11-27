<?php
/**
 * Faculty Profile Page
 * 
 * Allows faculty members to view and update their profile information
 * Including personal info, contact details, and password change
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');

// Only faculty members can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../dashboard.php");
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch user data
try {
    $stmt = $pdo->prepare("
        SELECT user_id, username, full_name, email, department, 
               phone, office_location, bio, profile_image,
               created_at, last_login
        FROM users 
        WHERE user_id = :user_id
    ");
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        header("Location: ../logout.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    $error = "Error loading profile information.";
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_profile') {
        $fullName = cleanInput($_POST['full_name'] ?? '');
        $email = cleanInput($_POST['email'] ?? '');
        $phone = cleanInput($_POST['phone'] ?? '');
        $officeLocation = cleanInput($_POST['office_location'] ?? '');
        $bio = cleanInput($_POST['bio'] ?? '');
        
        // Validation
        if (empty($fullName) || empty($email)) {
            $error = "Full name and email are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET full_name = :full_name,
                        email = :email,
                        phone = :phone,
                        office_location = :office_location,
                        bio = :bio
                    WHERE user_id = :user_id
                ");
                
                $stmt->execute([
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':office_location' => $officeLocation,
                    ':bio' => $bio,
                    ':user_id' => $userId
                ]);
                
                // Update session
                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = $email;
                
                // Log activity
                logActivity($pdo, $userId, 'profile_update', 'users', $userId);
                
                $success = "Profile updated successfully!";
                
                // Refresh user data
                $user['full_name'] = $fullName;
                $user['email'] = $email;
                $user['phone'] = $phone;
                $user['office_location'] = $officeLocation;
                $user['bio'] = $bio;
                
            } catch (PDOException $e) {
                error_log("Profile update error: " . $e->getMessage());
                $error = "Error updating profile. Please try again.";
            }
        }
    }
    
    // Handle password change
    if ($_POST['action'] === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = "All password fields are required.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "New passwords do not match.";
        } elseif (strlen($newPassword) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            try {
                // Verify current password
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $passwordHash = $stmt->fetchColumn();
                
                if (!password_verify($currentPassword, $passwordHash)) {
                    $error = "Current password is incorrect.";
                } else {
                    // Update password
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :user_id");
                    $stmt->execute([':hash' => $newHash, ':user_id' => $userId]);
                    
                    // Log activity
                    logActivity($pdo, $userId, 'password_change', 'users', $userId);
                    
                    $success = "Password changed successfully!";
                }
            } catch (PDOException $e) {
                error_log("Password change error: " . $e->getMessage());
                $error = "Error changing password. Please try again.";
            }
        }
    }
}

// Set page title for header
$page_title = "Profile";
include 'faculty_header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>My Profile</h1>
        <p class="text-muted">Manage your account settings and preferences</p>
    </div>
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

<!-- Profile Content -->
<div class="dashboard-row">
    <!-- Profile Information -->
    <div class="dashboard-col-6">
        <form method="POST" action="profile.php" class="form-section">
            <input type="hidden" name="action" value="update_profile">
            
            <h2 class="form-section-title">
                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                Personal Information
            </h2>

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
                >
            </div>

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
                <label for="department">Department</label>
                <input 
                    type="text" 
                    id="department" 
                    value="<?php echo sanitize($user['department']); ?>" 
                    disabled
                >
                <small class="form-help">Contact admin to change department</small>
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
                    rows="5"
                    placeholder="Tell us about your research interests, academic background, and expertise..."
                ><?php echo sanitize($user['bio'] ?? ''); ?></textarea>
                <small class="form-help">This will be displayed on your public profile</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Save Changes
                </button>
                <button type="reset" class="btn btn-secondary">Reset</button>
            </div>
        </form>
    </div>

    <!-- Password Change & Account Info -->
    <div class="dashboard-col-6">
        <!-- Change Password -->
        <form method="POST" action="profile.php" class="form-section">
            <input type="hidden" name="action" value="change_password">
            
            <h2 class="form-section-title">
                <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Change Password
            </h2>

            <div class="form-group">
                <label for="current_password">
                    Current Password <span class="required">*</span>
                </label>
                <input 
                    type="password" 
                    id="current_password" 
                    name="current_password"
                    placeholder="Enter your current password"
                >
            </div>

            <div class="form-group">
                <label for="new_password">
                    New Password <span class="required">*</span>
                </label>
                <input 
                    type="password" 
                    id="new_password" 
                    name="new_password"
                    placeholder="Enter new password"
                    minlength="6"
                >
                <small class="form-help">Minimum 6 characters</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">
                    Confirm New Password <span class="required">*</span>
                </label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password"
                    placeholder="Confirm new password"
                    minlength="6"
                >
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                    </svg>
                    Change Password
                </button>
            </div>
        </form>

        <!-- Account Statistics -->
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
                            <?php 
                            echo $user['last_login'] 
                                ? date('M j, Y H:i', strtotime($user['last_login'])) 
                                : 'N/A'; 
                            ?>
                        </strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; padding: var(--spacing-sm) 0;">
                        <span style="color: var(--gray-600);">Total Publications</span>
                        <strong>
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM publications WHERE user_id = :user_id");
                                $stmt->execute([':user_id' => $userId]);
                                echo $stmt->fetchColumn();
                            } catch (PDOException $e) {
                                echo 'N/A';
                            }
                            ?>
                        </strong>
                    </div>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="card">
            <div class="card-header">
                <h2>Need Help?</h2>
            </div>
            <div class="card-body">
                <p style="margin-bottom: var(--spacing-md); color: var(--gray-600);">
                    If you need to update information that cannot be changed here (like username or department), 
                    please contact the system administrator.
                </p>
                <a href="mailto:admin@uvt.edu.mk" class="btn btn-secondary btn-block">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                        <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                    Contact Administrator
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'faculty_footer.php'; ?>
