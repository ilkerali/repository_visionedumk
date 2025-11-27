<?php
/**
 * Reports Page - Admin Reports Dashboard
 * Displays publication statistics with filtering capabilities
 */

require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Check if user is admin
requireAdmin();

$pdo = getDBConnection();

// Get filter parameters
$startYear = isset($_GET['start_year']) && !empty($_GET['start_year']) ? (int)$_GET['start_year'] : null;
$endYear = isset($_GET['end_year']) && !empty($_GET['end_year']) ? (int)$_GET['end_year'] : null;
$department = isset($_GET['department']) && !empty($_GET['department']) ? cleanInput($_GET['department']) : null;
$typeId = isset($_GET['type_id']) && !empty($_GET['type_id']) ? (int)$_GET['type_id'] : null;

// Build WHERE clause for filters
$whereConditions = ["p.status = 'published'"];
$params = [];

if ($startYear) {
    $whereConditions[] = "p.publication_year >= :start_year";
    $params[':start_year'] = $startYear;
}

if ($endYear) {
    $whereConditions[] = "p.publication_year <= :end_year";
    $params[':end_year'] = $endYear;
}

if ($department) {
    $whereConditions[] = "u.department = :department";
    $params[':department'] = $department;
}

if ($typeId) {
    $whereConditions[] = "p.type_id = :type_id";
    $params[':type_id'] = $typeId;
}

$whereClause = implode(' AND ', $whereConditions);

// Get all departments for filter dropdown
$deptStmt = $pdo->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department");
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

// Get all publication types for filter dropdown
$typeStmt = $pdo->query("SELECT type_id, type_name_en, type_code FROM publication_types WHERE is_active = 1 ORDER BY display_order, type_name_en");
$publicationTypes = $typeStmt->fetchAll(PDO::FETCH_ASSOC);

// Get year range for filter dropdowns
$yearStmt = $pdo->query("SELECT MIN(publication_year) as min_year, MAX(publication_year) as max_year FROM publications");
$yearRange = $yearStmt->fetch(PDO::FETCH_ASSOC);
$minYear = $yearRange['min_year'] ?? date('Y') - 10;
$maxYear = $yearRange['max_year'] ?? date('Y');

// Section 1: Publication count by type (General Statistics)
$typeStatsQuery = "
    SELECT 
        pt.type_name_en,
        pt.type_code,
        COUNT(p.publication_id) as count
    FROM publication_types pt
    LEFT JOIN publications p ON pt.type_id = p.type_id 
        AND $whereClause
    WHERE pt.is_active = 1
    GROUP BY pt.type_id, pt.type_name_en, pt.type_code
    ORDER BY count DESC, pt.type_name_en
";

$typeStatsStmt = $pdo->prepare($typeStatsQuery);
$typeStatsStmt->execute($params);
$typeStats = $typeStatsStmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate total publications
$totalPublications = array_sum(array_column($typeStats, 'count'));

// Section 2: Publications by Author and Type (Detailed Table)
$authorStatsQuery = "
    SELECT 
        u.user_id,
        u.full_name,
        u.department,
        " . implode(', ', array_map(function($type) {
            return "SUM(CASE WHEN pt.type_code = '{$type['type_code']}' THEN 1 ELSE 0 END) as `{$type['type_code']}`";
        }, $publicationTypes)) . ",
        COUNT(p.publication_id) as total
    FROM users u
    LEFT JOIN publications p ON u.user_id = p.user_id 
        AND $whereClause
    LEFT JOIN publication_types pt ON p.type_id = pt.type_id
    WHERE u.role = 'faculty'
    " . ($department ? "AND u.department = :department" : "") . "
    GROUP BY u.user_id, u.full_name, u.department
    HAVING total > 0
    ORDER BY total DESC, u.full_name
";

$authorStatsStmt = $pdo->prepare($authorStatsQuery);
$authorStatsStmt->execute($params);
$authorStats = $authorStatsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get publication trends (by year)
$trendQuery = "
    SELECT 
        p.publication_year,
        COUNT(p.publication_id) as count
    FROM publications p
    INNER JOIN users u ON p.user_id = u.user_id
    WHERE $whereClause
    GROUP BY p.publication_year
    ORDER BY p.publication_year DESC
    LIMIT 10
";

$trendStmt = $pdo->prepare($trendQuery);
$trendStmt->execute($params);
$trendData = $trendStmt->fetchAll(PDO::FETCH_ASSOC);

// Page title
$pageTitle = "Reports & Statistics";
include 'admin_header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1><i class="fas fa-chart-bar"></i> Reports & Statistics</h1>
        <p class="text-muted">Publication statistics and analytics</p>
    </div>

    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="start_year" class="form-label">Start Year</label>
                    <select name="start_year" id="start_year" class="form-select">
                        <option value="">All Years</option>
                        <?php for ($year = $maxYear; $year >= $minYear; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($startYear == $year) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="end_year" class="form-label">End Year</label>
                    <select name="end_year" id="end_year" class="form-select">
                        <option value="">All Years</option>
                        <?php for ($year = $maxYear; $year >= $minYear; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($endYear == $year) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="department" class="form-label">Department</label>
                    <select name="department" id="department" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo htmlspecialchars($dept); ?>" 
                                <?php echo ($department == $dept) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label for="type_id" class="form-label">Publication Type</label>
                    <select name="type_id" id="type_id" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($publicationTypes as $type): ?>
                            <option value="<?php echo $type['type_id']; ?>" 
                                <?php echo ($typeId == $type['type_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['type_name_en']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="reports.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset Filters
                    </a>
                    <button type="button" class="btn btn-success" onclick="exportToCSV()">
                        <i class="fas fa-download"></i> Export to CSV
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Filters Display -->
    <?php if ($startYear || $endYear || $department || $typeId): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <strong><i class="fas fa-info-circle"></i> Active Filters:</strong>
        <?php 
        $filters = [];
        if ($startYear) $filters[] = "Start Year: $startYear";
        if ($endYear) $filters[] = "End Year: $endYear";
        if ($department) $filters[] = "Department: " . htmlspecialchars($department);
        if ($typeId) {
            $selectedType = array_filter($publicationTypes, function($t) use ($typeId) {
                return $t['type_id'] == $typeId;
            });
            if ($selectedType) {
                $selectedType = reset($selectedType);
                $filters[] = "Type: " . htmlspecialchars($selectedType['type_name_en']);
            }
        }
        echo implode(' | ', $filters);
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <!-- Section 1: General Statistics - Publications by Type -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie"></i> General Statistics - Publications by Type</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="stat-card bg-light p-3 rounded text-center">
                                <h2 class="text-primary mb-0"><?php echo $totalPublications; ?></h2>
                                <p class="text-muted mb-0">Total Publications</p>
                            </div>
                        </div>
                        <div class="col-md-9">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Publication Type</th>
                                            <th class="text-center">Count</th>
                                            <th class="text-center">Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($typeStats as $stat): ?>
                                            <?php 
                                            $percentage = $totalPublications > 0 
                                                ? round(($stat['count'] / $totalPublications) * 100, 1) 
                                                : 0;
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($stat['type_name_en']); ?></strong>
                                                    <small class="text-muted">(<?php echo htmlspecialchars($stat['type_code']); ?>)</small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-primary"><?php echo $stat['count']; ?></span>
                                                </td>
                                                <td class="text-center">
                                                    <div class="progress" style="height: 20px;">
                                                        <div class="progress-bar" role="progressbar" 
                                                            style="width: <?php echo $percentage; ?>%;" 
                                                            aria-valuenow="<?php echo $percentage; ?>" 
                                                            aria-valuemin="0" aria-valuemax="100">
                                                            <?php echo $percentage; ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($typeStats)): ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">No data available</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: Publications by Author -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-users"></i> Publications by Author</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="authorStatsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th>Author</th>
                                    <th>Department</th>
                                    <?php foreach ($publicationTypes as $type): ?>
                                        <th class="text-center" title="<?php echo htmlspecialchars($type['type_name_en']); ?>">
                                            <?php echo htmlspecialchars($type['type_code']); ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="text-center bg-warning">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($authorStats as $author): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($author['full_name']); ?></strong></td>
                                        <td><small><?php echo htmlspecialchars($author['department'] ?? 'N/A'); ?></small></td>
                                        <?php foreach ($publicationTypes as $type): ?>
                                            <td class="text-center">
                                                <?php 
                                                $count = $author[$type['type_code']] ?? 0;
                                                if ($count > 0) {
                                                    echo '<span class="badge bg-info">' . $count . '</span>';
                                                } else {
                                                    echo '<span class="text-muted">-</span>';
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="text-center bg-warning">
                                            <strong><?php echo $author['total']; ?></strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($authorStats)): ?>
                                    <tr>
                                        <td colspan="<?php echo count($publicationTypes) + 3; ?>" class="text-center text-muted">
                                            No publications found matching the selected filters
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <?php if (!empty($authorStats)): ?>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2">TOTAL</th>
                                    <?php foreach ($publicationTypes as $type): ?>
                                        <th class="text-center">
                                            <?php 
                                            $typeTotal = array_sum(array_column($authorStats, $type['type_code']));
                                            echo $typeTotal > 0 ? $typeTotal : '-';
                                            ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="text-center bg-warning">
                                        <?php echo array_sum(array_column($authorStats, 'total')); ?>
                                    </th>
                                </tr>
                            </tfoot>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Publication Trends -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line"></i> Publication Trends (Last 10 Years)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Year</th>
                                    <th class="text-center">Publications</th>
                                    <th>Trend</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $maxCount = !empty($trendData) ? max(array_column($trendData, 'count')) : 1;
                                foreach ($trendData as $trend): 
                                    $barWidth = ($trend['count'] / $maxCount) * 100;
                                ?>
                                    <tr>
                                        <td><strong><?php echo $trend['publication_year']; ?></strong></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?php echo $trend['count']; ?></span>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 25px;">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                    style="width: <?php echo $barWidth; ?>%;" 
                                                    aria-valuenow="<?php echo $barWidth; ?>" 
                                                    aria-valuemin="0" aria-valuemax="100">
                                                    <?php echo $trend['count']; ?> publications
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($trendData)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted">No trend data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Export table to CSV
function exportToCSV() {
    const table = document.getElementById('authorStatsTable');
    let csv = [];
    
    // Get headers
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));
    
    // Get data rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            // Clean the text content
            let text = td.textContent.trim().replace(/,/g, ';');
            row.push(text);
        });
        if (row.length > 0) {
            csv.push(row.join(','));
        }
    });
    
    // Get footer totals
    if (table.querySelector('tfoot')) {
        const footerRow = [];
        table.querySelectorAll('tfoot th').forEach(th => {
            footerRow.push(th.textContent.trim());
        });
        csv.push(footerRow.join(','));
    }
    
    // Create download
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    const filters = [];
    <?php if ($startYear) echo "filters.push('start{$startYear}');"; ?>
    <?php if ($endYear) echo "filters.push('end{$endYear}');"; ?>
    <?php if ($department) echo "filters.push('" . preg_replace('/[^a-zA-Z0-9]/', '', $department) . "');"; ?>
    
    const filename = 'publication_report_' + 
        (filters.length > 0 ? filters.join('_') + '_' : '') +
        new Date().toISOString().slice(0,10) + '.csv';
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Add sorting functionality to table
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('authorStatsTable');
    if (table) {
        const headers = table.querySelectorAll('thead th');
        headers.forEach((header, index) => {
            header.style.cursor = 'pointer';
            header.addEventListener('click', function() {
                sortTable(table, index);
            });
        });
    }
});

function sortTable(table, column) {
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Determine sort direction
    const isAscending = table.dataset.sortColumn === String(column) && 
                       table.dataset.sortDirection === 'asc';
    
    rows.sort((a, b) => {
        const aValue = a.cells[column].textContent.trim();
        const bValue = b.cells[column].textContent.trim();
        
        // Try to parse as number
        const aNum = parseFloat(aValue.replace(/[^0-9.-]/g, ''));
        const bNum = parseFloat(bValue.replace(/[^0-9.-]/g, ''));
        
        if (!isNaN(aNum) && !isNaN(bNum)) {
            return isAscending ? aNum - bNum : bNum - aNum;
        }
        
        // String comparison
        return isAscending ? 
            aValue.localeCompare(bValue) : 
            bValue.localeCompare(aValue);
    });
    
    // Re-append sorted rows
    rows.forEach(row => tbody.appendChild(row));
    
    // Update sort direction
    table.dataset.sortColumn = column;
    table.dataset.sortDirection = isAscending ? 'desc' : 'asc';
}
</script>

<style>
.stat-card {
    border-left: 4px solid #007bff;
    transition: all 0.3s ease;
}

.stat-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.table thead th {
    position: sticky;
    top: 0;
    background-color: #343a40;
    color: white;
    z-index: 10;
}

.table-responsive {
    max-height: 600px;
    overflow-y: auto;
}

.badge {
    font-size: 0.9em;
    padding: 0.35em 0.65em;
}

.progress {
    background-color: #e9ecef;
}

.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 1.5rem;
}

.card-header {
    font-weight: 600;
}

@media print {
    .card {
        break-inside: avoid;
    }
    
    .btn, .alert {
        display: none;
    }
}
</style>

<?php include 'admin_footer.php'; ?>
