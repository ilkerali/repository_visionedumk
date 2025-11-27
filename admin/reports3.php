<?php
/**
 * Admin Reports Page
 * * Detaylı raporlama ve istatistik sayfası
 * Comprehensive reporting and statistics page
 * * Updated: Fixed filtering logic for all charts
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');
requireAdmin('../dashboard.php');

$pdo = getDBConnection();

// Rapor filtreleri (Report filters)
$startYear = isset($_GET['start_year']) ? (int)$_GET['start_year'] : date('Y') - 5;
$endYear = isset($_GET['end_year']) ? (int)$_GET['end_year'] : date('Y');
$departmentFilter = isset($_GET['department']) ? cleanInput($_GET['department']) : '';
$typeFilter = isset($_GET['type']) ? (int)$_GET['type'] : 0;

try {
    // --- FİLTRELEME MANTIĞI (FILTERING LOGIC) ---
    // Tüm sorgular için ortak WHERE koşullarını hazırlıyoruz.
    // We prepare common WHERE conditions for all queries.
    
    $whereConditions = [];
    $params = [];

    // 1. Tarih Filtresi (Date Filter)
    $whereConditions[] = "p.publication_year BETWEEN :start_year AND :end_year";
    $params[':start_year'] = $startYear;
    $params[':end_year'] = $endYear;

    // 2. Departman Filtresi (Department Filter)
    if (!empty($departmentFilter)) {
        $whereConditions[] = "u.department = :department";
        $params[':department'] = $departmentFilter;
    }

    // 3. Yayın Türü Filtresi (Type Filter)
    if ($typeFilter > 0) {
        $whereConditions[] = "p.type_id = :type_id";
        $params[':type_id'] = $typeFilter;
    }

    // WHERE cümlesini oluştur
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    // Filtreleme için gerekli JOIN
    // User tablosu her zaman bağlanmalı çünkü departman bilgisi orada
    $joinUsers = "LEFT JOIN users u ON p.user_id = u.user_id";

    // --- SORGULAR (QUERIES) ---

    // 1. Genel İstatistikler (General Statistics)
    $sqlGeneral = "
        SELECT 
            COUNT(DISTINCT p.publication_id) as total_publications,
            COUNT(DISTINCT p.user_id) as active_authors,
            COUNT(DISTINCT pt.type_id) as publication_types,
            MAX(p.publication_year) as latest_year,
            MIN(p.publication_year) as earliest_year,
            AVG(YEAR(CURDATE()) - p.publication_year) as avg_age
        FROM publications p
        LEFT JOIN publication_types pt ON p.type_id = pt.type_id
        $joinUsers
        $whereClause
    ";
    $stmtGeneral = $pdo->prepare($sqlGeneral);
    $stmtGeneral->execute($params);
    $generalStats = $stmtGeneral->fetch();
    
    // 2. Yıllık Trend (Annual Trend)
    $sqlYearTrend = "
        SELECT 
            p.publication_year,
            COUNT(*) as count,
            COUNT(DISTINCT p.user_id) as unique_authors
        FROM publications p
        $joinUsers
        $whereClause
        GROUP BY p.publication_year
        ORDER BY p.publication_year ASC
    ";
    $stmtYearTrend = $pdo->prepare($sqlYearTrend);
    $stmtYearTrend->execute($params);
    $yearTrend = $stmtYearTrend->fetchAll();
    
    // 3. Yayın Türlerine Göre Dağılım (Distribution by publication type)
    // Yüzde hesabı için toplam filtrelenmiş kayıt sayısını bulmamız lazım
    $sqlTotalFiltered = "SELECT COUNT(*) FROM publications p $joinUsers $whereClause";
    
    $sqlByType = "
        SELECT 
            pt.type_name_en,
            pt.type_code,
            COUNT(p.publication_id) as count,
            ROUND((COUNT(p.publication_id) * 100.0 / ($sqlTotalFiltered)), 2) as percentage
        FROM publication_types pt
        JOIN publications p ON pt.type_id = p.type_id
        $joinUsers
        $whereClause
        GROUP BY pt.type_id, pt.type_name_en, pt.type_code
        HAVING count > 0
        ORDER BY count DESC
    ";
    $stmtByType = $pdo->prepare($sqlByType);
    $stmtByType->execute(array_merge($params, $params)); // Parametreleri subquery için iki kere gönderiyoruz
    $typeDistribution = $stmtByType->fetchAll();
    
    // 4. Departmanlara Göre Dağılım (Distribution by department)
    $sqlByDept = "
        SELECT 
            u.department,
            COUNT(p.publication_id) as publication_count,
            COUNT(DISTINCT p.user_id) as faculty_count,
            ROUND(AVG(YEAR(CURDATE()) - p.publication_year), 1) as avg_publication_age
        FROM users u
        JOIN publications p ON u.user_id = p.user_id
        $whereClause
        GROUP BY u.department
        HAVING publication_count > 0
        ORDER BY publication_count DESC
    ";
    $stmtByDept = $pdo->prepare($sqlByDept);
    $stmtByDept->execute($params);
    $deptDistribution = $stmtByDept->fetchAll();
    
    // 5. En Üretken Yazarlar (Top 10) (Most productive authors)
    $sqlTopAuthors = "
        SELECT 
            u.full_name,
            u.department,
            COUNT(p.publication_id) as publication_count,
            MIN(p.publication_year) as first_publication,
            MAX(p.publication_year) as last_publication,
            GROUP_CONCAT(DISTINCT pt.type_code ORDER BY pt.type_code SEPARATOR ', ') as publication_types
        FROM users u
        JOIN publications p ON u.user_id = p.user_id
        LEFT JOIN publication_types pt ON p.type_id = pt.type_id
        $whereClause
        GROUP BY u.user_id, u.full_name, u.department
        ORDER BY publication_count DESC
        LIMIT 10
    ";
    $stmtTopAuthors = $pdo->prepare($sqlTopAuthors);
    $stmtTopAuthors->execute($params);
    $topAuthors = $stmtTopAuthors->fetchAll();
    
    // 6. Yıllık Büyüme Analizi (Annual growth analysis)
    // Filtrelenen yıllar içindeki büyümeyi gösterir
    $sqlGrowth = "
        SELECT 
            p.publication_year,
            COUNT(*) as yearly_count,
            LAG(COUNT(*)) OVER (ORDER BY p.publication_year) as previous_year_count
        FROM publications p
        $joinUsers
        $whereClause
        GROUP BY p.publication_year
        ORDER BY p.publication_year DESC
    ";
    $stmtGrowth = $pdo->prepare($sqlGrowth);
    $stmtGrowth->execute($params);
    $growthData = $stmtGrowth->fetchAll();
    
    // 7. Son 12 Aydaki Aktivite (Last 12 months activity)
    // Not: Bu grafik genellikle "Eklenme Tarihine" göre çalışır. 
    // Ana "Yayın Yılı" filtresi burayı bozmamalı, ancak Departman ve Tür filtreleri uygulanmalı.
    
    $activityConditions = ["p.created_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)"];
    $activityParams = [];
    
    if (!empty($departmentFilter)) {
        $activityConditions[] = "u.department = :dept";
        $activityParams[':dept'] = $departmentFilter;
    }
    if ($typeFilter > 0) {
        $activityConditions[] = "p.type_id = :type";
        $activityParams[':type'] = $typeFilter;
    }
    
    $activityWhere = "WHERE " . implode(" AND ", $activityConditions);

    $sqlMonthly = "
        SELECT 
            DATE_FORMAT(p.created_at, '%Y-%m') as month,
            DATE_FORMAT(p.created_at, '%M %Y') as month_name,
            COUNT(*) as count
        FROM publications p
        $joinUsers
        $activityWhere
        GROUP BY DATE_FORMAT(p.created_at, '%Y-%m')
        ORDER BY month DESC
    ";
    $stmtMonthly = $pdo->prepare($sqlMonthly);
    $stmtMonthly->execute($activityParams);
    $monthlyActivity = $stmtMonthly->fetchAll();
    
    // 8. Dergi/Konferans İstatistikleri (Journal/Conference statistics)
    $sqlJournals = "
        SELECT 
            p.journal as name,
            COUNT(*) as count
        FROM publications p
        $joinUsers
        $whereClause
        AND p.journal IS NOT NULL AND p.journal != ''
        GROUP BY p.journal
        ORDER BY count DESC
        LIMIT 10
    ";
    $stmtJournals = $pdo->prepare($sqlJournals);
    $stmtJournals->execute($params);
    $topJournals = $stmtJournals->fetchAll();
    
    $sqlConferences = "
        SELECT 
            p.conference as name,
            COUNT(*) as count
        FROM publications p
        $joinUsers
        $whereClause
        AND p.conference IS NOT NULL AND p.conference != ''
        GROUP BY p.conference
        ORDER BY count DESC
        LIMIT 10
    ";
    $stmtConferences = $pdo->prepare($sqlConferences);
    $stmtConferences->execute($params);
    $topConferences = $stmtConferences->fetchAll();
    
    // Filtre seçenekleri için veriler (Dropdown data)
    $stmtDepts = $pdo->query("
        SELECT DISTINCT department 
        FROM users 
        WHERE department IS NOT NULL AND department != ''
        ORDER BY department
    ");
    $departments = $stmtDepts->fetchAll();
    
    $stmtTypes = $pdo->query("
        SELECT type_id, type_name_en 
        FROM publication_types 
        WHERE is_active = TRUE
        ORDER BY type_name_en
    ");
    $publicationTypes = $stmtTypes->fetchAll();
    
} catch (PDOException $e) {
    error_log("Reports fetch error: " . $e->getMessage());
    $generalStats = ['total_publications' => 0, 'active_authors' => 0, 'publication_types' => 0, 
                     'latest_year' => 0, 'earliest_year' => 0, 'avg_age' => 0];
    $yearTrend = [];
    $typeDistribution = [];
    $deptDistribution = [];
    $topAuthors = [];
    $growthData = [];
    $monthlyActivity = [];
    $topJournals = [];
    $topConferences = [];
    $departments = [];
    $publicationTypes = [];
}

// Sayfa başlığı (Page title)
$page_title = "Reports & Statistics";
include 'admin_header.php';
?>

<div class="page-header">
    <div>
        <h1>Reports & Statistics</h1>
        <p class="text-muted">Comprehensive analytics and reporting dashboard</p>
    </div>
    <button onclick="window.print()" class="btn btn-secondary">
        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <polyline points="6 9 6 2 18 2 18 9"></polyline>
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
            <rect x="6" y="14" width="12" height="8"></rect>
        </svg>
        Print Report
    </button>
</div>

<div class="filter-section">
    <form method="GET" action="reports.php">
        <div class="filter-grid">
            <div class="form-group">
                <label for="start_year">Start Year</label>
                <input 
                    type="number" 
                    id="start_year" 
                    name="start_year" 
                    value="<?php echo $startYear; ?>"
                    min="1900"
                    max="<?php echo date('Y'); ?>"
                >
            </div>

            <div class="form-group">
                <label for="end_year">End Year</label>
                <input 
                    type="number" 
                    id="end_year" 
                    name="end_year" 
                    value="<?php echo $endYear; ?>"
                    min="1900"
                    max="<?php echo date('Y'); ?>"
                >
            </div>

            <div class="form-group">
                <label for="department">Department</label>
                <select id="department" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo sanitize($dept['department']); ?>" 
                                <?php echo $departmentFilter === $dept['department'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($dept['department']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="type">Publication Type</label>
                <select id="type" name="type">
                    <option value="">All Types</option>
                    <?php foreach ($publicationTypes as $type): ?>
                        <option value="<?php echo $type['type_id']; ?>" 
                                <?php echo $typeFilter === $type['type_id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($type['type_name_en']); ?>
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
            <a href="reports.php" class="btn btn-secondary">Clear Filters</a>
        </div>
    </form>
</div>

<div class="stats-grid stats-grid-4">
    <div class="stat-card stat-primary">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo number_format($generalStats['total_publications']); ?></h3>
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
            <h3><?php echo $generalStats['active_authors']; ?></h3>
            <p>Active Authors</p>
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
            <h3><?php echo $generalStats['latest_year'] ?? '-'; ?></h3>
            <p>Latest Publication Year</p>
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
            <h3><?php echo number_format((float)$generalStats['avg_age'], 1); ?></h3>
            <p>Avg. Publication Age (years)</p>
        </div>
    </div>
</div>

<?php if (!empty($yearTrend)): ?>
<div class="card">
    <div class="card-header">
        <h2>Annual Publication Trend (<?php echo $startYear; ?> - <?php echo $endYear; ?>)</h2>
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

<div class="dashboard-row">
    <div class="dashboard-col-6">
        <div class="card">
            <div class="card-header">
                <h2>Publication Type Distribution</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($typeDistribution)): ?>
                    <div class="type-distribution">
                        <?php foreach ($typeDistribution as $type): ?>
                        <div class="distribution-item">
                            <div class="distribution-label">
                                <span class="type-badge badge-<?php echo strtolower($type['type_code']); ?>">
                                    <?php echo sanitize($type['type_name_en']); ?>
                                </span>
                                <span class="type-count"><?php echo $type['count']; ?></span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $type['percentage']; ?>%"></div>
                            </div>
                            <span class="type-percentage"><?php echo $type['percentage']; ?>%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="text-align: center;">No data available for current filters</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-col-6">
        <div class="card">
            <div class="card-header">
                <h2>Department Distribution</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($deptDistribution)): ?>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-md);">
                        <?php foreach (array_slice($deptDistribution, 0, 8) as $dept): ?>
                        <div style="padding: var(--spacing-md); background: var(--gray-50); border-radius: var(--radius-md); border-left: 3px solid var(--primary-color);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-xs);">
                                <strong style="font-size: 0.9375rem; color: var(--gray-900);">
                                    <?php echo sanitize($dept['department']); ?>
                                </strong>
                                <span style="font-size: 1.25rem; font-weight: 700; color: var(--primary-color);">
                                    <?php echo $dept['publication_count']; ?>
                                </span>
                            </div>
                            <div style="display: flex; gap: var(--spacing-md); font-size: 0.8125rem; color: var(--gray-600);">
                                <span>👥 <?php echo $dept['faculty_count']; ?> faculty</span>
                                <span>⏱️ Avg. <?php echo $dept['avg_publication_age']; ?> years old</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="text-align: center;">No data available for current filters</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Top 10 Most Productive Authors (Filtered)</h2>
    </div>
    <div class="card-body">
        <?php if (!empty($topAuthors)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Rank</th>
                            <th>Author Name</th>
                            <th>Department</th>
                            <th style="width: 120px;">Publications</th>
                            <th style="width: 150px;">Active Period</th>
                            <th>Publication Types</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topAuthors as $index => $author): ?>
                        <tr>
                            <td>
                                <div class="user-rank rank-<?php echo min($index + 1, 5); ?>" style="width: 35px; height: 35px; font-size: 1rem;">
                                    <?php echo $index + 1; ?>
                                </div>
                            </td>
                            <td><strong><?php echo sanitize($author['full_name']); ?></strong></td>
                            <td><?php echo sanitize($author['department']); ?></td>
                            <td>
                                <strong style="color: var(--primary-color); font-size: 1.125rem;">
                                    <?php echo $author['publication_count']; ?>
                                </strong>
                            </td>
                            <td>
                                <small><?php echo $author['first_publication']; ?> - <?php echo $author['last_publication']; ?></small>
                            </td>
                            <td>
                                <small style="color: var(--gray-600);">
                                    <?php echo sanitize($author['publication_types']); ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-muted" style="text-align: center;">No authors found matching criteria</p>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($monthlyActivity)): ?>
<div class="card">
    <div class="card-header">
        <h2>Publication Activity - Last 12 Months (By Entry Date)</h2>
    </div>
    <div class="card-body">
        <div class="chart-container">
            <?php 
            $maxMonthly = max(array_column($monthlyActivity, 'count'));
            ?>
            <div class="bar-chart">
                <?php foreach (array_reverse($monthlyActivity) as $month): 
                    $height = $maxMonthly > 0 ? ($month['count'] / $maxMonthly) * 100 : 0;
                ?>
                <div class="bar-item">
                    <div class="bar-value"><?php echo $month['count']; ?></div>
                    <div class="bar" style="height: <?php echo $height; ?>%">
                        <div class="bar-fill" style="background: linear-gradient(180deg, var(--success-color), #059669);"></div>
                    </div>
                    <div class="bar-label" style="font-size: 0.75rem;">
                        <?php 
                        $monthParts = explode(' ', $month['month_name']);
                        echo substr($monthParts[0], 0, 3) . ' ' . substr($monthParts[1], 2);
                        ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="dashboard-row">
    <div class="dashboard-col-6">
        <div class="card">
            <div class="card-header">
                <h2>Top 10 Journals</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($topJournals)): ?>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                        <?php foreach ($topJournals as $index => $journal): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-sm); border-bottom: 1px solid var(--gray-200);">
                            <div style="display: flex; align-items: center; gap: var(--spacing-sm); flex: 1;">
                                <span style="font-weight: 700; color: var(--gray-400); min-width: 25px;">
                                    #<?php echo $index + 1; ?>
                                </span>
                                <span style="font-size: 0.875rem; color: var(--gray-700);">
                                    <?php echo sanitize(substr($journal['name'], 0, 60)); ?><?php echo strlen($journal['name']) > 60 ? '...' : ''; ?>
                                </span>
                            </div>
                            <span style="font-weight: 700; font-size: 1rem; color: var(--primary-color);">
                                <?php echo $journal['count']; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="text-align: center;">No journal data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="dashboard-col-6">
        <div class="card">
            <div class="card-header">
                <h2>Top 10 Conferences</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($topConferences)): ?>
                    <div style="display: flex; flex-direction: column; gap: var(--spacing-sm);">
                        <?php foreach ($topConferences as $index => $conference): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--spacing-sm); border-bottom: 1px solid var(--gray-200);">
                            <div style="display: flex; align-items: center; gap: var(--spacing-sm); flex: 1;">
                                <span style="font-weight: 700; color: var(--gray-400); min-width: 25px;">
                                    #<?php echo $index + 1; ?>
                                </span>
                                <span style="font-size: 0.875rem; color: var(--gray-700);">
                                    <?php echo sanitize(substr($conference['name'], 0, 60)); ?><?php echo strlen($conference['name']) > 60 ? '...' : ''; ?>
                                </span>
                            </div>
                            <span style="font-weight: 700; font-size: 1rem; color: var(--success-color);">
                                <?php echo $conference['count']; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted" style="text-align: center;">No conference data available</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($growthData)): ?>
<div class="card">
    <div class="card-header">
        <h2>Year-over-Year Growth Analysis</h2>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Year</th>
                        <th>Publications</th>
                        <th>Previous Year</th>
                        <th>Growth</th>
                        <th>Growth Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($growthData as $growth): 
                        if ($growth['previous_year_count']) {
                            $difference = $growth['yearly_count'] - $growth['previous_year_count'];
                            $growthRate = ($difference / $growth['previous_year_count']) * 100;
                            $isPositive = $difference >= 0;
                        } else {
                            $difference = null;
                            $growthRate = null;
                            $isPositive = null;
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo $growth['publication_year']; ?></strong></td>
                        <td><strong style="color: var(--primary-color);"><?php echo $growth['yearly_count']; ?></strong></td>
                        <td><?php echo $growth['previous_year_count'] ?? '-'; ?></td>
                        <td>
                            <?php if ($difference !== null): ?>
                                <span style="color: <?php echo $isPositive ? 'var(--success-color)' : 'var(--error-color)'; ?>; font-weight: 600;">
                                    <?php echo ($isPositive ? '+' : '') . $difference; ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($growthRate !== null): ?>
                                <span style="color: <?php echo $isPositive ? 'var(--success-color)' : 'var(--error-color)'; ?>; font-weight: 600;">
                                    <?php echo ($isPositive ? '+' : '') . number_format($growthRate, 1); ?>%
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<style media="print">
    .navbar, .page-header button, .filter-section { display: none !important; }
    .card { page-break-inside: avoid; }
    body { background: white; }
    @page { margin: 1cm; }
</style>

<?php include 'admin_footer.php'; ?>