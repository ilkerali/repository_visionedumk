<?php
/**
 * Ana Dashboard - Yönlendirme Merkezi
 * 
 * Bu sayfa kullanıcının rolüne göre doğru panele yönlendirir.
 * 
 * Roller:
 * - admin    → admin/dashboard.php
 * - faculty  → faculty/dashboard.php
 */

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

startSession();

// Giriş kontrolü
requireLogin('login.php');

// Session güvenlik kontrolü
if (!validateSession()) {
    header("Location: login.php?error=session_expired");
    exit();
}

// Rol bazlı yönlendirme
$userRole = $_SESSION['role'] ?? 'faculty';

switch ($userRole) {
    case 'admin':
        header("Location: admin/dashboard.php");
        exit();
        
    case 'faculty':
        header("Location: faculty/dashboard.php");
        exit();
        
    default:
        // Bilinmeyen rol - güvenlik için çıkış yap
        logoutUser();
        header("Location: login.php?error=invalid_role");
        exit();
}
?>