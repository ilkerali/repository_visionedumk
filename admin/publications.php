<?php
/**
 * Admin All Publications Page
 * 
 * Tüm yayınları listeleme ve yönetim sayfası
 * List and manage all publications in the system
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');
requireAdmin('../dashboard.php');

$pdo = getDBConnection();
$error = '';
$success = '';

// Yayın silme işlemi (Delete publication)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $publicationId = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM publications WHERE publication_id = :publication_id");
        $stmt->execute([':publication_id' => $publicationId]);
        
        // Log activity
        logActivity($pdo, $_SESSION['user_id'], 'publication_delete', 'publications', $publicationId);
        
        $success = "Publication deleted successfully!";
        
    } catch (PDOException $e) {
        error_log("Publication delete error: " . $e->getMessage());
        $error = "Error deleting publication. Please try again.";
    }
}

// Filtreleme ve arama (Filtering and search)
$searchTerm = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$typeFilter = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$authorFilter = isset($_GET['author']) ? (int)$_GET['author'] : 0;

// Sayfalama (Pagination)
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Yayınları getir (Fetch publications)
try {
    // Toplam kayıt sayısı için sorgu (Query for total count)
    $countSql = "
        SELECT COUNT(*) as total
        FROM publications p
        JOIN publication_types pt ON p.type_id = pt.type_id
        JOIN users u ON p.user_id = u.user_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Arama filtresi (Search filter)
    if (!empty($searchTerm)) {
        $countSql .= " AND (p.title LIKE :search OR p.authors LIKE :search OR p.journal LIKE :search OR p.conference LIKE :search OR p.publisher LIKE :search)";
        $params[':search'] = "%$searchTerm%";
    }
    
    // Tür filtresi (Type filter)
    if ($typeFilter > 0) {
        $countSql .= " AND p.type_id = :type_id";
        $params[':type_id'] = $typeFilter;
    }
    
    // Yıl filtresi (Year filter)
    if ($yearFilter > 0) {
        $countSql .= " AND p.publication_year = :year";
        $params[':year'] = $yearFilter;
    }
    
    // Yazar filtresi (Author filter)
    if ($authorFilter > 0) {
        $countSql .= " AND p.user_id = :user_id";
        $params[':user_id'] = $authorFilter;
    }
    
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $totalRecords = $stmtCount->fetch()['total'];
    $totalPages = ceil($totalRecords / $perPage);
    
    // Ana sorgu (Main query)
    $sql = "
        SELECT p.*, 
               pt.type_name_en, pt.type_code,
               u.full_name as author_name,
               u.department
        FROM publications p
        JOIN publication_types pt ON p.type_id = pt.type_id
        JOIN users u ON p.user_id = u.user_id
        WHERE 1=1
    ";
    
    // Aynı filtreleri uygula (Apply same filters)
    if (!empty($searchTerm)) {
        $sql .= " AND (p.title LIKE :search OR p.authors LIKE :search OR p.journal LIKE :search OR p.conference LIKE :search OR p.publisher LIKE :search)";
    }
    if ($typeFilter > 0) {
        $sql .= " AND p.type_id = :type_id";
    }
    if ($yearFilter > 0) {
        $sql .= " AND p.publication_year = :year";
    }
    if ($authorFilter > 0) {
        $sql .= " AND p.user_id = :user_id";
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    // Parametreleri bağla (Bind parameters)
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $publications = $stmt->fetchAll();
    
    // İstatistikler (Statistics)
    $stmtStats = $pdo->query("
        SELECT 
            COUNT(*) as total_publications,
            COUNT(DISTINCT user_id) as total_authors,
            MAX(publication_year) as latest_year,
            MIN(publication_year) as earliest_year
        FROM publications
    ");
    $stats = $stmtStats->fetch();
    
    // Yayın türlerini getir (Fetch publication types for filter)
    $stmtTypes = $pdo->query("
        SELECT type_id, type_name_en, type_code 
        FROM publication_types 
        ORDER BY type_name_en
    ");
    $publicationTypes = $stmtTypes->fetchAll();
    
    // Yazarları getir (Fetch authors for filter)
    $stmtAuthors = $pdo->query("
        SELECT DISTINCT u.user_id, u.full_name 
        FROM users u
        INNER JOIN publications p ON u.user_id = p.user_id
        ORDER BY u.full_name
    ");
    $authors = $stmtAuthors->fetchAll();
    
    // Yılları getir (Fetch years for filter)
    $stmtYears = $pdo->query("
        SELECT DISTINCT publication_year 
        FROM publications 
        WHERE publication_year IS NOT NULL
        ORDER BY publication_year DESC
    ");
    $years = $stmtYears->fetchAll();
    
} catch (PDOException $e) {
    error_log("Publications fetch error: " . $e->getMessage());
    $publications = [];
    $stats = ['total_publications' => 0, 'total_authors' => 0, 'latest_year' => 0, 'earliest_year' => 0];
    $publicationTypes = [];
    $authors = [];
    $years = [];
    $totalRecords = 0;
    $totalPages = 0;
}

// Sayfa başlığı (Page title)
$page_title = "All Publications";
include 'admin_header.php';
?>

<!-- Sayfa Başlığı (Page header) -->
<div class="page-header">
    <div>
        <h1>All Publications</h1>
        <p class="text-muted">Manage and view all publications in the system</p>
    </div>
</div>

<!-- Alert Mesajları (Alert messages) -->
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

<!-- İstatistik Kartları (Statistics cards) -->
<div class="stats-grid stats-grid-4">
    <div class="stat-card stat-primary">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_publications']; ?></h3>
            <p>Total Publications</p>
        </div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['total_authors']; ?></h3>
            <p>Contributing Authors</p>
        </div>
    </div>

    <div class="stat-card stat-info">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['latest_year']; ?></h3>
            <p>Latest Year</p>
        </div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $stats['earliest_year']; ?></h3>
            <p>Earliest Year</p>
        </div>
    </div>
</div>

<!-- Filtreleme Bölümü (Filter section) -->
<div class="filter-section">
    <form method="GET" action="publications.php">
        <div class="filter-grid">
            <div class="form-group">
                <label for="search">Search</label>
                <input 
                    type="text" 
                    id="search" 
                    name="search" 
                    placeholder="Title, authors, journal, conference..."
                    value="<?php echo sanitize($searchTerm); ?>"
                >
            </div>

            <div class="form-group">
                <label for="type">Publication Type</label>
                <select id="type" name="type">
                    <option value="">All Types</option>
                    <?php foreach ($publicationTypes as $type): ?>
                        <option value="<?php echo $type['type_id']; ?>" <?php echo $typeFilter === $type['type_id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($type['type_name_en']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="year">Year</label>
                <select id="year" name="year">
                    <option value="">All Years</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year['publication_year']; ?>" <?php echo $yearFilter === $year['publication_year'] ? 'selected' : ''; ?>>
                            <?php echo $year['publication_year']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="author">Author</label>
                <select id="author" name="author">
                    <option value="">All Authors</option>
                    <?php foreach ($authors as $author): ?>
                        <option value="<?php echo $author['user_id']; ?>" <?php echo $authorFilter === $author['user_id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($author['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div style="display: flex; gap: var(--spacing-sm); margin-top: var(--spacing-md);">
            <button type="submit" class="btn btn-primary">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="M21 21l-4.35-4.35"></path>
                </svg>
                Apply Filters
            </button>
            <a href="publications.php" class="btn btn-secondary">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M3 6h18"></path>
                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                </svg>
                Clear Filters
            </a>
        </div>
    </form>
</div>

<!-- Yayınlar Tablosu (Publications table) -->
<div class="card">
    <div class="card-header">
        <h2>Publications (<?php echo $totalRecords; ?> results - Page <?php echo $page; ?> of <?php echo $totalPages; ?>)</h2>
    </div>
    <div class="card-body">
        <?php if (!empty($publications)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Type</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th style="width: 150px;">Published In</th>
                            <th style="width: 80px;">Year</th>
                            <th style="width: 100px;">Added</th>
                            <th style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($publications as $pub): ?>
                        <tr>
                            <td>
                                <span class="type-badge-sm badge-<?php echo strtolower($pub['type_code']); ?>">
                                    <?php echo sanitize($pub['type_code']); ?>
                                </span>
                            </td>
                            <td class="title-cell">
                                <strong style="display: block; margin-bottom: 0.25rem;">
                                    <?php echo sanitize(substr($pub['title'], 0, 80)); ?><?php echo strlen($pub['title']) > 80 ? '...' : ''; ?>
                                </strong>
                                <?php if ($pub['authors']): ?>
                                    <small style="color: var(--gray-600); font-style: italic;">
                                        <?php echo sanitize(substr($pub['authors'], 0, 60)); ?><?php echo strlen($pub['authors']) > 60 ? '...' : ''; ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column;">
                                    <strong><?php echo sanitize($pub['author_name']); ?></strong>
                                    <small style="color: var(--gray-600);"><?php echo sanitize($pub['department']); ?></small>
                                </div>
                            </td>
                            <td>
                                <?php if ($pub['journal']): ?>
                                    <small title="<?php echo sanitize($pub['journal']); ?>">
                                        <?php echo sanitize(substr($pub['journal'], 0, 20)); ?><?php echo strlen($pub['journal']) > 20 ? '...' : ''; ?>
                                    </small>
                                <?php elseif ($pub['conference']): ?>
                                    <small title="<?php echo sanitize($pub['conference']); ?>">
                                        <?php echo sanitize(substr($pub['conference'], 0, 20)); ?><?php echo strlen($pub['conference']) > 20 ? '...' : ''; ?>
                                    </small>
                                <?php elseif ($pub['publisher']): ?>
                                    <small title="<?php echo sanitize($pub['publisher']); ?>">
                                        <?php echo sanitize(substr($pub['publisher'], 0, 20)); ?><?php echo strlen($pub['publisher']) > 20 ? '...' : ''; ?>
                                    </small>
                                <?php else: ?>
                                    <span style="color: var(--gray-400);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo $pub['publication_year']; ?></strong>
                            </td>
                            <td>
                                <small><?php echo date('M j, Y', strtotime($pub['created_at'])); ?></small>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="view_publication.php?id=<?php echo $pub['publication_id']; ?>" 
                                       class="btn-icon" 
                                       title="View Details">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </a>
                                    
                                    <a href="edit_publication.php?id=<?php echo $pub['publication_id']; ?>" 
                                       class="btn-icon" 
                                       title="Edit Publication">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </a>
                                    
                                    <a href="publications.php?action=delete&id=<?php echo $pub['publication_id']; ?>" 
                                       class="btn-icon" 
                                       title="Delete Publication"
                                       onclick="return confirm('Are you sure you want to delete this publication? This action cannot be undone.');"
                                       style="color: var(--error-color);">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <polyline points="3 6 5 6 21 6"></polyline>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sayfalama (Pagination) -->
            <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: var(--spacing-sm); margin-top: var(--spacing-xl); padding-top: var(--spacing-lg); border-top: 1px solid var(--gray-200);">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $typeFilter > 0 ? '&type=' . $typeFilter : ''; ?><?php echo $yearFilter > 0 ? '&year=' . $yearFilter : ''; ?><?php echo $authorFilter > 0 ? '&author=' . $authorFilter : ''; ?>" 
                       class="btn btn-secondary btn-sm">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                        Previous
                    </a>
                <?php endif; ?>

                <span style="padding: 0 var(--spacing-md); color: var(--gray-600);">
                    Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong>
                </span>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : ''; ?><?php echo $typeFilter > 0 ? '&type=' . $typeFilter : ''; ?><?php echo $yearFilter > 0 ? '&year=' . $yearFilter : ''; ?><?php echo $authorFilter > 0 ? '&author=' . $authorFilter : ''; ?>" 
                       class="btn btn-secondary btn-sm">
                        Next
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-state">
                <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <h3>No Publications Found</h3>
                <p>No publications match your search criteria. Try adjusting your filters.</p>
                <a href="publications.php" class="btn btn-secondary">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'admin_footer.php'; ?>
