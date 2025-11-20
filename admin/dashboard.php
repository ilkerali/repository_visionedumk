<?php
/**
 * Yönetici Dashboard
 * 
 * Sistem yöneticilerinin tüm yayınları ve kullanıcıları yönetebileceği panel
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');
requireAdmin('../dashboard.php');

$pdo = getDBConnection();

// Sistem geneli istatistikler
try {
    // Toplam kullanıcı sayısı
    $stmtUsers = $pdo->query("SELECT COUNT(*) as total FROM users WHERE is_active = TRUE");
    $totalUsers = $stmtUsers->fetch()['total'];
    
    // Toplam yayın sayısı
    $stmtPublications = $pdo->query("SELECT COUNT(*) as total FROM publications");
    $totalPublications = $stmtPublications->fetch()['total'];
    
    // Bu ayki yeni yayınlar
    $stmtThisMonth = $pdo->query("
        SELECT COUNT(*) as total 
        FROM publications 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
    ");
    $thisMonthPublications = $stmtThisMonth->fetch()['total'];
    
    // Yayın türlerine göre dağılım
    $stmtByType = $pdo->query("
        SELECT pt.type_name_tr, pt.type_code, COUNT(*) as count
        FROM publications p
        JOIN publication_types pt ON p.type_id = pt.type_id
        GROUP BY pt.type_id, pt.type_name_tr, pt.type_code
        ORDER BY count DESC
    ");
    $publicationsByType = $stmtByType->fetchAll();
    
    // En aktif kullanıcılar (Top 5)
    $stmtTopUsers = $pdo->query("
        SELECT u.full_name, u.department, COUNT(p.publication_id) as pub_count
        FROM users u
        LEFT JOIN publications p ON u.user_id = p.user_id
        WHERE u.role = 'faculty'
        GROUP BY u.user_id, u.full_name, u.department
        ORDER BY pub_count DESC
        LIMIT 5
    ");
    $topUsers = $stmtTopUsers->fetchAll();
    
    // Son eklenen yayınlar (tüm kullanıcılar)
    $stmtRecent = $pdo->query("
        SELECT p.*, pt.type_name_tr, u.full_name as author_name
        FROM publications p
        JOIN publication_types pt ON p.type_id = pt.type_id
        JOIN users u ON p.user_id = u.user_id
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $recentPublications = $stmtRecent->fetchAll();
    
    // Yıllık yayın trendi (son 5 yıl)
    $stmtYearTrend = $pdo->query("
        SELECT publication_year, COUNT(*) as count
        FROM publications
        WHERE publication_year >= YEAR(CURDATE()) - 5
        GROUP BY publication_year
        ORDER BY publication_year ASC
    ");
    $yearTrend = $stmtYearTrend->fetchAll();
    
} catch (PDOException $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    $totalUsers = $totalPublications = $thisMonthPublications = 0;
    $publicationsByType = $topUsers = $recentPublications = $yearTrend = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Paneli - Uluslararası Vizyon Üniversitesi</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <!-- Admin Navbar -->
    <nav class="navbar navbar-admin">
        <div class="navbar-container">
            <div class="navbar-brand">
                <img src="../assets/images/logo.png" alt="Logo" class="navbar-logo" onerror="this.style.display='none'">
                <span class="navbar-title">Yönetici Paneli</span>
            </div>
            
            <div class="navbar-menu">
                <a href="dashboard.php" class="nav-link active">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    Dashboard
                </a>
                <a href="users.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    Kullanıcılar
                </a>
                <a href="publications.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    Tüm Yayınlar
                </a>
                <a href="publication_types.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <line x1="8" y1="6" x2="21" y2="6"></line>
                        <line x1="8" y1="12" x2="21" y2="12"></line>
                        <line x1="8" y1="18" x2="21" y2="18"></line>
                        <line x1="3" y1="6" x2="3.01" y2="6"></line>
                        <line x1="3" y1="12" x2="3.01" y2="12"></line>
                        <line x1="3" y1="18" x2="3.01" y2="18"></line>
                    </svg>
                    Yayın Türleri
                </a>
                <a href="reports.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <line x1="18" y1="20" x2="18" y2="10"></line>
                        <line x1="12" y1="20" x2="12" y2="4"></line>
                        <line x1="6" y1="20" x2="6" y2="14"></line>
                    </svg>
                    Raporlar
                </a>
                <a href="settings.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="3"></circle>
                        <path d="M12 1v6m0 6v6m9-9h-6m-6 0H3"></path>
                    </svg>
                    Ayarlar
                </a>
            </div>
            
            <div class="navbar-user">
                <div class="user-info">
                    <span class="user-name"><?php echo sanitize($_SESSION['full_name']); ?></span>
                    <span class="user-role badge-admin">Yönetici</span>
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
    <main class="main-content admin-content">
        <div class="container">
            <!-- Hoş Geldin Mesajı -->
            <div class="welcome-section">
                <h1>Hoş Geldiniz, <?php echo sanitize(explode(' ', $_SESSION['full_name'])[0]); ?>!</h1>
                <p class="text-muted">Sistem yönetim paneline hoş geldiniz. Tüm istatistikler ve yönetim araçları burada.</p>
            </div>

            <!-- Ana İstatistik Kartları -->
            <div class="stats-grid stats-grid-4">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $totalUsers; ?></h3>
                        <p>Toplam Kullanıcı</p>
                    </div>
                    <a href="users.php" class="stat-link">Detayları Gör →</a>
                </div>

                <div class="stat-card stat-success">
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
                    <a href="publications.php" class="stat-link">Detayları Gör →</a>
                </div>

                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $thisMonthPublications; ?></h3>
                        <p>Bu Ayki Yeni Yayınlar</p>
                    </div>
                    <a href="publications.php?month=current" class="stat-link">Detayları Gör →</a>
                </div>

                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo count($publicationsByType); ?></h3>
                        <p>Yayın Türü</p>
                    </div>
                    <a href="publication_types.php" class="stat-link">Detayları Gör →</a>
                </div>
            </div>

            <!-- İki Kolonlu Layout -->
            <div class="dashboard-row">
                <!-- Sol Kolon: Yayın Türleri Dağılımı -->
                <div class="dashboard-col-6">
                    <div class="card">
                        <div class="card-header">
                            <h2>Yayın Türleri Dağılımı</h2>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($publicationsByType)): ?>
                                <div class="type-distribution">
                                    <?php 
                                    $totalCount = array_sum(array_column($publicationsByType, 'count'));
                                    foreach ($publicationsByType as $type): 
                                        $percentage = $totalCount > 0 ? ($type['count'] / $totalCount) * 100 : 0;
                                    ?>
                                    <div class="distribution-item">
                                        <div class="distribution-label">
                                            <span class="type-badge badge-<?php echo strtolower($type['type_code']); ?>">
                                                <?php echo sanitize($type['type_name_tr']); ?>
                                            </span>
                                            <span class="type-count"><?php echo $type['count']; ?></span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                        <span class="type-percentage"><?php echo number_format($percentage, 1); ?>%</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">Henüz yayın bulunmamaktadır.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Sağ Kolon: En Aktif Kullanıcılar -->
                <div class="dashboard-col-6">
                    <div class="card">
                        <div class="card-header">
                            <h2>En Aktif Kullanıcılar</h2>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($topUsers)): ?>
                                <div class="top-users-list">
                                    <?php foreach ($topUsers as $index => $user): ?>
                                    <div class="top-user-item">
                                        <div class="user-rank rank-<?php echo $index + 1; ?>">
                                            <?php echo $index + 1; ?>
                                        </div>
                                        <div class="user-details">
                                            <h4><?php echo sanitize($user['full_name']); ?></h4>
                                            <p class="text-muted"><?php echo sanitize($user['department']); ?></p>
                                        </div>
                                        <div class="user-stat">
                                            <span class="stat-number"><?php echo $user['pub_count']; ?></span>
                                            <span class="stat-label">yayın</span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted text-center">Henüz kullanıcı bulunmamaktadır.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Yıllık Trend Grafiği -->
            <?php if (!empty($yearTrend)): ?>
            <div class="card">
                <div class="card-header">
                    <h2>Yıllık Yayın Trendi</h2>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <?php 
                        $maxCount = max(array_column($yearTrend, 'count'));
                        ?>
                        <div class="bar-chart">
                            <?php foreach ($yearTrend as $year): 
                                $height = $maxCount > 0 ? ($year['count'] / $maxCount) * 100 : 0;
                            ?>
                            <div class="bar-item">
                                <div class="bar-value"><?php echo $year['count']; ?></div>
                                <div class="bar" style="height: <?php echo $height; ?>%">
                                    <div class="bar-fill"></div>
                                </div>
                                <div class="bar-label"><?php echo $year['publication_year']; ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Son Eklenen Yayınlar -->
            <div class="card">
                <div class="card-header">
                    <h2>Son Eklenen Yayınlar</h2>
                    <a href="publications.php" class="btn btn-sm btn-secondary">Tümünü Gör</a>
                </div>
                <div class="card-body">
                    <?php if (!empty($recentPublications)): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Tür</th>
                                        <th>Başlık</th>
                                        <th>Yazar</th>
                                        <th>Yıl</th>
                                        <th>Eklenme Tarihi</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPublications as $pub): ?>
                                    <tr>
                                        <td>
                                            <span class="type-badge-sm badge-<?php echo strtolower($pub['type_name_tr']); ?>">
                                                <?php echo sanitize($pub['type_name_tr']); ?>
                                            </span>
                                        </td>
                                        <td class="title-cell">
                                            <strong><?php echo sanitize(substr($pub['title'], 0, 60)); ?><?php echo strlen($pub['title']) > 60 ? '...' : ''; ?></strong>
                                        </td>
                                        <td><?php echo sanitize($pub['author_name']); ?></td>
                                        <td><?php echo $pub['publication_year']; ?></td>
                                        <td><?php echo formatDate($pub['created_at']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_publication.php?id=<?php echo $pub['publication_id']; ?>" 
                                                   class="btn-icon" title="Görüntüle">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                </a>
                                                <a href="edit_publication.php?id=<?php echo $pub['publication_id']; ?>" 
                                                   class="btn-icon" title="Düzenle">
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                                    </svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p class="text-muted text-center">Henüz yayın bulunmamaktadır.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>