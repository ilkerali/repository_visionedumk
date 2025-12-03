<?php
/**
 * Generate Publication Report
 * 
 * Professional A4 formatted report generation
 * Filters applied: Year range, Department, Publication Type
 */

// Start session FIRST - before any output
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');
requireAdmin('../dashboard.php');

$pdo = getDBConnection();

// Get filter parameters
$startYear = isset($_GET['start_year']) ? (int)$_GET['start_year'] : date('Y') - 5;
$endYear = isset($_GET['end_year']) ? (int)$_GET['end_year'] : date('Y');
$departmentFilter = isset($_GET['department']) ? (int)$_GET['department'] : 0;
$typeFilter = isset($_GET['type']) ? (int)$_GET['type'] : 0;

// Initialize all data arrays
$generalStats = ['total_publications' => 0, 'active_authors' => 0, 'publication_types' => 0, 
                 'latest_year' => 0, 'earliest_year' => 0, 'avg_age' => 0];
$yearTrend = [];
$typeDistribution = [];
$deptDistribution = [];
$topAuthors = [];
$topJournals = [];
$topConferences = [];
$selectedDepartment = null;
$selectedType = null;

try {
    // Get selected department name if filtered
    if ($departmentFilter > 0) {
        $stmtDept = $pdo->prepare("SELECT department_name_en FROM departments WHERE department_id = :dept_id");
        $stmtDept->execute([':dept_id' => $departmentFilter]);
        $selectedDepartment = $stmtDept->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get selected type name if filtered
    if ($typeFilter > 0) {
        $stmtType = $pdo->prepare("SELECT type_name_en FROM publication_types WHERE type_id = :type_id");
        $stmtType->execute([':type_id' => $typeFilter]);
        $selectedType = $stmtType->fetch(PDO::FETCH_ASSOC);
    }
    
    // ===== GENERAL STATISTICS =====
    $sqlGeneral = "
        SELECT 
            COUNT(DISTINCT p.publication_id) as total_publications,
            COUNT(DISTINCT p.user_id) as active_authors,
            COUNT(DISTINCT pt.type_id) as publication_types,
            COALESCE(MAX(p.publication_year), 0) as latest_year,
            COALESCE(MIN(p.publication_year), 0) as earliest_year,
            COALESCE(AVG(YEAR(CURDATE()) - p.publication_year), 0) as avg_age
        FROM publications p
        LEFT JOIN publication_types pt ON p.type_id = pt.type_id
        LEFT JOIN users u ON p.user_id = u.user_id
        WHERE 1=1
    ";
    
    $paramsGeneral = [];
    
    if ($startYear > 0 && $endYear > 0) {
        $sqlGeneral .= " AND p.publication_year BETWEEN :start_year AND :end_year";
        $paramsGeneral[':start_year'] = $startYear;
        $paramsGeneral[':end_year'] = $endYear;
    }
    
    if ($departmentFilter > 0) {
        $sqlGeneral .= " AND u.department_id = :department_id";
        $paramsGeneral[':department_id'] = $departmentFilter;
    }
    
    if ($typeFilter > 0) {
        $sqlGeneral .= " AND p.type_id = :type_id";
        $paramsGeneral[':type_id'] = $typeFilter;
    }
    
    $stmtGeneral = $pdo->prepare($sqlGeneral);
    $stmtGeneral->execute($paramsGeneral);
    $generalStats = $stmtGeneral->fetch(PDO::FETCH_ASSOC);
    
    // ===== ANNUAL TREND =====
    $sqlYearTrend = "
        SELECT 
            p.publication_year,
            COUNT(*) as count,
            COUNT(DISTINCT p.user_id) as unique_authors
        FROM publications p
        LEFT JOIN users u ON p.user_id = u.user_id
        WHERE 1=1
    ";
    
    $paramsYearTrend = [];
    
    if ($startYear > 0 && $endYear > 0) {
        $sqlYearTrend .= " AND p.publication_year BETWEEN :start_year AND :end_year";
        $paramsYearTrend[':start_year'] = $startYear;
        $paramsYearTrend[':end_year'] = $endYear;
    }
    
    if ($departmentFilter > 0) {
        $sqlYearTrend .= " AND u.department_id = :department_id";
        $paramsYearTrend[':department_id'] = $departmentFilter;
    }
    
    if ($typeFilter > 0) {
        $sqlYearTrend .= " AND p.type_id = :type_id";
        $paramsYearTrend[':type_id'] = $typeFilter;
    }
    
    $sqlYearTrend .= " GROUP BY p.publication_year ORDER BY p.publication_year ASC";
    
    $stmtYearTrend = $pdo->prepare($sqlYearTrend);
    $stmtYearTrend->execute($paramsYearTrend);
    $yearTrend = $stmtYearTrend->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== TYPE DISTRIBUTION =====
    $sqlByType = "
        SELECT 
            pt.type_name_en,
            pt.type_code,
            COUNT(p.publication_id) as count,
            ROUND(COUNT(p.publication_id) * 100.0 / NULLIF((
                SELECT COUNT(*) FROM publications p2
                LEFT JOIN users u2 ON p2.user_id = u2.user_id
                WHERE 1=1
    ";
    
    $paramsByType = [];
    
    if ($startYear > 0 && $endYear > 0) {
        $sqlByType .= " AND p2.publication_year BETWEEN :start_year_sub AND :end_year_sub";
        $paramsByType[':start_year_sub'] = $startYear;
        $paramsByType[':end_year_sub'] = $endYear;
    }
    
    if ($departmentFilter > 0) {
        $sqlByType .= " AND u2.department_id = :department_id_sub";
        $paramsByType[':department_id_sub'] = $departmentFilter;
    }
    
    $sqlByType .= "
            ), 0), 2) as percentage
        FROM publication_types pt
        LEFT JOIN publications p ON pt.type_id = p.type_id
        LEFT JOIN users u ON p.user_id = u.user_id
        WHERE 1=1
    ";
    
    if ($startYear > 0 && $endYear > 0) {
        $sqlByType .= " AND p.publication_year BETWEEN :start_year AND :end_year";
        $paramsByType[':start_year'] = $startYear;
        $paramsByType[':end_year'] = $endYear;
    }
    
    if ($departmentFilter > 0) {
        $sqlByType .= " AND u.department_id = :department_id";
        $paramsByType[':department_id'] = $departmentFilter;
    }
    
    if ($typeFilter > 0) {
        $sqlByType .= " AND p.type_id = :type_id";
        $paramsByType[':type_id'] = $typeFilter;
    }
    
    $sqlByType .= " GROUP BY pt.type_id, pt.type_name_en, pt.type_code HAVING count > 0 ORDER BY count DESC";
    
    $stmtByType = $pdo->prepare($sqlByType);
    $stmtByType->execute($paramsByType);
    $typeDistribution = $stmtByType->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== DEPARTMENT DISTRIBUTION =====
    $sqlByDept = "
        SELECT 
            d.department_id,
            d.department_name_en,
            d.department_name_tr,
            d.faculty_name,
            COUNT(p.publication_id) as publication_count,
            COUNT(DISTINCT p.user_id) as faculty_count,
            ROUND(AVG(YEAR(CURDATE()) - p.publication_year), 1) as avg_publication_age
        FROM departments d
        INNER JOIN users u ON d.department_id = u.department_id
        LEFT JOIN publications p ON u.user_id = p.user_id
        WHERE 1=1
    ";
    
    $paramsByDept = [];
    
    if ($startYear > 0 && $endYear > 0) {
        $sqlByDept .= " AND p.publication_year BETWEEN :start_year AND :end_year";
        $paramsByDept[':start_year'] = $startYear;
        $paramsByDept[':end_year'] = $endYear;
    }
    
    if ($departmentFilter > 0) {
        $sqlByDept .= " AND d.department_id = :department_id";
        $paramsByDept[':department_id'] = $departmentFilter;
    }
    
    if ($typeFilter > 0) {
        $sqlByDept .= " AND p.type_id = :type_id";
        $paramsByDept[':type_id'] = $typeFilter;
    }
    
    $sqlByDept .= " GROUP BY d.department_id, d.department_name_en, d.department_name_tr, d.faculty_name";
    $sqlByDept .= " HAVING publication_count > 0 ORDER BY publication_count DESC";
    
    $stmtByDept = $pdo->prepare($sqlByDept);
    $stmtByDept->execute($paramsByDept);
    $deptDistribution = $stmtByDept->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== TOP AUTHORS =====
    $sqlTopAuthors = "
        SELECT 
            u.full_name,
            COALESCE(d.department_name_en, 'Unknown') as department_name_en,
            COUNT(p.publication_id) as publication_count,
            MIN(p.publication_year) as first_publication,
            MAX(p.publication_year) as last_publication,
            GROUP_CONCAT(DISTINCT pt.type_code ORDER BY pt.type_code SEPARATOR ', ') as publication_types
        FROM users u
        INNER JOIN publications p ON u.user_id = p.user_id
        LEFT JOIN publication_types pt ON p.type_id = pt.type_id
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE 1=1
    ";
    
    $paramsTopAuthors = [];
    
    if ($startYear > 0 && $endYear > 0) {
        $sqlTopAuthors .= " AND p.publication_year BETWEEN :start_year AND :end_year";
        $paramsTopAuthors[':start_year'] = $startYear;
        $paramsTopAuthors[':end_year'] = $endYear;
    }
    
    if ($departmentFilter > 0) {
        $sqlTopAuthors .= " AND u.department_id = :department_id";
        $paramsTopAuthors[':department_id'] = $departmentFilter;
    }
    
    if ($typeFilter > 0) {
        $sqlTopAuthors .= " AND p.type_id = :type_id";
        $paramsTopAuthors[':type_id'] = $typeFilter;
    }
    
    $sqlTopAuthors .= " GROUP BY u.user_id, u.full_name, d.department_name_en";
    $sqlTopAuthors .= " ORDER BY publication_count DESC";
    
    $stmtTopAuthors = $pdo->prepare($sqlTopAuthors);
    $stmtTopAuthors->execute($paramsTopAuthors);
    $topAuthors = $stmtTopAuthors->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== TOP JOURNALS =====
    $sqlJournals = "
        SELECT 
            p.journal_name as name,
            COUNT(*) as count
        FROM publications p
        LEFT JOIN users u ON p.user_id = u.user_id
        WHERE p.journal_name IS NOT NULL AND p.journal_name != ''
    ";
    
    $paramsJournals = [];
    
    if ($startYear > 0 && $endYear > 0) {
        $sqlJournals .= " AND p.publication_year BETWEEN :start_year AND :end_year";
        $paramsJournals[':start_year'] = $startYear;
        $paramsJournals[':end_year'] = $endYear;
    }
    
    if ($departmentFilter > 0) {
        $sqlJournals .= " AND u.department_id = :department_id";
        $paramsJournals[':department_id'] = $departmentFilter;
    }
    
    if ($typeFilter > 0) {
        $sqlJournals .= " AND p.type_id = :type_id";
        $paramsJournals[':type_id'] = $typeFilter;
    }
    
    $sqlJournals .= " GROUP BY p.journal_name ORDER BY count DESC LIMIT 10";
    
    $stmtJournals = $pdo->prepare($sqlJournals);
    $stmtJournals->execute($paramsJournals);
    $topJournals = $stmtJournals->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== TOP CONFERENCES =====
    $sqlConferences = "
        SELECT 
            p.conference_name as name,
            COUNT(*) as count
        FROM publications p
        LEFT JOIN users u ON p.user_id = u.user_id
        WHERE p.conference_name IS NOT NULL AND p.conference_name != ''
    ";
    
    $paramsConferences = [];
    
    if ($startYear > 0 && $endYear > 0) {
        $sqlConferences .= " AND p.publication_year BETWEEN :start_year AND :end_year";
        $paramsConferences[':start_year'] = $startYear;
        $paramsConferences[':end_year'] = $endYear;
    }
    
    if ($departmentFilter > 0) {
        $sqlConferences .= " AND u.department_id = :department_id";
        $paramsConferences[':department_id'] = $departmentFilter;
    }
    
    if ($typeFilter > 0) {
        $sqlConferences .= " AND p.type_id = :type_id";
        $paramsConferences[':type_id'] = $typeFilter;
    }
    
    $sqlConferences .= " GROUP BY p.conference_name ORDER BY count DESC LIMIT 10";
    
    $stmtConferences = $pdo->prepare($sqlConferences);
    $stmtConferences->execute($paramsConferences);
    $topConferences = $stmtConferences->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Report generation error: " . $e->getMessage());
}

// Generate Excel export URL with same filters
$excelUrl = "export_excel.php?start_year=$startYear&end_year=$endYear";
if ($departmentFilter > 0) {
    $excelUrl .= "&department=$departmentFilter";
}
if ($typeFilter > 0) {
    $excelUrl .= "&type=$typeFilter";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Publication Report - <?php echo $startYear; ?>-<?php echo $endYear; ?></title>
    <link rel="stylesheet" href="../assets/css/report_template.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

<!-- Page 1: Cover Page -->
<div class="report-page">
    <div class="report-header">
        <img src="../assets/images/vision_logo_k.png" alt="University Logo" class="university-logo" style="max-width: 120px; height: auto;">
        <h1 class="university-name">INTERNATIONAL VISION UNIVERSITY</h1>
        <p class="university-name-tr">ULUSLARARASI VIZYON ÜNİVERSİTESİ</p>
        <div class="title-divider"></div>
    </div>
    
    <div class="report-title-section">
        <h2 class="report-title">Publication Reports</h2>
        <p class="report-subtitle">Statistical analysis and comprehensive reports</p>
    </div>
    
    <div class="filters-applied">
        <h3 class="filters-title">Applied Filters:</h3>
        <div class="filter-badges">
            <div class="filter-badge filter-period">
                <span class="filter-label">Period:</span>
                <span class="filter-value"><?php echo $startYear; ?> - <?php echo $endYear; ?></span>
            </div>
            <?php if ($departmentFilter > 0 && $selectedDepartment): ?>
            <div class="filter-badge filter-department">
                <span class="filter-label">Department:</span>
                <span class="filter-value"><?php echo sanitize($selectedDepartment['department_name_en']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($typeFilter > 0 && $selectedType): ?>
            <div class="filter-badge filter-type">
                <span class="filter-label">Type:</span>
                <span class="filter-value"><?php echo sanitize($selectedType['type_name_en']); ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Executive Summary Stats -->
    <div class="summary-stats" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 30px 0;">
        <div class="stat-box stat-primary">
            <div class="stat-number"><?php echo number_format($generalStats['total_publications']); ?></div>
            <div class="stat-label">Total Publications</div>
        </div>
        <div class="stat-box stat-success">
            <div class="stat-number"><?php echo $generalStats['active_authors']; ?></div>
            <div class="stat-label">Active Authors</div>
        </div>
        <div class="stat-box stat-info">
            <div class="stat-number"><?php echo $generalStats['publication_types']; ?></div>
            <div class="stat-label">Publication Types</div>
        </div>
        <div class="stat-box stat-warning">
            <div class="stat-number"><?php echo $generalStats['latest_year']; ?></div>
            <div class="stat-label">Latest Year</div>
        </div>
    </div>
    
    <!-- Print Controls (hidden when printing) -->
    <div class="print-controls no-print">
        <button onclick="window.print()" class="btn-print">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print Report
        </button>
        <a href="<?php echo $excelUrl; ?>" class="btn-excel">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="12" y1="18" x2="12" y2="12"></line>
                <line x1="9" y1="15" x2="15" y2="15"></line>
            </svg>
            Export to Excel
        </a>
        <button onclick="window.close()" class="btn-close">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
            Close
        </button>
    </div>
    
    <div class="report-footer">
        <p>Generated on: <?php echo date('F j, Y \a\t g:i A'); ?></p>
        <p>Page 1</p>
    </div>
</div>

<!-- Page 2: Charts -->
<div class="report-page">
    <div class="page-header">
        <h2>Publication Analysis</h2>
    </div>
    
    <!-- Publication Type Distribution (Pie Chart) -->
    <?php if (!empty($typeDistribution)): ?>
    <div class="chart-section">
        <h3 class="section-title">Publication Type Distribution</h3>
        <div class="chart-wrapper">
            <canvas id="typeChart"></canvas>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Annual Trend (Bar Chart) -->
    <?php if (!empty($yearTrend)): ?>
    <div class="chart-section">
        <h3 class="section-title">Annual Publication Trend</h3>
        <div class="chart-wrapper">
            <canvas id="trendChart"></canvas>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="report-footer">
        <p>International Vision University - Publication Reports</p>
        <p>Page 2</p>
    </div>
</div>

<!-- Page 3: Department Distribution -->
<?php if (!empty($deptDistribution)): ?>
<div class="report-page">
    <div class="page-header">
        <h2>Department Distribution</h2>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Rank</th>
                <th>Department</th>
                <th>Faculty</th>
                <th>Publications</th>
                <th>Faculty Count</th>
                <th>Avg. Age (years)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($deptDistribution as $index => $dept): ?>
            <tr>
                <td class="rank-cell"><?php echo $index + 1; ?></td>
                <td><strong><?php echo sanitize($dept['department_name_en']); ?></strong></td>
                <td><?php echo sanitize($dept['faculty_name'] ?? '-'); ?></td>
                <td class="number-cell"><strong><?php echo $dept['publication_count']; ?></strong></td>
                <td class="number-cell"><?php echo $dept['faculty_count']; ?></td>
                <td class="number-cell"><?php echo $dept['avg_publication_age']; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="report-footer">
        <p>International Vision University - Publication Reports</p>
        <p>Page 3</p>
    </div>
</div>
<?php endif; ?>

<!-- Page 4: Top 10 Authors -->
<?php if (!empty($topAuthors)): ?>
<div class="report-page">
    <div class="page-header">
        <h2>All Authors Ranked by Publications</h2>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>Rank</th>
                <th>Author Name</th>
                <th>Department</th>
                <th>Publications</th>
                <th>Active Period</th>
                <th>Publication Types</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($topAuthors as $index => $author): ?>
            <tr>
                <td class="rank-cell rank-<?php echo min($index + 1, 5); ?>"><?php echo $index + 1; ?></td>
                <td><strong><?php echo sanitize($author['full_name']); ?></strong></td>
                <td><?php echo sanitize($author['department_name_en']); ?></td>
                <td class="number-cell"><strong><?php echo $author['publication_count']; ?></strong></td>
                <td><?php echo $author['first_publication']; ?> - <?php echo $author['last_publication']; ?></td>
                <td><small><?php echo sanitize($author['publication_types']); ?></small></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="report-footer">
        <p>International Vision University - Publication Reports</p>
        <p>Page 4</p>
    </div>
</div>
<?php endif; ?>

<!-- Page 5: Top Journals and Conferences -->
<div class="report-page">
    <div class="page-header">
        <h2>Top Journals and Conferences</h2>
    </div>
    
    <div class="two-column-layout">
        <!-- Top Journals -->
        <div class="column">
            <h3 class="section-title">Top 10 Journals</h3>
            <?php if (!empty($topJournals)): ?>
            <table class="data-table compact-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Rank</th>
                        <th>Journal Name</th>
                        <th style="width: 80px;">Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topJournals as $index => $journal): ?>
                    <tr>
                        <td class="rank-cell"><?php echo $index + 1; ?></td>
                        <td><small><?php echo sanitize($journal['name']); ?></small></td>
                        <td class="number-cell"><strong><?php echo $journal['count']; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="no-data">No journal data available</p>
            <?php endif; ?>
        </div>
        
        <!-- Top Conferences -->
        <div class="column">
            <h3 class="section-title">Top 10 Conferences</h3>
            <?php if (!empty($topConferences)): ?>
            <table class="data-table compact-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">Rank</th>
                        <th>Conference Name</th>
                        <th style="width: 80px;">Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topConferences as $index => $conference): ?>
                    <tr>
                        <td class="rank-cell"><?php echo $index + 1; ?></td>
                        <td><small><?php echo sanitize($conference['name']); ?></small></td>
                        <td class="number-cell"><strong><?php echo $conference['count']; ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="no-data">No conference data available</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="report-footer">
        <p>International Vision University - Publication Reports</p>
        <p>Page 5</p>
    </div>
</div>

<!-- Chart.js Scripts -->
<script>
// Publication Type Distribution Pie Chart
<?php if (!empty($typeDistribution)): ?>
const typeCtx = document.getElementById('typeChart');
if (typeCtx) {
    new Chart(typeCtx, {
        type: 'pie',
        data: {
            labels: <?php echo json_encode(array_column($typeDistribution, 'type_name_en')); ?>,
            datasets: [{
                data: <?php echo json_encode(array_column($typeDistribution, 'count')); ?>,
                backgroundColor: [
                    '#2563EB', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
                    '#EC4899', '#14B8A6', '#F97316', '#6366F1', '#84CC16'
                ],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        padding: 15,
                        font: {
                            size: 11,
                            family: "'Inter', sans-serif"
                        },
                        generateLabels: function(chart) {
                            const data = chart.data;
                            return data.labels.map((label, i) => ({
                                text: `${label} (${data.datasets[0].data[i]})`,
                                fillStyle: data.datasets[0].backgroundColor[i],
                                hidden: false,
                                index: i
                            }));
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return `${context.label}: ${context.parsed} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
}
<?php endif; ?>

// Annual Trend Bar Chart
<?php if (!empty($yearTrend)): ?>
const trendCtx = document.getElementById('trendChart');
if (trendCtx) {
    new Chart(trendCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($yearTrend, 'publication_year')); ?>,
            datasets: [{
                label: 'Publications',
                data: <?php echo json_encode(array_column($yearTrend, 'count')); ?>,
                backgroundColor: '#2563EB',
                borderColor: '#1E40AF',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        color: '#E5E7EB'
                    }
                },
                x: {
                    ticks: {
                        font: {
                            size: 11
                        }
                    },
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `Publications: ${context.parsed.y}`;
                        }
                    }
                }
            }
        }
    });
}
<?php endif; ?>
</script>

</body>
</html>