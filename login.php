<?php
/**
 * User Login Page
 * 
 * This page allows users to log in to the system.
 */

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

startSession();

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Logout message
if (isset($_GET['logout'])) {
    $success = 'You have successfully logged out.';
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // Don't sanitize password, hash comparison will be done
    
    // Validation
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        // Authentication
        $user = authenticateUser($username, $password);
        
        if ($user) {
            loginUser($user);
            header("Location: dashboard.php");
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - International Vision University</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .university-branding {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            gap: 20px;
        }
        
        .university-logo img {
            max-width: 120px;
            height: auto;
        }
        
        .university-name h2 {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
            line-height: 1.3;
        }
        
        .login-header h1 {
            font-size: 20px;
            margin: 10px 0 5px;
        }
        
        @media (max-width: 768px) {
            .university-branding {
                flex-direction: column;
                gap: 10px;
            }
            
            .university-name h2 {
                font-size: 20px;
                text-align: center;
            }
        }
    </style>
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <!-- Logo and Header -->
            <div class="login-header">
                <div class="university-branding">
                    <div class="university-logo">
                        <img src="assets/images/vision_logo_k.png" alt="Vision University Logo" onerror="this.style.display='none'">
                    </div>
                    <div class="university-name">
                        <h2>International Vision University</h2>
                    </div>
                </div>
                <h1>Academic Publication Repository</h1>
                <p class="subtitle">Faculty & Staff Portal</p>
            </div>

            <!-- Error and Success Messages -->
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

            <!-- Login Form -->
            <form method="POST" action="login.php" class="login-form">
                <div class="form-group">
                    <label for="username">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Username
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?php echo isset($username) ? sanitize($username) : ''; ?>"
                        required 
                        autofocus
                        placeholder="Enter your username"
                    >
                </div>

                <div class="form-group">
                    <label for="password">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Password
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        placeholder="Enter your password"
                    >
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember Me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Sign In
                </button>
            </form>

            <!-- Footer -->
            <div class="login-footer">
                <p>&copy; 2024 International Vision University</p>
                <p><a href="mailto:ilker@vision.edu.mk">Technical Support</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>