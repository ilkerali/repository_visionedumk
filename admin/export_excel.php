<?php
/**
 * Excel Export for Publication Reports
 * 
 * Generates Excel file with multiple sheets
 */

// Start session FIRST
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

try {
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
            d.department_name_en,
            COUNT(p.publication_id) as publication_count,
            COUNT(DISTINCT p.user_id) as faculty_count
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
    
    $sqlByDept .= " GROUP BY d.department_id, d.department_name_en HAVING publication_count > 0 ORDER BY publication_count DESC";
    
    $stmtByDept = $pdo->prepare($sqlByDept);
    $stmtByDept->execute($paramsByDept);
    $deptDistribution = $stmtByDept->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== ALL PUBLICATIONS =====
    $sqlPubs = "
        SELECT 
            p.publication_id,
            p.title,
            p.authors,
            p.publication_year,
            pt.type_name_en as type,
            u.full_name as author_name,
            COALESCE(d.department_name_en, 'Unknown') as department_name,
            p.journal_name,
            p.conference_name,
            p.publisher,
            p.volume,
            p.issue,
            p.pages,
            p.doi,
            p.url,
            p.created_at
        FROM publications p
        LEFT JOIN publication_types pt ON p.type_id = pt.type_id
        LEFT JOIN users u ON p.user_id = u.user_id
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE 1=1
    ";
    
    $paramsPubs = [];
    
    if ($startYear > 0 && $endYear > 0) {
        $sqlPubs .= " AND p.publication_year BETWEEN :start_year AND :end_year";
        $paramsPubs[':start_year'] = $startYear;
        $paramsPubs[':end_year'] = $endYear;
    }
    
    if ($departmentFilter > 0) {
        $sqlPubs .= " AND u.department_id = :department_id";
        $paramsPubs[':department_id'] = $departmentFilter;
    }
    
    if ($typeFilter > 0) {
        $sqlPubs .= " AND p.type_id = :type_id";
        $paramsPubs[':type_id'] = $typeFilter;
    }
    
    $sqlPubs .= " ORDER BY p.publication_year DESC, p.title ASC";
    
    $stmtPubs = $pdo->prepare($sqlPubs);
    $stmtPubs->execute($paramsPubs);
    $publications = $stmtPubs->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== AUTHORS =====
    $sqlAuthors = "
        SELECT 
            u.full_name,
            COALESCE(d.department_name_en, 'Unknown') as department_name,
            COUNT(p.publication_id) as publication_count,
            MIN(p.publication_year) as first_publication,
            MAX(p.publication_year) as last_publication
        FROM users u
        INNER JOIN publications p ON u.user_id = p.user_id
        LEFT JOIN departments d ON u.department_id = d.department_id
        WHERE 1=1
    ";
    
    $paramsAuthors = [];
    
    if ($startYear > 0 && $endYear > 0) {
        $sqlAuthors .= " AND p.publication_year BETWEEN :start_year AND :end_year";
        $paramsAuthors[':start_year'] = $startYear;
        $paramsAuthors[':end_year'] = $endYear;
    }
    
    if ($departmentFilter > 0) {
        $sqlAuthors .= " AND u.department_id = :department_id";
        $paramsAuthors[':department_id'] = $departmentFilter;
    }
    
    if ($typeFilter > 0) {
        $sqlAuthors .= " AND p.type_id = :type_id";
        $paramsAuthors[':type_id'] = $typeFilter;
    }
    
    $sqlAuthors .= " GROUP BY u.user_id, u.full_name, d.department_name_en ORDER BY publication_count DESC";
    
    $stmtAuthors = $pdo->prepare($sqlAuthors);
    $stmtAuthors->execute($paramsAuthors);
    $authors = $stmtAuthors->fetchAll(PDO::FETCH_ASSOC);
    
    // ===== ANNUAL TRENDS =====
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
    
} catch (PDOException $e) {
    error_log("Excel export error: " . $e->getMessage());
    die("Error generating Excel file. Please try again.");
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="Publication_Report_' . $startYear . '-' . $endYear . '_' . date('Y-m-d') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Output UTF-8 BOM for proper encoding
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #2563EB; color: white; font-weight: bold; padding: 8px; border: 1px solid #ddd; }
        td { padding: 8px; border: 1px solid #ddd; }
        .header { background-color: #f3f4f6; font-weight: bold; }
        .number { text-align: right; }
    </style>
</head>
<body>

<!-- Sheet 1: Summary -->
<h1>Publication Report Summary</h1>
<p><strong>Period:</strong> <?php echo $startYear; ?> - <?php echo $endYear; ?></p>
<p><strong>Generated:</strong> <?php echo date('F j, Y \a\t g:i A'); ?></p>

<h2>General Statistics</h2>
<table>
    <tr>
        <th>Metric</th>
        <th>Value</th>
    </tr>
    <tr>
        <td>Total Publications</td>
        <td class="number"><?php echo $generalStats['total_publications']; ?></td>
    </tr>
    <tr>
        <td>Active Authors</td>
        <td class="number"><?php echo $generalStats['active_authors']; ?></td>
    </tr>
    <tr>
        <td>Publication Types</td>
        <td class="number"><?php echo $generalStats['publication_types']; ?></td>
    </tr>
    <tr>
        <td>Latest Year</td>
        <td class="number"><?php echo $generalStats['latest_year']; ?></td>
    </tr>
    <tr>
        <td>Earliest Year</td>
        <td class="number"><?php echo $generalStats['earliest_year']; ?></td>
    </tr>
    <tr>
        <td>Average Publication Age (years)</td>
        <td class="number"><?php echo number_format($generalStats['avg_age'], 1); ?></td>
    </tr>
</table>

<h2>Publication Type Distribution</h2>
<table>
    <tr>
        <th>Type</th>
        <th>Code</th>
        <th>Count</th>
        <th>Percentage</th>
    </tr>
    <?php foreach ($typeDistribution as $type): ?>
    <tr>
        <td><?php echo htmlspecialchars($type['type_name_en']); ?></td>
        <td><?php echo htmlspecialchars($type['type_code']); ?></td>
        <td class="number"><?php echo $type['count']; ?></td>
        <td class="number"><?php echo $type['percentage']; ?>%</td>
    </tr>
    <?php endforeach; ?>
</table>

<h2>Department Statistics</h2>
<table>
    <tr>
        <th>Department</th>
        <th>Publications</th>
        <th>Faculty Count</th>
    </tr>
    <?php foreach ($deptDistribution as $dept): ?>
    <tr>
        <td><?php echo htmlspecialchars($dept['department_name_en']); ?></td>
        <td class="number"><?php echo $dept['publication_count']; ?></td>
        <td class="number"><?php echo $dept['faculty_count']; ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<!-- Sheet 2: Publications -->
<br><br>
<h1>All Publications</h1>
<table>
    <tr>
        <th>ID</th>
        <th>Title</th>
        <th>Authors</th>
        <th>Year</th>
        <th>Type</th>
        <th>Faculty Member</th>
        <th>Department</th>
        <th>Journal</th>
        <th>Conference</th>
        <th>Publisher</th>
        <th>Volume</th>
        <th>Issue</th>
        <th>Pages</th>
        <th>DOI</th>
        <th>URL</th>
        <th>Added Date</th>
    </tr>
    <?php foreach ($publications as $pub): ?>
    <tr>
        <td><?php echo $pub['publication_id']; ?></td>
        <td><?php echo htmlspecialchars($pub['title']); ?></td>
        <td><?php echo htmlspecialchars($pub['authors']); ?></td>
        <td class="number"><?php echo $pub['publication_year']; ?></td>
        <td><?php echo htmlspecialchars($pub['type']); ?></td>
        <td><?php echo htmlspecialchars($pub['author_name']); ?></td>
        <td><?php echo htmlspecialchars($pub['department_name']); ?></td>
        <td><?php echo htmlspecialchars($pub['journal_name']); ?></td>
        <td><?php echo htmlspecialchars($pub['conference_name']); ?></td>
        <td><?php echo htmlspecialchars($pub['publisher']); ?></td>
        <td><?php echo htmlspecialchars($pub['volume']); ?></td>
        <td><?php echo htmlspecialchars($pub['issue']); ?></td>
        <td><?php echo htmlspecialchars($pub['pages']); ?></td>
        <td><?php echo htmlspecialchars($pub['doi']); ?></td>
        <td><?php echo htmlspecialchars($pub['url']); ?></td>
        <td><?php echo date('Y-m-d', strtotime($pub['created_at'])); ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<!-- Sheet 3: Authors -->
<br><br>
<h1>Author Rankings</h1>
<table>
    <tr>
        <th>Rank</th>
        <th>Author Name</th>
        <th>Department</th>
        <th>Publications</th>
        <th>First Publication</th>
        <th>Last Publication</th>
        <th>Active Years</th>
    </tr>
    <?php foreach ($authors as $index => $author): ?>
    <tr>
        <td class="number"><?php echo $index + 1; ?></td>
        <td><?php echo htmlspecialchars($author['full_name']); ?></td>
        <td><?php echo htmlspecialchars($author['department_name']); ?></td>
        <td class="number"><?php echo $author['publication_count']; ?></td>
        <td class="number"><?php echo $author['first_publication']; ?></td>
        <td class="number"><?php echo $author['last_publication']; ?></td>
        <td class="number"><?php echo $author['last_publication'] - $author['first_publication'] + 1; ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<!-- Sheet 4: Annual Trends -->
<br><br>
<h1>Annual Publication Trends</h1>
<table>
    <tr>
        <th>Year</th>
        <th>Publications</th>
        <th>Unique Authors</th>
        <th>Avg per Author</th>
    </tr>
    <?php foreach ($yearTrend as $year): ?>
    <tr>
        <td class="number"><?php echo $year['publication_year']; ?></td>
        <td class="number"><?php echo $year['count']; ?></td>
        <td class="number"><?php echo $year['unique_authors']; ?></td>
        <td class="number"><?php echo number_format($year['count'] / $year['unique_authors'], 2); ?></td>
    </tr>
    <?php endforeach; ?>
</table>

</body>
</html>