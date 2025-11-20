<?php
/**
 * Yardımcı Fonksiyonlar Kütüphanesi
 * 
 * Bu dosya projenin farklı bölümlerinde kullanılacak
 * genel amaçlı fonksiyonları içerir.
 */

/**
 * HTML karakterlerini güvenli hale getirir (XSS koruması)
 * 
 * @param string $data Temizlenecek veri
 * @return string Temizlenmiş veri
 */
function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Form verilerini temizler ve doğrular
 * 
 * @param string $data Form verisi
 * @return string Temizlenmiş veri
 */
function cleanInput($data) {
    $data = trim($data);           // Başındaki ve sonundaki boşlukları temizle
    $data = stripslashes($data);   // Ters slash'leri kaldır
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');  // XSS koruması
    return $data;
}

/**
 * Kullanıcı oturumunu başlatır
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        // Güvenli session ayarları
        ini_set('session.cookie_httponly', 1);  // JavaScript erişimini engelle
        ini_set('session.use_only_cookies', 1); // Sadece cookie kullan
        ini_set('session.cookie_secure', 0);    // HTTPS kullanıyorsanız 1 yapın
        
        session_start();
    }
}

/**
 * Kullanıcının giriş yapıp yapmadığını kontrol eder
 * 
 * @return boolean Giriş yapılmışsa true
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Yetkilendirilmemiş erişimi engeller
 * 
 * @param string $redirectTo Yönlendirilecek sayfa
 */
function requireLogin($redirectTo = 'login.php') {
    if (!isLoggedIn()) {
        header("Location: $redirectTo");
        exit();
    }
}

/**
 * Kullanıcı aktivitesini loglar
 * 
 * @param PDO $pdo Veritabanı bağlantısı
 * @param int $userId Kullanıcı ID
 * @param string $action Yapılan işlem
 * @param string $tableName Etkilenen tablo
 * @param int $recordId Etkilenen kayıt ID
 */
function logActivity($pdo, $userId, $action, $tableName = null, $recordId = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, table_name, record_id, ip_address, user_agent) 
            VALUES (:user_id, :action, :table_name, :record_id, :ip_address, :user_agent)
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':table_name' => $tableName,
            ':record_id' => $recordId,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log("Activity logging error: " . $e->getMessage());
    }
}

/**
 * Başarı mesajı gösterir
 * 
 * @param string $message Mesaj metni
 * @return string HTML formatında mesaj
 */
function showSuccess($message) {
    return '<div class="alert alert-success">' . sanitize($message) . '</div>';
}

/**
 * Hata mesajı gösterir
 * 
 * @param string $message Mesaj metni
 * @return string HTML formatında mesaj
 */
function showError($message) {
    return '<div class="alert alert-error">' . sanitize($message) . '</div>';
}

/**
 * Tarih formatını düzenler (TR format)
 * 
 * @param string $date Tarih (Y-m-d formatında)
 * @return string Düzenlenmiş tarih
 */
function formatDate($date) {
    if (empty($date)) return '-';
    
    $timestamp = strtotime($date);
    return date('d.m.Y', $timestamp);
}
?>