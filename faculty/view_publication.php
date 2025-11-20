<?php
/**
 * View Publication Details
 * * Displays full details of a specific publication based on ID.
 */

// 1. GEREKLİ DOSYALARI DAHİL ET (Sizin sistem yapınıza göre)
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');

// Sadece yetkili kullanıcılar (faculty)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../dashboard.php");
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];
$pubId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$publication = null;
$error = '';

// 2. YAYIN VERİLERİNİ ÇEK
if ($pubId > 0) {
    try {
        // Yayın detaylarını ve tür ismini çekiyoruz
        $stmt = $pdo->prepare("
            SELECT p.*, pt.type_name_en, pt.type_code 
            FROM publications p
            LEFT JOIN publication_types pt ON p.type_id = pt.type_id
            WHERE p.publication_id = :id
        ");
        $stmt->execute([':id' => $pubId]);
        $publication = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$publication) {
            $error = 'Publication not found.';
        } 
        // Opsiyonel: Başkasının yayınına bakıyorsa uyarı verebiliriz veya gizleyebiliriz.
        // Şimdilik sadece veri var mı diye bakıyoruz.
        
    } catch (PDOException $e) {
        error_log("View publication error: " . $e->getMessage());
        $error = 'Database error occurred.';
    }
} else {
    $error = 'Invalid publication ID.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $publication ? sanitize($publication['title']) : 'Error'; ?> - Publication Details</title>
    
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    
    <style>
        /* Sayfaya özel ufak düzenlemeler */
        .detail-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            padding-bottom: var(--spacing-sm);
            border-bottom: 1px solid var(--gray-200);
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: var(--gray-700); }
        .detail-value { color: var(--gray-900); }
        .abstract-box { background: var(--gray-50); padding: var(--spacing-lg); border-radius: var(--radius-md); line-height: 1.6; }
        .section-title { margin-top: var(--spacing-xl); margin-bottom: var(--spacing-md); padding-bottom: var(--spacing-xs); border-bottom: 2px solid var(--gray-200); }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <img src="../assets/images/logo.png" alt="Logo" class="navbar-logo" onerror="this.style.display='none'">
                <span class="navbar-title">Academic Publication Repository</span>
            </div>
            
            <div class="navbar-menu">
                <a href="dashboard.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                    Dashboard
                </a>
                <a href="add_publication.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                    New Publication
                </a>
                <a href="my_publications.php" class="nav-link active">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    My Publications
                </a>
            </div>
            
            <div class="navbar-user">
                <div class="user-info">
                    <span class="user-name"><?php echo sanitize($_SESSION['full_name']); ?></span>
                    <span class="user-role"><?php echo sanitize($_SESSION['department']); ?></span>
                </div>
                <a href="../logout.php" class="btn btn-sm btn-secondary">Logout</a>
            </div>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            
            <div class="page-header">
                <div>
                    <h1>Publication Details</h1>
                    <p class="text-muted">View full information about the selected publication.</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <a href="my_publications.php" class="btn btn-secondary">
                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                        Back to List
                    </a>
                    <?php if ($publication && $publication['user_id'] == $userId): ?>
                        <a href="edit_publication.php?id=<?php echo $pubId; ?>" class="btn btn-primary">
                            <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            Edit
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <?php echo sanitize($error); ?>
                </div>
            <?php else: ?>

                <div class="dashboard-row">
                    <div class="card" style="grid-column: span 2;">
                        <div class="card-body">
                            <div style="margin-bottom: var(--spacing-md);">
                                <span class="type-badge type-badge-sm badge-<?php echo strtolower($publication['type_code']); ?>">
                                    <?php echo sanitize($publication['type_name_en']); ?>
                                </span>
                                <span class="status-badge status-<?php echo $publication['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $publication['status'])); ?>
                                </span>
                            </div>

                            <h2 style="font-size: 1.75rem; margin-bottom: var(--spacing-lg); color: var(--primary-dark);">
                                <?php echo sanitize($publication['title']); ?>
                            </h2>

                            <div class="detail-row">
                                <div class="detail-label">Authors</div>
                                <div class="detail-value"><?php echo sanitize($publication['authors']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Publication Year</div>
                                <div class="detail-value"><?php echo sanitize($publication['publication_year']); ?></div>
                            </div>
                            <?php if (!empty($publication['publication_date'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Full Date</div>
                                    <div class="detail-value"><?php echo formatDate($publication['publication_date']); ?></div>
                                </div>
                            <?php endif; ?>
                            <div class="detail-row">
                                <div class="detail-label">Language</div>
                                <div class="detail-value"><?php echo strtoupper(sanitize($publication['language'])); ?></div>
                            </div>

                            <?php if (!empty($publication['abstract'])): ?>
                                <h3 class="section-title">Abstract</h3>
                                <div class="abstract-box">
                                    <?php echo nl2br(sanitize($publication['abstract'])); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($publication['keywords'])): ?>
                                <div style="margin-top: var(--spacing-lg);">
                                    <strong>Keywords: </strong>
                                    <?php 
                                        $keywords = explode(',', $publication['keywords']);
                                        foreach($keywords as $kw) {
                                            echo '<span style="display:inline-block; background:var(--gray-200); padding:2px 8px; border-radius:12px; font-size:0.85em; margin-right:5px;">'.trim(sanitize($kw)).'</span>';
                                        }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dashboard-row">
                    <div class="card">
                        <div class="card-header">
                            <h2>Specific Details</h2>
                        </div>
                        <div class="card-body">
                            
                            <?php if (!empty($publication['journal_name'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Journal Name</div>
                                    <div class="detail-value"><?php echo sanitize($publication['journal_name']); ?></div>
                                </div>
                                <?php if(!empty($publication['issn'])) : ?><div class="detail-row"><div class="detail-label">ISSN</div><div class="detail-value"><?php echo sanitize($publication['issn']); ?></div></div><?php endif; ?>
                                <?php if(!empty($publication['volume'])) : ?><div class="detail-row"><div class="detail-label">Volume</div><div class="detail-value"><?php echo sanitize($publication['volume']); ?></div></div><?php endif; ?>
                                <?php if(!empty($publication['issue'])) : ?><div class="detail-row"><div class="detail-label">Issue</div><div class="detail-value"><?php echo sanitize($publication['issue']); ?></div></div><?php endif; ?>
                                <?php if(!empty($publication['pages'])) : ?><div class="detail-row"><div class="detail-label">Pages</div><div class="detail-value"><?php echo sanitize($publication['pages']); ?></div></div><?php endif; ?>
                                <?php if(!empty($publication['journal_quartile'])) : ?><div class="detail-row"><div class="detail-label">Quartile</div><div class="detail-value"><?php echo sanitize($publication['journal_quartile']); ?></div></div><?php endif; ?>
                                <?php if(!empty($publication['impact_factor'])) : ?><div class="detail-row"><div class="detail-label">Impact Factor</div><div class="detail-value"><?php echo sanitize($publication['impact_factor']); ?></div></div><?php endif; ?>
                            <?php endif; ?>

                            <?php if (!empty($publication['conference_name'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Conference</div>
                                    <div class="detail-value"><?php echo sanitize($publication['conference_name']); ?></div>
                                </div>
                                <?php if(!empty($publication['conference_location'])) : ?><div class="detail-row"><div class="detail-label">Location</div><div class="detail-value"><?php echo sanitize($publication['conference_location']); ?></div></div><?php endif; ?>
                                <?php if(!empty($publication['presentation_type'])) : ?><div class="detail-row"><div class="detail-label">Type</div><div class="detail-value"><?php echo ucfirst(sanitize($publication['presentation_type'])); ?></div></div><?php endif; ?>
                            <?php endif; ?>

                             <?php if (!empty($publication['publisher'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Publisher</div>
                                    <div class="detail-value"><?php echo sanitize($publication['publisher']); ?></div>
                                </div>
                                <?php if(!empty($publication['isbn'])) : ?><div class="detail-row"><div class="detail-label">ISBN</div><div class="detail-value"><?php echo sanitize($publication['isbn']); ?></div></div><?php endif; ?>
                                <?php if(!empty($publication['book_type'])) : ?><div class="detail-row"><div class="detail-label">Book Type</div><div class="detail-value"><?php echo ucfirst(sanitize($publication['book_type'])); ?></div></div><?php endif; ?>
                            <?php endif; ?>

                            <?php if (!empty($publication['project_role'])): ?>
                                <div class="detail-row">
                                    <div class="detail-label">Role</div>
                                    <div class="detail-value"><?php echo sanitize($publication['project_role']); ?></div>
                                </div>
                                <?php if(!empty($publication['funding_agency'])) : ?><div class="detail-row"><div class="detail-label">Agency</div><div class="detail-value"><?php echo sanitize($publication['funding_agency']); ?></div></div><?php endif; ?>
                                <?php if(!empty($publication['grant_amount'])) : ?><div class="detail-row"><div class="detail-label">Budget</div><div class="detail-value"><?php echo number_format($publication['grant_amount'], 2); ?> ₺</div></div><?php endif; ?>
                            <?php endif; ?>

                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h2>Additional Info</h2>
                        </div>
                        <div class="card-body">
                             <div class="detail-row">
                                <div class="detail-label">Added Date</div>
                                <div class="detail-value"><?php echo formatDate($publication['created_at']); ?></div>
                            </div>
                             <div class="detail-row">
                                <div class="detail-label">Citation Count</div>
                                <div class="detail-value"><?php echo intval($publication['citation_count']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">Access</div>
                                <div class="detail-value"><?php echo ucfirst(sanitize($publication['access_type'] ?? 'Not specified')); ?></div>
                            </div>

                            <hr style="margin: var(--spacing-lg) 0; border: 0; border-top: 1px solid var(--gray-200);">

                            <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                                <?php if (!empty($publication['doi'])): ?>
                                    <a href="https://doi.org/<?php echo sanitize($publication['doi']); ?>" target="_blank" class="btn btn-secondary btn-block">
                                        View DOI (<?php echo sanitize($publication['doi']); ?>)
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($publication['url'])): ?>
                                    <a href="<?php echo sanitize($publication['url']); ?>" target="_blank" class="btn btn-secondary btn-block">
                                        Visit URL
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($publication['file_path'])): ?>
                                    <a href="<?php echo sanitize($publication['file_path']); ?>" target="_blank" class="btn btn-primary btn-block">
                                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                        Download Full Text
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>