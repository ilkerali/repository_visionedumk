<?php
/**
 * Kullanıcı Giriş Sayfası
 * 
 * Bu sayfa kullanıcıların sisteme giriş yapmasını sağlar.
 */

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

startSession();

// Zaten giriş yapmışsa dashboard'a yönlendir
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Çıkış mesajı
if (isset($_GET['logout'])) {
    $success = 'Başarıyla çıkış yaptınız.';
}

// Form gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? ''; // Şifreyi temizleme, hash karşılaştırması yapılacak
    
    // Validasyon
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        // Kimlik doğrulama
        $user = authenticateUser($username, $password);
        
        if ($user) {
            loginUser($user);
            header("Location: dashboard.php");
            exit();
        } else {
            $error = 'Kullanıcı adı veya şifre hatalı.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Uluslararası Vizyon Üniversitesi</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-box">
            <!-- Logo ve Başlık -->
            <div class="login-header">
                <div class="university-logo">
                    <img src="assets/images/logo.png" alt="University Logo" onerror="this.style.display='none'">
                </div>
                <h1>Akademik Yayın Repositori</h1>
                <p class="subtitle">Uluslararası Vizyon Üniversitesi</p>
            </div>

            <!-- Hata ve Başarı Mesajları -->
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

            <!-- Giriş Formu -->
            <form method="POST" action="login.php" class="login-form">
                <div class="form-group">
                    <label for="username">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Kullanıcı Adı
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?php echo isset($username) ? sanitize($username) : ''; ?>"
                        required 
                        autofocus
                        placeholder="Kullanıcı adınızı girin"
                    >
                </div>

                <div class="form-group">
                    <label for="password">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Şifre
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        placeholder="Şifrenizi girin"
                    >
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span>Beni Hatırla</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Şifremi Unuttum</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    Giriş Yap
                </button>
            </form>

            <!-- Test Bilgileri (Production'da kaldırın) -->
            <div class="test-credentials">
                <p><strong>Test Hesapları:</strong></p>
                <p>Admin: <code>admin</code> / Şifre: <code>123456</code></p>
                <p>Öğretim Üyesi: <code>ahmet.yilmaz</code> / Şifre: <code>123456</code></p>
            </div>

            <!-- Footer -->
            <div class="login-footer">
                <p>&copy; 2024 Uluslararası Vizyon Üniversitesi</p>
                <p><a href="mailto:support@uvt.edu.mk">Teknik Destek</a></p>
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>