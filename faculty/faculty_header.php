<?php
/**
 * Faculty Header - Navigation Menu
 * 
 * This file contains the header and navigation menu for faculty pages.
 * Include this file at the top of all faculty pages for consistency.
 */

// Ensure session is started and user is authenticated
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get user information
$user_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'faculty';
$user_title = '';

// Set user title based on role or custom title
if (isset($_SESSION['title'])) {
    $user_title = $_SESSION['title'];
} else {
    $user_title = ($user_role === 'admin') ? 'Administrator' : 'Faculty Member';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Academic Publication Repository'; ?> - International Vision University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    
    <style>
        /* Icon size control - prevent oversized icons */
        .navbar svg,
        .nav-link svg {
            width: 20px !important;
            height: 20px !important;
            max-width: 20px !important;
            max-height: 20px !important;
        }
        
        .navbar-logo {
            width: 40px !important;
            height: 40px !important;
            max-width: 40px !important;
            max-height: 40px !important;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <!-- Brand -->
            <div class="navbar-brand">
                <img src="../assets/images/vision_logo_k.png" alt="Vision University" class="navbar-logo">
                <span class="navbar-title">Academic Publication Repository</span>
            </div>

            <!-- Navigation Menu -->
            <div class="navbar-menu">
                <a href="dashboard.php" class="nav-link <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="add_publication.php" class="nav-link <?php echo ($current_page === 'add_publication.php') ? 'active' : ''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                    <span>New Publication</span>
                </a>

                <a href="my_publications.php" class="nav-link <?php echo ($current_page === 'my_publications.php') ? 'active' : ''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                    <span>My Publications</span>
                </a>

                <a href="profile.php" class="nav-link <?php echo ($current_page === 'profile.php') ? 'active' : ''; ?>">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Profile</span>
                </a>
            </div>

            <!-- User Info -->
            <div class="navbar-user">
                <div class="user-info">
                    <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                    <span class="user-role"><?php echo htmlspecialchars($user_title); ?></span>
                    <a href="cv_information.php">My CV Information</a>
                </div>
                <a href="../logout.php" class="btn btn-secondary btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width: 16px; height: 16px; margin-right: 4px;">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
