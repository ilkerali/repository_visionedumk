<?php
/**
 * Repository Homepage - Public Access
 * 
 * Main landing page for International Vision University Academic Repository
 */

require_once 'config/database.php';
require_once 'includes/functions.php';

// Start session for logged-in users (optional)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getDBConnection();

// Get search and filter parameters
$searchQuery = isset($_GET['search']) ? cleanInput($_GET['search']) : '';
$departmentFilter = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$typeFilter = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build SQL query
$sql = "
    SELECT 
        p.publication_id,
        p.title,
        p.authors,
        p.publication_year,
        p.journal_name,
        p.conference_name,
        p.doi,
        p.url,
        p.created_at,
        pt.type_name_en,
        pt.type_code,
        u.full_name as faculty_name,
        COALESCE(d.department_name_en, 'Unknown') as department_name
    FROM publications p
    LEFT JOIN publication_types pt ON p.type_id = pt.type_id
    LEFT JOIN users u ON p.user_id = u.user_id
    LEFT JOIN departments d ON u.department_id = d.department_id
    WHERE 1=1
";

$params = [];

// Search filter
if (!empty($searchQuery)) {
    $sql .= " AND (
        p.title LIKE :search1 
        OR p.authors LIKE :search2 
        OR u.full_name LIKE :search3
        OR p.keywords LIKE :search4
    )";
    $searchTerm = '%' . $searchQuery . '%';
    $params[':search1'] = $searchTerm;
    $params[':search2'] = $searchTerm;
    $params[':search3'] = $searchTerm;
    $params[':search4'] = $searchTerm;
}

// Department filter
if ($departmentFilter > 0) {
    $sql .= " AND u.department_id = :department_id";
    $params[':department_id'] = $departmentFilter;
}

// Type filter
if ($typeFilter > 0) {
    $sql .= " AND p.type_id = :type_id";
    $params[':type_id'] = $typeFilter;
}

// Year filter
if ($yearFilter > 0) {
    $sql .= " AND p.publication_year = :year";
    $params[':year'] = $yearFilter;
}

// Count total for pagination
$countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as count_query";
$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($params);
$totalResults = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalResults / $perPage);

// Add ordering and pagination
$sql .= " ORDER BY p.publication_year DESC, p.created_at DESC, p.title ASC";
$sql .= " LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$publications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter dropdown
$stmtDepts = $pdo->query("
    SELECT department_id, department_name_en 
    FROM departments 
    WHERE is_active = 1 
    ORDER BY display_order, department_name_en
");
$departments = $stmtDepts->fetchAll(PDO::FETCH_ASSOC);

// Get publication types for filter
$stmtTypes = $pdo->query("
    SELECT type_id, type_name_en, type_code 
    FROM publication_types 
    WHERE is_active = 1 
    ORDER BY type_name_en
");
$publicationTypes = $stmtTypes->fetchAll(PDO::FETCH_ASSOC);

// Get available years
$stmtYears = $pdo->query("
    SELECT DISTINCT publication_year 
    FROM publications 
    WHERE publication_year IS NOT NULL 
    ORDER BY publication_year DESC
");
$years = $stmtYears->fetchAll(PDO::FETCH_COLUMN);

// Get statistics for hero section
$stmtStats = $pdo->query("
    SELECT 
        COUNT(DISTINCT p.publication_id) as total_publications,
        COUNT(DISTINCT p.user_id) as total_authors,
        COUNT(DISTINCT pt.type_id) as total_types,
        MAX(p.publication_year) as latest_year
    FROM publications p
    LEFT JOIN publication_types pt ON p.type_id = pt.type_id
");
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Repository - International Vision University</title>
    <meta name="description" content="Browse academic publications from International Vision University faculty members">
    <meta name="keywords" content="academic repository, research, publications, International Vision University">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-color: #2563EB;
            --primary-dark: #1E40AF;
            --primary-light: #3B82F6;
            --success-color: #10B981;
            --warning-color: #F59E0B;
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: var(--gray-900);
            background: #fff;
        }
        
        /* Header & Navigation */
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo {
            height: 50px;
            width: auto;
        }
        
        .site-title {
            font-size: 1.4rem;
            font-weight: 700;
            line-height: 1.2;
        }
        
        .site-subtitle {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .login-btn {
            background: white;
            color: var(--primary-color);
            padding: 10px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .login-btn:hover {
            background: var(--gray-100);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
            padding: 60px 20px;
            text-align: center;
        }
        
        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .hero h1 {
            font-size: 2.5rem;
            color: var(--gray-900);
            margin-bottom: 15px;
            font-weight: 800;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            color: var(--gray-700);
            margin-bottom: 40px;
        }
        
        /* Statistics Bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            max-width: 900px;
            margin: 40px auto 0;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07);
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            display: block;
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: 0.95rem;
            margin-top: 5px;
        }
        
        /* Search & Filter Section */
        .search-section {
            background: white;
            padding: 40px 20px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .search-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .search-box {
            margin-bottom: 25px;
        }
        
        .search-input-wrapper {
            position: relative;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .search-input {
            width: 100%;
            padding: 16px 50px 16px 20px;
            font-size: 1.1rem;
            border: 2px solid var(--gray-300);
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .search-icon {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
        }
        
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gray-700);
            font-size: 0.9rem;
        }
        
        .filter-select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            font-size: 1rem;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 1rem;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }
        
        .btn-secondary:hover {
            background: var(--gray-300);
        }
        
        /* Results Section */
        .results-section {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .results-count {
            font-size: 1.2rem;
            color: var(--gray-700);
        }
        
        .results-count strong {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        /* Publication Card */
        .publications-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .publication-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 25px;
            transition: all 0.3s;
            position: relative;
        }
        
        .publication-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
            transform: translateY(-2px);
            border-color: var(--primary-color);
        }
        
        .publication-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .publication-type-badge {
            background: var(--primary-color);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .publication-year {
            background: var(--gray-100);
            color: var(--gray-700);
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .publication-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 12px;
            line-height: 1.4;
        }
        
        .publication-title a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .publication-title a:hover {
            color: var(--primary-color);
        }
        
        .publication-authors {
            color: var(--gray-700);
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .publication-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--gray-200);
            font-size: 0.9rem;
            color: var(--gray-600);
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .meta-item svg {
            width: 16px;
            height: 16px;
            opacity: 0.7;
        }
        
        .publication-links {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .pub-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: var(--gray-100);
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .pub-link:hover {
            background: var(--primary-color);
            color: white;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 50px;
            padding-top: 30px;
            border-top: 2px solid var(--gray-200);
        }
        
        .page-btn {
            padding: 10px 16px;
            border: 2px solid var(--gray-300);
            background: white;
            color: var(--gray-700);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .page-btn:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .page-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* No Results */
        .no-results {
            text-align: center;
            padding: 80px 20px;
        }
        
        .no-results svg {
            width: 120px;
            height: 120px;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .no-results h3 {
            font-size: 1.5rem;
            color: var(--gray-700);
            margin-bottom: 10px;
        }
        
        .no-results p {
            color: var(--gray-600);
            font-size: 1.1rem;
        }
        
        /* Footer */
        .footer {
            background: var(--gray-900);
            color: var(--gray-400);
            padding: 40px 20px;
            margin-top: 60px;
            text-align: center;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        .footer a {
            color: var(--primary-light);
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }
            
            .stats-bar {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters {
                grid-template-columns: 1fr;
            }
            
            .publication-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .results-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<header class="header">
    <div class="header-container">
        <div class="logo-section">
            <img src="assets/images/vision_logo_k.png" alt="IVU Logo" class="logo">
            <div>
                <div class="site-title">Academic Repository</div>
                <div class="site-subtitle">International Vision University</div>
            </div>
        </div>
        
        <a href="login.php" class="login-btn">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                <polyline points="10 17 15 12 10 7"></polyline>
                <line x1="15" y1="12" x2="3" y2="12"></line>
            </svg>
            Faculty Login
        </a>
    </div>
</header>

<!-- Hero Section -->
<section class="hero">
    <div class="hero-content">
        <h1>Explore Academic Research</h1>
        <p class="hero-subtitle">
            Browse publications from our distinguished faculty members
        </p>
        
        <!-- Statistics -->
        <div class="stats-bar">
            <div class="stat-card">
                <span class="stat-number"><?php echo number_format($stats['total_publications']); ?></span>
                <span class="stat-label">Total Publications</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['total_authors']; ?></span>
                <span class="stat-label">Faculty Authors</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['total_types']; ?></span>
                <span class="stat-label">Publication Types</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $stats['latest_year']; ?></span>
                <span class="stat-label">Latest Year</span>
            </div>
        </div>
    </div>
</section>

<!-- Search & Filter Section -->
<section class="search-section">
    <div class="search-container">
        <form method="GET" action="index.php">
            <!-- Search Box -->
            <div class="search-box">
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        name="search" 
                        class="search-input"
                        placeholder="Search by title, author, or keywords..."
                        value="<?php echo htmlspecialchars($searchQuery); ?>"
                    >
                    <svg class="search-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="M21 21l-4.35-4.35"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters">
                <div class="filter-group">
                    <label for="department">Department</label>
                    <select name="department" id="department" class="filter-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo (int)$dept['department_id']; ?>" 
                                    <?php echo $departmentFilter == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name_en'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="type">Publication Type</label>
                    <select name="type" id="type" class="filter-select">
                        <option value="">All Types</option>
                        <?php foreach ($publicationTypes as $type): ?>
                            <option value="<?php echo (int)$type['type_id']; ?>" 
                                    <?php echo $typeFilter == $type['type_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['type_name_en'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="year">Year</label>
                    <select name="year" id="year" class="filter-select">
                        <option value="">All Years</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo (int)$year; ?>" 
                                    <?php echo $yearFilter == $year ? 'selected' : ''; ?>>
                                <?php echo (int)$year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                </div>
            </div>
        </form>
    </div>
</section>

<!-- Results Section -->
<section class="results-section">
    <div class="results-header">
        <div class="results-count">
            Showing <strong><?php echo number_format($totalResults); ?></strong> 
            publication<?php echo $totalResults != 1 ? 's' : ''; ?>
            <?php if (!empty($searchQuery)): ?>
                for "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (empty($publications)): ?>
        <!-- No Results -->
        <div class="no-results">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="M21 21l-4.35-4.35"></path>
                <line x1="11" y1="8" x2="11" y2="14"></line>
                <line x1="8" y1="11" x2="14" y2="11"></line>
            </svg>
            <h3>No publications found</h3>
            <p>Try adjusting your search or filter criteria</p>
        </div>
    <?php else: ?>
        <!-- Publications List -->
        <div class="publications-list">
            <?php foreach ($publications as $pub): ?>
            <article class="publication-card">
                <div class="publication-header">
                    <span class="publication-type-badge">
                        <?php echo htmlspecialchars($pub['type_code'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                    <span class="publication-year">
                        <?php echo (int)$pub['publication_year']; ?>
                    </span>
                </div>
                
                <h2 class="publication-title">
                    <a href="publication.php?id=<?php echo (int)$pub['publication_id']; ?>">
                        <?php echo htmlspecialchars($pub['title'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </h2>
                
                <div class="publication-authors">
                    <strong>Authors:</strong> <?php echo htmlspecialchars($pub['authors'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
                
                <div class="publication-meta">
                    <div class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span><?php echo htmlspecialchars($pub['faculty_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    
                    <div class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        <span><?php echo htmlspecialchars($pub['department_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    
                    <?php if (!empty($pub['journal_name'])): ?>
                    <div class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                        </svg>
                        <span><?php echo htmlspecialchars($pub['journal_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($pub['conference_name'])): ?>
                    <div class="meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span><?php echo htmlspecialchars($pub['conference_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($pub['doi']) || !empty($pub['url'])): ?>
                <div class="publication-links">
                    <?php if (!empty($pub['doi'])): ?>
                    <a href="https://doi.org/<?php echo htmlspecialchars($pub['doi'], ENT_QUOTES, 'UTF-8'); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="pub-link">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                            <polyline points="15 3 21 3 21 9"></polyline>
                            <line x1="10" y1="14" x2="21" y2="3"></line>
                        </svg>
                        DOI
                    </a>
                    <?php endif; ?>
                    
                    <?php if (!empty($pub['url'])): ?>
                    <a href="<?php echo htmlspecialchars($pub['url'], ENT_QUOTES, 'UTF-8'); ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="pub-link">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                            <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                        </svg>
                        Full Text
                    </a>
                    <?php endif; ?>
                    
                    <a href="publication.php?id=<?php echo (int)$pub['publication_id']; ?>" class="pub-link">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        View Details
                    </a>
                </div>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?><?php echo $departmentFilter ? '&department=' . $departmentFilter : ''; ?><?php echo $typeFilter ? '&type=' . $typeFilter : ''; ?><?php echo $yearFilter ? '&year=' . $yearFilter : ''; ?>" 
                   class="page-btn">← Previous</a>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++):
            ?>
                <a href="?page=<?php echo $i; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?><?php echo $departmentFilter ? '&department=' . $departmentFilter : ''; ?><?php echo $typeFilter ? '&type=' . $typeFilter : ''; ?><?php echo $yearFilter ? '&year=' . $yearFilter : ''; ?>" 
                   class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?><?php echo $departmentFilter ? '&department=' . $departmentFilter : ''; ?><?php echo $typeFilter ? '&type=' . $typeFilter : ''; ?><?php echo $yearFilter ? '&year=' . $yearFilter : ''; ?>" 
                   class="page-btn">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="footer-content">
        <p>&copy; <?php echo date('Y'); ?> International Vision University. All rights reserved.</p>
        <p>
            <a href="https://vision.edu.mk" target="_blank">Visit our website</a> | 
            <a href="mailto:info@vision.edu.mk">Contact Us</a>
        </p>
    </div>
</footer>

</body>
</html>
