<?php
/**
 * Add New User Page
 * 
 * Yeni kullanıcı ekleme formu
 * Form to add new users to the system
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

// Form gönderildi mi? (Form submitted?)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $fullName = cleanInput($_POST['full_name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $department = cleanInput($_POST['department'] ?? '');
    $role = cleanInput($_POST['role'] ?? 'faculty');
    $phone = cleanInput($_POST['phone'] ?? '');
    $officeLocation = cleanInput($_POST['office_location'] ?? '');
    $bio = cleanInput($_POST['bio'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Validasyon (Validation)
    if (empty($username) || empty($password) || empty($fullName) || empty($email) || empty($department)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } elseif (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        $error = "Username can only contain letters, numbers, dots, hyphens and underscores.";
    } elseif (!in_array($role, ['admin', 'faculty'])) {
        $error = "Invalid role selected.";
    } else {
        try {
            // Kullanıcı adı ve email kontrolü (Check if username or email exists)
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = :username OR email = :email");
            $stmtCheck->execute([':username' => $username, ':email' => $email]);
            
            if ($stmtCheck->fetch()['count'] > 0) {
                $error = "Username or email already exists!";
            } else {
                // Şifreyi hashle (Hash password)
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                
                // Kullanıcıyı ekle (Insert user)
                $stmt = $pdo->prepare("
                    INSERT INTO users (
                        username, password_hash, full_name, email, department, 
                        role, phone, office_location, bio, is_active, created_at
                    ) VALUES (
                        :username, :password_hash, :full_name, :email, :department,
                        :role, :phone, :office_location, :bio, :is_active, NOW()
                    )
                ");
                
                $stmt->execute([
                    ':username' => $username,
                    ':password_hash' => $passwordHash,
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':department' => $department,
                    ':role' => $role,
                    ':phone' => $phone,
                    ':office_location' => $officeLocation,
                    ':bio' => $bio,
                    ':is_active' => $isActive
                ]);
                
                $newUserId = $pdo->lastInsertId();
                
                // Log activity
                logActivity($pdo, $_SESSION['user_id'], 'user_create', 'users', $newUserId);
                
                $success = "User created successfully!";
                
                // Formu temizle (Clear form)
                $_POST = [];
            }
            
        } catch (PDOException $e) {
            error_log("User creation error: " . $e->getMessage());
            $error = "Error creating user. Please try again.";
        }
    }
}

// Sayfa başlığı (Page title)
$page_title = "Add New User";
include 'admin_header.php';
?>

<!-- Sayfa Başlığı (Page header) -->
<div class="page-header">
    <div>
        <h1>Add New User</h1>
        <p class="text-muted">Create a new user account for the system</p>
    </div>
    <a href="users.php" class="btn btn-secondary">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <line x1="19" y1="12" x2="5" y2="12"></line>
            <polyline points="12 19 5 12 12 5"></polyline>
        </svg>
        Back to Users
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

<!-- Kullanıcı Ekleme Formu (Add user form) -->
<form method="POST" action="add_user.php">
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
                <label for="username">
                    Username <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="<?php echo isset($_POST['username']) ? sanitize($_POST['username']) : ''; ?>"
                    required
                    pattern="[a-zA-Z0-9._-]+"
                    placeholder="john.doe"
                >
                <small class="form-help">Only letters, numbers, dots, hyphens and underscores allowed</small>
            </div>

            <div class="form-group">
                <label for="full_name">
                    Full Name <span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="full_name" 
                    name="full_name" 
                    value="<?php echo isset($_POST['full_name']) ? sanitize($_POST['full_name']) : ''; ?>"
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
                    value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>"
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
                    value="<?php echo isset($_POST['department']) ? sanitize($_POST['department']) : ''; ?>"
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
                    value="<?php echo isset($_POST['phone']) ? sanitize($_POST['phone']) : ''; ?>"
                    placeholder="+389 XX XXX XXX"
                >
            </div>

            <div class="form-group">
                <label for="office_location">Office Location</label>
                <input 
                    type="text" 
                    id="office_location" 
                    name="office_location" 
                    value="<?php echo isset($_POST['office_location']) ? sanitize($_POST['office_location']) : ''; ?>"
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
            ><?php echo isset($_POST['bio']) ? sanitize($_POST['bio']) : ''; ?></textarea>
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
                    <option value="faculty" <?php echo (isset($_POST['role']) && $_POST['role'] === 'faculty') ? 'selected' : 'selected'; ?>>
                        Faculty Member
                    </option>
                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>
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
                        <?php echo (!isset($_POST['is_active']) || isset($_POST['is_active'])) ? 'checked' : ''; ?>
                    >
                    <span>Account is Active</span>
                </div>
                <small class="form-help">Inactive users cannot login to the system</small>
            </div>
        </div>
    </div>

    <!-- Şifre Ayarları (Password Settings) -->
    <div class="form-section">
        <h2 class="form-section-title">
            <svg class="section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            Password Settings
        </h2>

        <div class="form-row">
            <div class="form-group">
                <label for="password">
                    Password <span class="required">*</span>
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required
                    minlength="6"
                    placeholder="Enter password"
                >
                <small class="form-help">Minimum 6 characters</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">
                    Confirm Password <span class="required">*</span>
                </label>
                <input 
                    type="password" 
                    id="confirm_password" 
                    name="confirm_password" 
                    required
                    minlength="6"
                    placeholder="Confirm password"
                >
            </div>
        </div>
    </div>

    <!-- Form İşlemleri (Form actions) -->
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="8.5" cy="7" r="4"></circle>
                <line x1="20" y1="8" x2="20" y2="14"></line>
                <line x1="23" y1="11" x2="17" y2="11"></line>
            </svg>
            Create User
        </button>
        <a href="users.php" class="btn btn-secondary">Cancel</a>
        <button type="reset" class="btn btn-secondary">Reset Form</button>
    </div>
</form>

<?php include 'admin_footer.php'; ?>
