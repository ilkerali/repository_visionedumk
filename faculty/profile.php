<?php
/**
 * Faculty Profile Page - FIXED VERSION
 * 
 * Displays and allows editing of faculty member's profile information
 * 
 * FIXES:
 * - Removed dependency on 'department' column
 * - Uses department_id with JOIN to get department info
 * - Updated session variable references
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');

// Only faculty can access this page
if ($_SESSION['role'] !== 'faculty') {
    header("Location: ../dashboard.php");
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = cleanInput($_POST['full_name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $officeLocation = cleanInput($_POST['office_location'] ?? '');
    $bio = cleanInput($_POST['bio'] ?? '');
    $departmentId = isset($_POST['department_id']) ? (int)$_POST['department_id'] : null;
    
    // Validation
    if (empty($fullName) || empty($email)) {
        $error = "Full name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        try {
            // FIXED: Use department_id instead of department text
            $stmt = $pdo->prepare("
                UPDATE users 
                SET full_name = :full_name,
                    email = :email,
                    phone = :phone,
                    office_location = :office_location,
                    bio = :bio,
                    department_id = :department_id
                WHERE user_id = :user_id
            ");
            
            $stmt->execute([
                ':full_name' => $fullName,
                ':email' => $email,
                ':phone' => $phone,
                ':office_location' => $officeLocation,
                ':bio' => $bio,
                ':department_id' => $departmentId,
                ':user_id' => $userId
            ]);
            
            // Update session variables
            $_SESSION['full_name'] = $fullName;
            $_SESSION['email'] = $email;
            
            // FIXED: Update department session variables
            if ($departmentId) {
                $deptStmt = $pdo->prepare("
                    SELECT department_name_en, department_name_tr, department_code 
                    FROM departments 
                    WHERE department_id = :dept_id
                ");
                $deptStmt->execute([':dept_id' => $departmentId]);
                $dept = $deptStmt->fetch();
                
                if ($dept) {
                    $_SESSION['department_id'] = $departmentId;
                    $_SESSION['department_name_en'] = $dept['department_name_en'];
                    $_SESSION['department_name_tr'] = $dept['department_name_tr'];
                    $_SESSION['department_code'] = $dept['department_code'];
                }
            }
            
            // Log activity
            logActivity($pdo, $userId, 'profile_update', 'users', $userId);
            
            $success = "Profile updated successfully!";
            
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = "An error occurred while updating your profile. Please try again.";
        }
    }
}

// FIXED: Fetch user data with department JOIN
try {
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.full_name,
            u.email,
            u.phone,
            u.office_location,
            u.bio,
            u.department_id,
            u.created_at,
            u.last_login,
            d.department_name_en,
            d.department_name_tr,
            d.department_code,
            d.faculty_name
        FROM users u
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE u.user_id = :user_id
    ");
    
    $stmt->execute([':user_id' => $userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        die("User not found.");
    }
    
    // Get all departments for dropdown
    $deptStmt = $pdo->query("
        SELECT department_id, department_name_en, department_code, faculty_name
        FROM departments
        WHERE is_active = 1
        ORDER BY faculty_name, department_name_en
    ");
    $departments = $deptStmt->fetchAll();
    
    // Get user's publication statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_publications,
            COUNT(CASE WHEN publication_year = YEAR(CURDATE()) THEN 1 END) as this_year,
            COUNT(CASE WHEN publication_year = YEAR(CURDATE()) - 1 THEN 1 END) as last_year
        FROM publications
        WHERE user_id = :user_id
    ");
    $statsStmt->execute([':user_id' => $userId]);
    $stats = $statsStmt->fetch();
    
} catch (PDOException $e) {
    error_log("Profile fetch error: " . $e->getMessage());
    die("Error loading profile data.");
}

$page_title = "My Profile";
include 'faculty_header.php';
?>

<div class="page-header">
    <div>
        <h1>My Profile</h1>
        <p class="text-muted">View and edit your profile information</p>
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

<!-- Statistics Cards -->
<div class="stats-grid stats-grid-3">
    <div class="stat-card stat-primary">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_publications']; ?></h3>
            <p>Total Publications</p>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['this_year']; ?></h3>
            <p>This Year</p>
        </div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['last_year']; ?></h3>
            <p>Last Year</p>
        </div>
    </div>
</div>

<!-- Profile Form -->
<div class="card">
    <div class="card-header">
        <h2>Profile Information</h2>
    </div>
    <div class="card-body">
        <form method="POST" action="profile.php" class="form-horizontal">
            
            <!-- Basic Information -->
            <div class="form-section">
                <h3>Basic Information</h3>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        value="<?php echo sanitize($user['username']); ?>" 
                        disabled
                        class="input-disabled"
                    >
                    <small class="form-text">Username cannot be changed</small>
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        value="<?php echo sanitize($user['full_name']); ?>" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email <span class="required">*</span></label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo sanitize($user['email']); ?>" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        value="<?php echo sanitize($user['phone'] ?? ''); ?>"
                        placeholder="+389 XX XXX XXX"
                    >
                </div>

                <!-- FIXED: Department dropdown with department_id -->
                <div class="form-group">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id">
                        <option value="">Select Department</option>
                        <?php 
                        $currentFaculty = '';
                        foreach ($departments as $dept): 
                            if ($dept['faculty_name'] != $currentFaculty):
                                if ($currentFaculty != '') echo '</optgroup>';
                                $currentFaculty = $dept['faculty_name'];
                                echo '<optgroup label="' . sanitize($currentFaculty) . '">';
                            endif;
                        ?>
                            <option 
                                value="<?php echo $dept['department_id']; ?>"
                                <?php echo ($user['department_id'] == $dept['department_id']) ? 'selected' : ''; ?>
                            >
                                <?php echo sanitize($dept['department_name_en']); ?> 
                                (<?php echo sanitize($dept['department_code']); ?>)
                            </option>
                        <?php 
                        endforeach;
                        if ($currentFaculty != '') echo '</optgroup>';
                        ?>
                    </select>
                    <!-- FIXED: Display current department -->
                    <?php if ($user['department_name_en']): ?>
                        <small class="form-text">
                            Current: <?php echo sanitize($user['department_name_en']); ?> 
                            (<?php echo sanitize($user['department_code']); ?>)
                        </small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="office_location">Office Location</label>
                    <input 
                        type="text" 
                        id="office_location" 
                        name="office_location" 
                        value="<?php echo sanitize($user['office_location'] ?? ''); ?>"
                        placeholder="e.g., Building A, Room 305"
                    >
                </div>
            </div>

            <!-- Bio Section -->
            <div class="form-section">
                <h3>Biography</h3>
                
                <div class="form-group">
                    <label for="bio">About Me</label>
                    <textarea 
                        id="bio" 
                        name="bio" 
                        rows="6"
                        placeholder="Tell us about your research interests, academic background, etc."
                    ><?php echo sanitize($user['bio'] ?? ''); ?></textarea>
                    <small class="form-text">This information will be displayed on your public profile</small>
                </div>
            </div>

            <!-- Account Information (Read-only) -->
            <div class="form-section">
                <h3>Account Information</h3>
                
                <div class="info-grid">
                    <div class="info-item">
                        <label>Member Since</label>
                        <p><?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                    </div>

                    <div class="info-item">
                        <label>Last Login</label>
                        <p>
                            <?php 
                            if ($user['last_login']) {
                                echo date('F j, Y g:i A', strtotime($user['last_login']));
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </p>
                    </div>

                    <div class="info-item">
                        <label>User ID</label>
                        <p><?php echo $user['user_id']; ?></p>
                    </div>

                    <div class="info-item">
                        <label>Role</label>
                        <p>
                            <span class="badge badge-faculty">Faculty Member</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Save Changes
                </button>
                
                <a href="dashboard.php" class="btn btn-secondary">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Additional CSS for info grid -->
<style>
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-lg);
    margin-top: var(--spacing-md);
}

.info-item label {
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
    display: block;
}

.info-item p {
    color: var(--text-color);
    font-size: 1rem;
    margin: 0;
}

.form-section {
    margin-bottom: var(--spacing-xl);
    padding-bottom: var(--spacing-xl);
    border-bottom: 1px solid var(--gray-200);
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section h3 {
    margin-bottom: var(--spacing-lg);
    color: var(--gray-800);
    font-size: 1.25rem;
}

.input-disabled {
    background-color: var(--gray-100);
    cursor: not-allowed;
}

.required {
    color: var(--error-color);
}

.badge-faculty {
    background-color: var(--success-color);
    color: white;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 500;
}
</style>

<?php include 'faculty_footer.php'; ?>
