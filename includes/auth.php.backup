<?php
/**
 * Kimlik Doğrulama (Authentication) Sistemi
 * 
 * Bu modül kullanıcı girişi, çıkışı ve oturum yönetimini sağlar.
 * Güvenlik prensipleri:
 * - Password hashing (bcrypt)
 * - Session hijacking koruması
 * - Brute force attack koruması (basit versiyon)
 */

require_once 'functions.php';
require_once __DIR__ . '/../config/database.php';

/**
 * Kullanıcı girişini doğrular
 * 
 * @param string $username Kullanıcı adı
 * @param string $password Şifre
 * @return array|false Başarılıysa kullanıcı bilgileri, değilse false
 */
function authenticateUser($username, $password) {
    $pdo = getDBConnection();
    
    try {
        // Kullanıcıyı veritabanından al
        $stmt = $pdo->prepare("
            SELECT user_id, username, password_hash, full_name, email, 
                   department, role, is_active 
            FROM users 
            WHERE username = :username AND is_active = TRUE
            LIMIT 1
        ");
        
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();
        
        // Kullanıcı bulunamadı
        if (!$user) {
            return false;
        }
        
        // Şifre doğrulama (bcrypt ile hash karşılaştırma)
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }
        
        // Eski hash algoritması kontrolü (rehash gerekebilir)
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE user_id = :id");
            $updateStmt->execute([':hash' => $newHash, ':id' => $user['user_id']]);
        }
        
        // Son giriş zamanını güncelle
        $updateLogin = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :id");
        $updateLogin->execute([':id' => $user['user_id']]);
        
        // Aktivite logla
        logActivity($pdo, $user['user_id'], 'login');
        
        // Şifre hash'ini döndürme (güvenlik)
        unset($user['password_hash']);
        
        return $user;
        
    } catch (PDOException $e) {
        error_log("Authentication error: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcı oturumu başlatır
 * 
 * @param array $user Kullanıcı bilgileri
 */
function loginUser($user) {
    startSession();
    
    // Session fixation saldırılarını önle
    session_regenerate_id(true);
    
    // Kullanıcı bilgilerini session'a kaydet
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['department'] = $user['department'];
    
    // Güvenlik için ek bilgiler
    $_SESSION['login_time'] = time();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
}

/**
 * Kullanıcı çıkışı yapar
 */
function logoutUser() {
    startSession();
    
    // Aktivite logla
    if (isset($_SESSION['user_id'])) {
        $pdo = getDBConnection();
        logActivity($pdo, $_SESSION['user_id'], 'logout');
    }
    
    // Session verilerini temizle
    $_SESSION = array();
    
    // Session cookie'sini sil
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Session'ı yok et
    session_destroy();
}

/**
 * Kullanıcının admin olup olmadığını kontrol eder
 * 
 * @return boolean Admin ise true
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Admin yetkisi gerektirir
 * 
 * @param string $redirectTo Yetki yoksa yönlendirilecek sayfa
 */
function requireAdmin($redirectTo = 'dashboard.php') {
    requireLogin();
    
    if (!isAdmin()) {
        header("Location: $redirectTo?error=unauthorized");
        exit();
    }
}

/**
 * Session güvenlik kontrolü (Session hijacking koruması)
 * 
 * @return boolean Session geçerliyse true
 */
function validateSession() {
    if (!isLoggedIn()) {
        return false;
    }
    
    // User agent değişmişse şüpheli
    $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $currentUserAgent) {
        logoutUser();
        return false;
    }
    
    // Session timeout kontrolü (örnek: 2 saat)
    $maxLifetime = 2 * 60 * 60; // 2 saat
    if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > $maxLifetime) {
        logoutUser();
        return false;
    }
    
    return true;
}

/**
 * CSRF token oluşturur
 * 
 * @return string CSRF token
 */
function generateCSRFToken() {
    startSession();
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * CSRF token doğrular
 * 
 * @param string $token Doğrulanacak token
 * @return boolean Token geçerliyse true
 */
function validateCSRFToken($token) {
    startSession();
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>