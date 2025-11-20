<?php
/**
 * Öğretim Üyesi Dashboard
 * 
 * Hocaların yayınlarını yönetebileceği ana panel
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');

// Sadece faculty yetkisi olanlar girebilir
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../dashboard.php");
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Kullanıcının istatistiklerini al
try {
    // Toplam yayın sayısı
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) as total FROM publications WHERE user_id = :user_id");
    $stmtTotal->execute([':user_id' => $userId]);
    $totalPublications = $stmtTotal->fetch()['total'];
    
    // Yayın türlerine göre dağılım
    $stmtByType = $pdo->prepare("
        SELECT pt.type_name_tr, COUNT(*) as count
        FROM publications p
        JOIN publication_types pt ON p.type_id = pt.type_id
        WHERE p.user_id = :user_id
        GROUP BY pt.type_id, pt.type_name_tr
        ORDER BY count DESC
    ");
    $stmtByType->execute([':user_id' => $userId]);
    $publicationsByType = $stmtByType->fetchAll();
    
    // Yıllara göre dağılım (son 5 yıl)
    $stmtByYear = $pdo->prepare("
        SELECT publication_year, COUNT(*) as count
        FROM publications
        WHERE user_id = :user_id AND publication_year >= YEAR(CURDATE()) - 5
        GROUP BY publication_year
        ORDER BY publication_year DESC
    ");
    $stmtByYear->execute([':user_id' => $userId]);
    $publicationsByYear = $stmtByYear->fetchAll();
    
    // Son eklenen yayınlar (5 adet)
    $stmtRecent = $pdo->prepare("
        SELECT p.*, pt.type_name_tr
        FROM publications p
        JOIN publication_types pt ON p.type_id = pt.type_id
        WHERE p.user_id = :user_id
        ORDER BY p.created_at DESC
        LIMIT 5
    ");
    $stmtRecent->execute([':user_id' => $userId]);
    $recentPublications = $stmtRecent->fetchAll();
    
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $totalPublications = 0;
    $publicationsByType = [];
    $publicationsByYear = [];
    $recentPublications = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akademik Panel - Uluslararası Vizyon Üniversitesi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <img src="../assets/images/logo.png" alt="Logo" class="navbar-logo" onerror="this.style.display='none'">
                <span class="navbar-title">Akademik Yayın Repositori</span>
            </div>
            
            <div class="navbar-menu">
                <a href="dashboard.php" class="nav-link active">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    </svg>
                    Ana Sayfa
                </a>
                <a href="add_publication.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                    Yeni Yayın
                </a>
                <a href="my_publications.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    Yayınlarım
                </a>
                <a href="profile.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Profilim
                </a>
            </div>
            
            <div class="navbar-user">
                <div class="user-info">
                    <span class="user-name"><?php echo sanitize($_SESSION['full_name']); ?></span>
                    <span class="user-role"><?php echo sanitize($_SESSION['department']); ?></span>
                </div>
                <a href="../logout.php" class="btn btn-sm btn-secondary">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Çıkış
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Hoş Geldin Mesajı -->
            <div class="welcome-section">
                <h1>Hoş Geldiniz, <?php echo sanitize(explode(' ', $_SESSION['full_name'])[0]); ?>!</h1>
                <p class="text-muted">Akademik yayınlarınızı buradan yönetebilirsiniz.</p>
            </div>

            <!-- İstatistik Kartları -->
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $totalPublications; ?></h3>
                        <p>Toplam Yayın</p>
                    </div>
                </div>

                <?php if (!empty($publicationsByType)): ?>
                    <?php foreach (array_slice($publicationsByType, 0, 3) as $index => $type): ?>
                        <div class="stat-card stat-<?php echo ['success', 'info', 'warning'][$index % 3]; ?>">
                            <div class="stat-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $type['count']; ?></h3>
                                <p><?php echo sanitize($type['type_name_tr']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Hızlı Aksiyonlar -->
            <div class="quick-actions">
                <h2>Hızlı İşlemler</h2>
                <div class="action-buttons">
                    <a href="add_publication.php?type=article" class="action-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        </svg>
                        <span>Makale Ekle</span>
                    </a>
                    <a href="add_publication.php?type=conference" class="action-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                        </svg>
                        <span>Konferans Ekle</span>
                    </a>
                    <a href="add_publication.php?type=book" class="action-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                        </svg>
                        <span>Kitap Ekle</span>
                    </a>
                    <a href="add_publication.php?type=project" class="action-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                            <polyline points="2 17 12 22 22 17"></polyline>
                            <polyline points="2 12 12 17 22 12"></polyline>
                        </svg>
                        <span>Proje Ekle</span>
                    </a>
                </div>
            </div>

            <!-- Son Yayınlar -->
            <?php if (!empty($recentPublications)): ?>
            <div class="recent-publications">
                <div class="section-header">
                    <h2>Son Eklenen Yayınlar</h2>
                    <a href="my_publications.php" class="btn btn-sm btn-secondary">Tümünü Gör</a>
                </div>
                
                <div class="publications-list">
                    <?php foreach ($recentPublications as $pub): ?>
                    <div class="publication-card">
                        <div class="publication-type-badge badge-<?php echo strtolower($pub['type_name_tr']); ?>">
                            <?php echo sanitize($pub['type_name_tr']); ?>
                        </div>
                        <h3 class="publication-title"><?php echo sanitize($pub['title']); ?></h3>
                        <div class="publication-meta">
                            <span class="meta-item">
                                <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                </svg>
                                <?php echo $pub['publication_year']; ?>
                            </span>
                            <span class="meta-item">
                                <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                                <?php echo formatDate($pub['created_at']); ?>
                            </span>
                        </div>
                        <div class="publication-actions">
                            <a href="view_publication.php?id=<?php echo $pub['publication_id']; ?>" class="btn btn-sm btn-secondary">Görüntüle</a>
                            <a href="edit_publication.php?id=<?php echo $pub['publication_id']; ?>" class="btn btn-sm btn-primary">Düzenle</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                </svg>
                <h3>Henüz yayın eklemediniz</h3>
                <p>Yeni bir yayın eklemek için yukarıdaki "Yeni Yayın" butonuna tıklayın.</p>
                <a href="add_publication.php" class="btn btn-primary">İlk Yayınımı Ekle</a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>