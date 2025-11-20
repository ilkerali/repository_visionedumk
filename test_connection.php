<?php
require_once 'config/database.php';

try {
    $pdo = getDBConnection();
    echo "<h2>✅ Veritabanı Bağlantısı Başarılı!</h2>";
    echo "<p><strong>Veritabanı:</strong> " . DB_NAME . "</p>";
    echo "<p><strong>Host:</strong> " . DB_HOST . "</p>";
    echo "<p><strong>Kullanıcı:</strong> " . DB_USER . "</p>";
    
    // Tabloları kontrol et
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Mevcut Tablolar:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Kullanıcı sayısını kontrol et
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "<p><strong>Toplam Kullanıcı:</strong> " . $result['count'] . "</p>";
    
    // Faaliyet türü sayısını kontrol et
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM publication_types");
    $result = $stmt->fetch();
    echo "<p><strong>Toplam Faaliyet Türü:</strong> " . $result['count'] . "</p>";
    
} catch (Exception $e) {
    echo "<h2>❌ Bağlantı Hatası!</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>