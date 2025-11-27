<?php
/**
 * Admin Reports Page - IDEAL VERSION
 * 
 * Bu versiyon sadece department_id kullanır (migrate_departments.sql çalıştırıldıktan sonra)
 * This version uses only department_id (after running migrate_departments.sql)
 * 
 * PREREQUISITES:
 * 1. Run analyze_departments.sql to check data
 * 2. Run migrate_departments.sql to clean up data
 * 3. Verify all users have valid department_id
 * 4. Optional: Drop department column from users table
 */

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

startSession();
requireLogin('../login.php');
requireAdmin('../dashboard.php');

$pdo = getDBConnection();

// Filters
$yearFilter = isset($_GET['year']) ? (int)$_GET['year'] : 0;
$typeFilter = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$departmentFilter = isset($_GET['department']) ? (int)$_GET['department'] : 0;

// Fetch statistics
try {
    // ============================================
    // TOTAL PUBLICATIONS
    // ============================================
    $sql = "
        SELECT COUNT(*) as total 
        FROM publications p
        JOIN users u ON p.user_id = u.user_id
        WHERE 1=1
    ";
    $params = [];
    
    if ($yearFilter > 0) {
        $sql .= " AND p.publication_year = :year";
        $params[':year'] = $yearFilter;
    }
    if ($typeFilter > 0) {
        $sql .= " AND p.type_id = :type_id";
        $params[':type_id'] = $typeFilter;
    }
    if ($departmentFilter > 0) {
        $sql .= " AND u.department_id = :dept_id";
        $params[':dept_id'] = $departmentFilter;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $totalPublications = $stmt->fetch()['total'];
    
    // ============================================
    // PUBLICATIONS BY TYPE
    // ============================================
    $sql = "
        SELECT 
            pt.type_name_en,
            pt.type_code,
            COUNT(p.publication_id) as count
        FROM publication_types pt
        LEFT JOIN publications p ON pt.type_id = p.type_id
        LEFT JOIN users u ON p.user_id = u.user_id
        WHERE 1=1
    ";
    
    if ($yearFilter > 0) {
        $sql .= " AND p.publication_year = :year";
    }
    if ($departmentFilter > 0) {
        $sql .= " AND u.department_id = :dept_id";
    }
    
    $sql .= " GROUP BY pt.type_id, pt.type_name_en, pt.type_code 
              ORDER BY count DESC";
    
    $stmt = $pdo->prepare($sql);
    if ($yearFilter > 0) {
        $stmt->bindValue(':year', $yearFilter, PDO::PARAM_INT);
    }
    if ($departmentFilter > 0) {
        $stmt->bindValue(':dept_id', $departmentFilter, PDO::PARAM_INT);
    }
    $stmt->execute();
    $publicationsByType = $stmt->fetchAll();
    
    // ============================================
    // PUBLICATIONS BY YEAR
    // ============================================
    $sql = "
        SELECT 
            p.publication_year,
            COUNT(*) as count
        FROM publications p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.publication_year IS NOT NULL
    ";
    $params = [];
    
    if ($typeFilter > 0) {
        $sql .= " AND p.type_id = :type_id";
        $params[':type_id'] = $typeFilter;
    }
    if ($departmentFilter > 0) {
        $sql .= " AND u.department_id = :dept_id";
        $params[':dept_id'] = $departmentFilter;
    }
    
    $sql .= " GROUP BY p.publication_year 
              ORDER BY p.publication_year DESC 
              LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $publicationsByYear = $stmt->fetchAll();
    
    // ============================================
    // PUBLICATIONS BY DEPARTMENT - CLEAN VERSION!
    // ============================================
    $sql = "
        SELECT 
            d.department_name_en,
            d.department_name_tr,
            d.department_code,
            d.faculty_name,
            COUNT(DISTINCT p.publication_id) as count
        FROM departments d
        LEFT JOIN users u ON u.department_id = d.department_id
        LEFT JOIN publications p ON u.user_id = p.user_id
        WHERE d.is_active = 1
    ";
    $params = [];
    
    if ($yearFilter > 0) {
        $sql .= " AND p.publication_year = :year";
        $params[':year'] = $yearFilter;
    }
    if ($typeFilter > 0) {
        $sql .= " AND p.type_id = :type_id";
        $params[':type_id'] = $typeFilter;
    }
    
    $sql .= " GROUP BY d.department_id, d.department_name_en, d.department_name_tr, 
                       d.department_code, d.faculty_name
              HAVING count > 0
              ORDER BY count DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $publicationsByDepartment = $stmt->fetchAll();
    
    // ============================================
    // PUBLICATIONS BY FACULTY
    // ============================================
    $sql = "
        SELECT 
            COALESCE(d.faculty_name, 'Other') as faculty_name,
            COUNT(DISTINCT p.publication_id) as count
        FROM publications p
        JOIN users u ON p.user_id = u.user_id
        JOIN departments d ON u.department_id = d.department_id
        WHERE 1=1
    ";
    $params = [];
    
    if ($yearFilter > 0) {
        $sql .= " AND p.publication_year = :year";
        $params[':year'] = $yearFilter;
    }
    if ($typeFilter > 0) {
        $sql .= " AND p.type_id = :type_id";
        $params[':type_id'] = $typeFilter;
    }
    if ($departmentFilter > 0) {
        $sql .= " AND u.department_id = :dept_id";
        $params[':dept_id'] = $departmentFilter;
    }
    
    $sql .= " GROUP BY d.faculty_name 
              ORDER BY count DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $publicationsByFaculty = $stmt->fetchAll();
    
    // ============================================
    // TOP AUTHORS
    // ============================================
    $sql = "
        SELECT 
            u.full_name,
            d.department_name_en,
            d.department_code,
            d.faculty_name,
            COUNT(p.publication_id) as pub_count
        FROM users u
        JOIN departments d ON u.department_id = d.department_id
        LEFT JOIN publications p ON u.user_id = p.user_id
        WHERE p.publication_id IS NOT NULL
    ";
    $params = [];
    
    if ($yearFilter > 0) {
        $sql .= " AND p.publication_year = :year";
        $params[':year'] = $yearFilter;
    }
    if ($typeFilter > 0) {
        $sql .= " AND p.type_id = :type_id";
        $params[':type_id'] = $typeFilter;
    }
    if ($departmentFilter > 0) {
        $sql .= " AND u.department_id = :dept_id";
        $params[':dept_id'] = $departmentFilter;
    }
    
    $sql .= " GROUP BY u.user_id, u.full_name, d.department_name_en, 
                       d.department_code, d.faculty_name
              ORDER BY pub_count DESC 
              LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $topAuthors = $stmt->fetchAll();
    
    // ============================================
    // GET FILTER OPTIONS
    // ============================================
    
    // Years
    $stmtYears = $pdo->query("
        SELECT DISTINCT publication_year 
        FROM publications 
        WHERE publication_year IS NOT NULL 
        ORDER BY publication_year DESC
    ");
    $years = $stmtYears->fetchAll();
    
    // Publication Types
    $stmtTypes = $pdo->query("
        SELECT type_id, type_name_en, type_code 
        FROM publication_types 
        WHERE is_active = 1
        ORDER BY display_order, type_name_en
    ");
    $publicationTypes = $stmtTypes->fetchAll();
    
    // Departments with counts
    $stmtDepts = $pdo->query("
        SELECT 
            d.department_id,
            d.department_name_en,
            d.department_code,
            d.faculty_name,
            COUNT(DISTINCT p.publication_id) as pub_count
        FROM departments d
        LEFT JOIN users u ON u.department_id = d.department_id
        LEFT JOIN publications p ON u.user_id = p.user_id
        WHERE d.is_active = 1
        GROUP BY d.department_id, d.department_name_en, d.department_code, d.faculty_name
        HAVING pub_count > 0
        ORDER BY d.faculty_name, d.department_name_en
    ");
    $departments = $stmtDepts->fetchAll();
    
} catch (PDOException $e) {
    error_log("Reports error: " . $e->getMessage());
    $error = "Error loading statistics. Please check the logs.";
    
    // Initialize empty arrays
    $totalPublications = 0;
    $publicationsByType = [];
    $publicationsByYear = [];
    $publicationsByDepartment = [];
    $publicationsByFaculty = [];
    $topAuthors = [];
    $years = [];
    $publicationTypes = [];
    $departments = [];
}

$page_title = "Publication Reports";
include 'admin_header.php';
?>

<!-- Same HTML as before, but cleaner and more reliable -->
<div class="page-header">
    <div>
        <h1>Publication Reports</h1>
        <p class="text-muted">Statistical analysis and comprehensive reports</p>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error">
        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
        <?php echo sanitize($error); ?>
    </div>
<?php endif; ?>

<!-- Filter Section -->
<div class="filter-section">
    <form method="GET" action="reports.php">
        <div class="filter-grid">
            <div class="form-group">
                <label for="year">Year</label>
                <select id="year" name="year">
                    <option value="">All Years</option>
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year['publication_year']; ?>" 
                                <?php echo $yearFilter === $year['publication_year'] ? 'selected' : ''; ?>>
                            <?php echo $year['publication_year']; ?>
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

            <div class="form-group">
                <label for="department">Department</label>
                <select id="department" name="department">
                    <option value="">All Departments</option>
                    <?php 
                    $currentFaculty = '';
                    foreach ($departments as $dept): 
                        if ($dept['faculty_name'] != $currentFaculty):
                            if ($currentFaculty != '') echo '</optgroup>';
                            $currentFaculty = $dept['faculty_name'];
                            echo '<optgroup label="' . sanitize($currentFaculty) . '">';
                        endif;
                    ?>
                        <option value="<?php echo $dept['department_id']; ?>" 
                                <?php echo $departmentFilter === $dept['department_id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($dept['department_name_en']); ?>
                            (<?php echo $dept['pub_count']; ?>)
                        </option>
                    <?php 
                    endforeach;
                    if ($currentFaculty != '') echo '</optgroup>';
                    ?>
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
            <a href="reports.php" class="btn btn-secondary">
                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M3 6h18"></path>
                    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                </svg>
                Clear Filters
            </a>
        </div>
    </form>
</div>

<!-- Statistics Grid -->
<div class="stats-grid stats-grid-4">
    <div class="stat-card stat-primary">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo $totalPublications; ?></h3>
            <p>Total Publications</p>
        </div>
    </div>
    
    <div class="stat-card stat-success">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo count($publicationsByDepartment); ?></h3>
            <p>Active Departments</p>
        </div>
    </div>
    
    <div class="stat-card stat-info">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo count($topAuthors); ?></h3>
            <p>Contributing Authors</p>
        </div>
    </div>
    
    <div class="stat-card stat-warning">
        <div class="stat-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
        </div>
        <div class="stat-content">
            <h3><?php echo count($publicationsByYear); ?></h3>
            <p>Years Covered</p>
        </div>
    </div>
</div>

<!-- Publications by Faculty (NEW!) -->
<div class="card">
    <div class="card-header">
        <h2>Publications by Faculty</h2>
    </div>
    <div class="card-body">
        <?php if (!empty($publicationsByFaculty)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Faculty</th>
                        <th>Publications</th>
                        <th>Percentage</th>
                        <th>Visual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($publicationsByFaculty as $item): ?>
                        <tr>
                            <td><strong><?php echo sanitize($item['faculty_name']); ?></strong></td>
                            <td><strong><?php echo $item['count']; ?></strong></td>
                            <td>
                                <?php 
                                $percentage = $totalPublications > 0 ? round(($item['count'] / $totalPublications) * 100, 1) : 0;
                                echo $percentage . '%';
                                ?>
                            </td>
                            <td>
                                <div style="background: var(--gray-200); height: 20px; border-radius: 10px; overflow: hidden;">
                                    <div style="background: var(--primary-color); height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.3s;"></div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-muted">No faculty data available</p>
        <?php endif; ?>
    </div>
</div>

<!-- Rest of the report sections... -->
<!-- (Publications by Type, Department, Year, Top Authors - same as before) -->

<?php include 'admin_footer.php'; ?>
