<?php
/**
 * Veritabanı Bağlantı Yöneticisi
 * 
 * Bu dosya PDO (PHP Data Objects) kullanarak güvenli veritabanı bağlantısı sağlar.
 * PDO'nun avantajları:
 * - Prepared statements ile SQL injection koruması
 * - Çoklu veritabanı desteği
 * - Exception handling ile hata yönetimi
 */

// CPanel Veritabanı Yapılandırması
define('DB_HOST', 'localhost');
define('DB_NAME', 'vizyoned_repo');
define('DB_USER', 'vizyoned_ilker');
define('DB_PASS', 'Z8jIZ^Goe6CU2bo7');
define('DB_CHARSET', 'utf8mb4');

/**
 * Veritabanı bağlantısı oluşturur
 * 
 * @return PDO Veritabanı bağlantı nesnesi
 * @throws PDOException Bağlantı hatası durumunda
 */
function getDBConnection() {
    static $pdo = null;
    
    // Singleton pattern: Tek bir bağlantı örneği kullan
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Hataları exception olarak fırlat
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Asosyatif dizi olarak getir
                PDO::ATTR_EMULATE_PREPARES   => false,                   // Gerçek prepared statements kullan
                PDO::ATTR_PERSISTENT         => false,                   // Kalıcı bağlantı kullanma
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            // Gerçek ortamda hata detaylarını log'a yazın, kullanıcıya göstermeyin
            error_log("Database connection error: " . $e->getMessage());
            
            // Geliştirme ortamında detaylı hata göstermek isterseniz (production'da kapatın):
            // die("Veritabanı bağlantı hatası: " . $e->getMessage());
            
            // Production için güvenli mesaj:
            die("Veritabanı bağlantı hatası. Lütfen sistem yöneticisiyle iletişime geçin.");
        }
    }
    
    return $pdo;
}
?>