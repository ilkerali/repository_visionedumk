<?php
/**
 * My Publications Page
 * 
 * Faculty members can view, search, filter and manage their publications
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');

// Check faculty authorization
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'faculty') {
    header("Location: ../dashboard.php");
    exit();
}

$pdo = getDBConnection();
$userId = $_SESSION['user_id'];

// Success/Error messages
$message = '';
$messageType = '';

// Handle success messages from other pages
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'added') {
        $message = 'Publication added successfully!';
        $messageType = 'success';
    } elseif ($_GET['success'] === 'updated') {
        $message = 'Publication updated successfully!';
        $messageType = 'success';
    } elseif ($_GET['success'] === 'deleted') {
        $message = 'Publication deleted successfully!';
        $messageType = 'success';
    }
}

// Handle publication deletion
if (isset($_GET['delete']) && isset($_GET['confirm'])) {
    $publicationId = intval($_GET['delete']);
    
    try {
        // Check if publication belongs to user
        $checkStmt = $pdo->prepare("SELECT user_id FROM publications WHERE publication_id = :id");
        $checkStmt->execute([':id' => $publicationId]);
        $pub = $checkStmt->fetch();
        
        if ($pub && $pub['user_id'] == $userId) {
            $deleteStmt = $pdo->prepare("DELETE FROM publications WHERE publication_id = :id AND user_id = :user_id");
            $deleteStmt->execute([':id' => $publicationId, ':user_id' => $userId]);
            
            // Log activity
            logActivity($pdo, $userId, 'publication_deleted', 'publications', $publicationId);
            
            header("Location: my_publications.php?success=deleted");
            exit();
        } else {
            $message = 'You do not have permission to delete this publication.';
            $messageType = 'error';
        }
    } catch (PDOException $e) {
        error_log("Delete publication error: " . $e->getMessage());
        $message = 'An error occurred while deleting the publication.';
        $messageType = 'error';
    }
}

// Search and filter parameters
$searchTerm = cleanInput($_GET['search'] ?? '');
$filterType = intval($_GET['type'] ?? 0);
$filterYear = intval($_GET['year'] ?? 0);
$filterStatus = cleanInput($_GET['status'] ?? '');
$sortBy = cleanInput($_GET['sort'] ?? 'created_at');
$sortOrder = cleanInput($_GET['order'] ?? 'DESC');

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = ['p.user_id = :user_id'];
$params = [':user_id' => $userId];

if (!empty($searchTerm)) {
    $whereConditions[] = "(p.title LIKE :search OR p.authors LIKE :search OR p.keywords LIKE :search)";
    $params[':search'] = '%' . $searchTerm . '%';
}

if ($filterType > 0) {
    $whereConditions[] = "p.type_id = :type_id";
    $params[':type_id'] = $filterType;
}

if ($filterYear > 0) {
    $whereConditions[] = "p.publication_year = :year";
    $params[':year'] = $filterYear;
}

if (!empty($filterStatus)) {
    $whereConditions[] = "p.status = :status";
    $params[':status'] = $filterStatus;
}

$whereClause = implode(' AND ', $whereConditions);

// Validate sort column
$allowedSorts = ['title', 'publication_year', 'created_at', 'citation_count', 'status'];
if (!in_array($sortBy, $allowedSorts)) {
    $sortBy = 'created_at';
}

// Validate sort order
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

try {
    // Get total count for pagination
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM publications p 
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalPublications = $countStmt->fetch()['total'];
    $totalPages = ceil($totalPublications / $perPage);
    
    // Get publications
    $stmt = $pdo->prepare("
        SELECT p.*, pt.type_name_en, pt.type_code
        FROM publications p
        JOIN publication_types pt ON p.type_id = pt.type_id
        WHERE $whereClause
        ORDER BY p.$sortBy $sortOrder
        LIMIT :limit OFFSET :offset
    ");
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $publications = $stmt->fetchAll();
    
    // Get publication types for filter
    $typesStmt = $pdo->query("SELECT * FROM publication_types WHERE is_active = TRUE ORDER BY type_name_en");
    $publicationTypes = $typesStmt->fetchAll();
    
    // Get unique years for filter
    $yearsStmt = $pdo->prepare("
        SELECT DISTINCT publication_year 
        FROM publications 
        WHERE user_id = :user_id 
        ORDER BY publication_year DESC
    ");
    $yearsStmt->execute([':user_id' => $userId]);
    $years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get statistics
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(citation_count) as total_citations
        FROM publications
        WHERE user_id = :user_id
    ");
    $statsStmt->execute([':user_id' => $userId]);
    $stats = $statsStmt->fetch();
    
} catch (PDOException $e) {
    error_log("My publications error: " . $e->getMessage());
    $publications = [];
    $totalPages = 1;
    $stats = ['total' => 0, 'published' => 0, 'under_review' => 0, 'total_citations' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Publications - International Vision University</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <div class="navbar-brand">
                <img src="../assets/images/logo.png" alt="Logo" class="navbar-logo" onerror="this.style.display='none'">
                <span class="navbar-title">Academic Publication Repository</span>
            </div>
            
            <div class="navbar-menu">
                <a href="dashboard.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    </svg>
                    Dashboard
                </a>
                <a href="add_publication.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                    New Publication
                </a>
                <a href="my_publications.php" class="nav-link active">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    My Publications
                </a>
                <a href="profile.php" class="nav-link">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Profile
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
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>My Publications</h1>
                    <p class="text-muted">Manage and track your academic publications</p>
                </div>
                <a href="add_publication.php" class="btn btn-primary">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                    Add New Publication
                </a>
            </div>

            <!-- Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <?php if ($messageType === 'success'): ?>
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        <?php else: ?>
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="8" x2="12" y2="12"></line>
                            <line x1="12" y1="16" x2="12.01" y2="16"></line>
                        <?php endif; ?>
                    </svg>
                    <?php echo sanitize($message); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Bar -->
            <div class="stats-grid stats-grid-4">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total']; ?></h3>
                        <p>Total Publications</p>
                    </div>
                </div>
                
                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['published']; ?></h3>
                        <p>Published</p>
                    </div>
                </div>
                
                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['under_review']; ?></h3>
                        <p>Under Review</p>
                    </div>
                </div>
                
                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $stats['total_citations'] ?? 0; ?></h3>
                        <p>Total Citations</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="card">
                <div class="card-header">
                    <h2>Search & Filter</h2>
                </div>
                <div class="card-body">
                    <form method="GET" action="my_publications.php">
                        <div class="form-row">
                            <div class="form-group" style="grid-column: 1 / -1;">
                                <div style="display: flex; gap: var(--spacing-md);">
                                    <input type="text" 
                                           name="search" 
                                           placeholder="Search by title, authors, or keywords..." 
                                           value="<?php echo sanitize($searchTerm); ?>"
                                           style="flex: 1;">
                                    <button type="submit" class="btn btn-primary">
                                        <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <path d="m21 21-4.35-4.35"></path>
                                        </svg>
                                        Search
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row form-row-3">
                            <div class="form-group">
                                <select name="type">
                                    <option value="">All Types</option>
                                    <?php foreach ($publicationTypes as $type): ?>
                                        <option value="<?php echo $type['type_id']; ?>" 
                                                <?php echo $filterType == $type['type_id'] ? 'selected' : ''; ?>>
                                            <?php echo sanitize($type['type_name_en']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <select name="year">
                                    <option value="">All Years</option>
                                    <?php foreach ($years as $year): ?>
                                        <option value="<?php echo $year; ?>" 
                                                <?php echo $filterYear == $year ? 'selected' : ''; ?>>
                                            <?php echo $year; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <select name="status">
                                    <option value="">All Status</option>
                                    <option value="published" <?php echo $filterStatus === 'published' ? 'selected' : ''; ?>>Published</option>
                                    <option value="under_review" <?php echo $filterStatus === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                    <option value="draft" <?php echo $filterStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                    <option value="rejected" <?php echo $filterStatus === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row form-row-3">
                            <div class="form-group">
                                <select name="sort">
                                    <option value="created_at" <?php echo $sortBy === 'created_at' ? 'selected' : ''; ?>>Date Added</option>
                                    <option value="publication_year" <?php echo $sortBy === 'publication_year' ? 'selected' : ''; ?>>Publication Year</option>
                                    <option value="title" <?php echo $sortBy === 'title' ? 'selected' : ''; ?>>Title</option>
                                    <option value="citation_count" <?php echo $sortBy === 'citation_count' ? 'selected' : ''; ?>>Citations</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <select name="order">
                                    <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                                    <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <a href="my_publications.php" class="btn btn-secondary btn-block">Clear Filters</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Publications List -->
            <?php if (!empty($publications)): ?>
                <div class="publications-list">
                    <?php foreach ($publications as $pub): ?>
                        <div class="publication-card">
                            <div class="publication-type-badge type-badge-sm badge-<?php echo strtolower($pub['type_code']); ?>">
                                <?php echo sanitize($pub['type_name_en']); ?>
                            </div>
                            <?php if ($pub['status'] !== 'published'): ?>
                                <span class="status-badge status-<?php echo $pub['status']; ?>" style="float: right;">
                                    <?php echo ucfirst(str_replace('_', ' ', $pub['status'])); ?>
                                </span>
                            <?php endif; ?>
                            
                            <h3 class="publication-title"><?php echo sanitize($pub['title']); ?></h3>
                            
                            <div class="publication-meta">
                                <span class="meta-item">
                                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    <?php echo sanitize($pub['authors']); ?>
                                </span>
                                <span class="meta-item">
                                    <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="16" y1="2" x2="16" y2="6"></line>
                                        <line x1="8" y1="2" x2="8" y2="6"></line>
                                    </svg>
                                    <?php echo $pub['publication_year']; ?>
                                </span>
                                <?php if (!empty($pub['journal_name'])): ?>
                                    <span class="meta-item">
                                        <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                                        </svg>
                                        <?php echo sanitize($pub['journal_name']); ?>
                                    </span>
                                <?php endif; ?>
                                <?php if ($pub['citation_count'] > 0): ?>
                                    <span class="meta-item">
                                        <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                                        </svg>
                                        <?php echo $pub['citation_count']; ?> citations
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($pub['abstract'])): ?>
                                <p class="text-muted" style="margin-bottom: var(--spacing-md);">
                                    <?php 
                                    $abstract = strip_tags($pub['abstract']);
                                    echo sanitize(substr($abstract, 0, 200)) . (strlen($abstract) > 200 ? '...' : ''); 
                                    ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="publication-actions">
                                <a href="view_publication.php?id=<?php echo $pub['publication_id']; ?>" 
                                   class="btn btn-sm btn-secondary">View</a>
                                <a href="edit_publication.php?id=<?php echo $pub['publication_id']; ?>" 
                                   class="btn btn-sm btn-primary">Edit</a>
                                <a href="my_publications.php?delete=<?php echo $pub['publication_id']; ?>" 
                                   class="btn btn-sm btn-danger"
                                   data-confirm-delete="Are you sure you want to delete this publication?">Delete</a>
                                <?php if (!empty($pub['doi'])): ?>
                                    <a href="https://doi.org/<?php echo sanitize($pub['doi']); ?>" 
                                       target="_blank"
                                       class="btn btn-sm btn-secondary">DOI</a>
                                <?php endif; ?>
                                <?php if (!empty($pub['url'])): ?>
                                    <a href="<?php echo sanitize($pub['url']); ?>" 
                                       target="_blank"
                                       class="btn btn-sm btn-secondary">Link</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($searchTerm); ?>&type=<?php echo $filterType; ?>&year=<?php echo $filterYear; ?>&status=<?php echo $filterStatus; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?>">
                                Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="active"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($searchTerm); ?>&type=<?php echo $filterType; ?>&year=<?php echo $filterYear; ?>&status=<?php echo $filterStatus; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($searchTerm); ?>&type=<?php echo $filterType; ?>&year=<?php echo $filterYear; ?>&status=<?php echo $filterStatus; ?>&sort=<?php echo $sortBy; ?>&order=<?php echo $sortOrder; ?>">
                                Next
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <svg class="empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="18" x2="12" y2="12"></line>
                        <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                    <?php if (!empty($searchTerm) || $filterType > 0 || $filterYear > 0 || !empty($filterStatus)): ?>
                        <h3>No publications found</h3>
                        <p>No publications match your search criteria. Try adjusting your filters.</p>
                        <a href="my_publications.php" class="btn btn-primary">Clear Filters</a>
                    <?php else: ?>
                        <h3>You haven't added any publications yet</h3>
                        <p>Start building your academic portfolio by adding your first publication.</p>
                        <a href="add_publication.php" class="btn btn-primary">Add Your First Publication</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="../assets/js/main.js"></script>
</body>
</html>